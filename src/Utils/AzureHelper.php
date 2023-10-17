<?php

namespace RobTrehy\LaravelAzureProvisioning\Utils;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RobTrehy\LaravelAzureProvisioning\Exceptions\AzureProvisioningException;
use RobTrehy\LaravelAzureProvisioning\Resources\ResourceType;
use RobTrehy\LaravelAzureProvisioning\Resources\UsersResourceType;
use Tmilos\ScimFilterParser\Ast\ComparisonExpression;
use Tmilos\ScimFilterParser\Ast\Conjunction;
use Tmilos\ScimFilterParser\Ast\Disjunction;
use Tmilos\ScimFilterParser\Ast\Factor;
use Tmilos\ScimFilterParser\Ast\Negation;
use Tmilos\ScimFilterParser\Ast\ValuePath;

class AzureHelper
{
    /**
     * See https://tools.ietf.org/html/rfc7644#section-3.4.2.2
     *
     * @throws AzureProvisioningException
     */
    public static function filterToQuery(ResourceType $resourceType, &$query, $node)
    {
        if ($node instanceof Negation) {
            throw (new AzureProvisioningException('Negation filters are not supported'))
                ->setCode(400)
                ->setSCIMType('invalidFilter');
        } elseif ($node instanceof ComparisonExpression) {
            $operator = strtolower($node->operator);
            $attribute = $node->attributePath->attributeNames[0];
            $mapping = $resourceType->getMappingForAttribute($attribute);

            self::applyWhereConditionToQuery($mapping, $query, $operator, $node->compareValue);
        } elseif ($node instanceof Conjunction) {
            foreach ($node->getFactors() as $factor) {
                $query->where(
                    function ($query) use ($factor, $resourceType) {
                        self::filterToQuery($resourceType, $query, $factor);
                    }
                );
            }
        } elseif ($node instanceof Disjunction) {
            foreach ($node->getTerms() as $term) {
                $query->orWhere(
                    function ($query) use ($term, $resourceType) {
                        self::filterToQuery($resourceType, $query, $term);
                    }
                );
            }
        } elseif ($node instanceof ValuePath) {
            $getAttributePath = function () {
                return $this->attributePath;
            };

            $getFilter = function () {
                return $this->filter;
            };

            $query->whereExists(
                function ($query) {
                    $query->select(DB::raw(1))
                        ->from('users AS users2')
                        ->whereRaw('users.id = users2.id');
                }
            );
        } elseif ($node instanceof Factor) {
            throw (new AzureProvisioningException('Factor is not supported'))
                ->setCode(400)
                ->setSCIMType('invalidFilter');
        }
    }

    public static function prepareReturn(
        Arrayable $object,
        ResourceType $resourceType = null,
        array $attributes = [],
        array $excludedAttributes = []
    ) {
        $result = null;

        if (!empty($object) && isset($object[0]) && is_object($object[0])) {
            $result = [];
            foreach ($object as $value) {
                $result[] = self::objectToSCIMArray($value, $resourceType, $attributes, $excludedAttributes);
            }
        }

        return ($result <> null ? $result : $object);
    }

    public static function objectLocation(Model $object, ResourceType $resourceType = null)
    {
        return (substr(config('app.url'), -1) <> '/' ?
            config('app.url').'/'.config('azureprovisioning.routePrefix').'/'.$resourceType->getName().'/'.$object->id :
            config('app.url').config('azureprovisioning.routePrefix').'/'.$resourceType->getName().'/'.$object->id);
    }

    public static function objectToSCIMArray(
        Model $object,
        ResourceType $resourceType = null,
        array $attributes = [],
        array $excludedAttributes = []
    ) {
        $array = $object->toArray();

        // If the getDates method exists, ensure proper formatting of dates
        if (method_exists($object, 'getDates')) {
            $dateAttributes = $object->getDates();
            foreach ($dateAttributes as $dateAttribute) {
                if (isset($array[$dateAttribute])) {
                    $array[$dateAttribute] = $object->getAttribute($dateAttribute)->format('c');
                }
            }
        }

        $result = [];

        if ($resourceType <> null) {
            if ((count($attributes) === 0 || self::inArrayi('schemas', $attributes)) &&
                (count($excludedAttributes) === 0 || !self::inArrayi('schemas', $excludedAttributes))) {
                $result['schemas'] = is_array($resourceType->getSchema())
                    ? $resourceType->getSchema()
                    : [$resourceType->getSchema()];
            }
            $result = self::roughen($result, $resourceType, $object, $attributes, $excludedAttributes);

            if ((count($attributes) === 0 || self::inArrayi('meta', $attributes)) &&
                (count($excludedAttributes) === 0 || !self::inArrayi('meta', $excludedAttributes))) {
                $result['meta'] = [
                    'resourceType' => $resourceType->getSingular(),
                    'created' => $array['created_at'],
                    'lastModified' => $array['updated_at'],
                    'location' => self::objectLocation($object, $resourceType),
                    'version' => self::getResourceObjectVersion($object)
                ];
            }
        } else {
            $result = $array;
        }

        return $result;
    }

    public static function objectToSCIMResponse(
        Model $object,
        ResourceType $resourceType = null,
        array $attributes = [],
        array $excludedAttributes = []
    ) {
        return response(self::objectToSCIMArray($object, $resourceType, $attributes, $excludedAttributes))
        // Leaving this in causes a loop when getting an object
            // ->header('Location', self::objectLocation($object, $resourceType))
            ->setEtag(self::getResourceObjectVersion($object));
    }

    public static function objectToSCIMCreateResponse(Model $object, ResourceType $resourceType = null)
    {
        return self::objectToSCIMResponse($object, $resourceType)->setStatusCode(201);
    }

    public static function getResourceObjectVersion($object)
    {
        $version = null;

        if (method_exists($object, "getSCIMVersion")) {
            $version = $object->getSCIMVersion();
        } else {
            $version = sha1($object->getKey() . $object->updated_at . $object->created_at);
        }

        // Entity tags uniquely representing the requested resources.
        // They are a string of ASCII characters placed between double quotes
        return sprintf('W/"%s"', $version);
    }

    public static function getFlattenKey($parts, $schemas)
    {
        $result = "";
        $partsCopy = $parts;
        $first = Arr::first($partsCopy);

        if ($first <> null) {
            if (in_array($first, $schemas)) {
                $result .= $first . ":";
                array_shift($partsCopy);
            } else {
                // If no schema is provided, use the first schema
                if (is_array($schemas[0])) {
                    $result .= $schemas[0][0] . ":";
                } else {
                    $result .= $schemas[0] . ":";
                }
            }

            $result .= implode(".", $partsCopy);
        } else {
            throw (new AzureProvisioningException("An unknown error occured. " . json_encode($partsCopy)));
        }

        return $result;
    }

    public static function flatten($array, array $schemas, $parts = [])
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (strtolower($key) === "emails" || strtolower($key) === "phonenumbers") {
                $result = $result + self::flatten($value, $schemas, array_merge($parts, [$key]));
                $result = $result + self::flattenArrayValue($value, $schemas, array_merge($parts, [$key]));
            } elseif (is_numeric($key)) {
                if (is_array($value)) {
                    $result = $result + self::flattenArrayValue($value, $schemas, array_merge($parts, [$key]));
                } else {
                    $final = self::getFlattenKey($parts, $schemas);

                    if (!isset($result[$final])) {
                        $result[$final] = [];
                    }

                    $result[$final][$key] = $value;
                }
            } elseif (is_array($value)) {
                // Empty values matter
                if (empty($value)) {
                    $partsCopy = $parts;
                    $partsCopy[] = $key;
                    $final = self::getFlattenKey($partsCopy, $schemas);
                    $result[$final] = $value;
                } else {
                    $result = $result + self::flatten($value, $schemas, array_merge($parts, [$key]));
                }
            } else {
                $partsCopy = $parts;
                $partsCopy[] = $key;

                $result[self::getFlattenKey($partsCopy, $schemas)] = $value;
            }
        }

        return $result;
    }

    public static function flattenArrayValue($array, array $schemas, $parts = [])
    {
        $result = [];

        foreach ($array as $key => $value) {
            $partsCopy = $parts;
            $partsPrimary = array_merge($parts, ['', '']);
            $val = "";
            $valPrimary = null;

            if (is_array($value)) {
                foreach ($value as $key => $value) {
                    if (strtolower($key) === "type") {
                        $partsPrimary[1] = $value;
                        $partsCopy[] = $value;
                    } elseif (strtolower($key) === "primary") {
                        $partsPrimary[2] = strtolower($key);
                        $valPrimary = $value;
                    } elseif (strtolower($key) === "value") {
                        $partsCopy[] = strtolower($key);
                        $val = $value;
                    }
                }
            } else {
                $partsCopy[] = strtolower($key);
                $val = $value;
            }

            $result[self::getFlattenKey($partsCopy, $schemas)] = $val;

            if ($valPrimary !== null && $partsPrimary[2] !== '') {
                $result[self::getFlattenKey($partsPrimary, $schemas)] = $valPrimary;
            }
        }

        return $result;
    }

    public static function roughen(
        array $result,
        ResourceType $resourceType,
        Model $object,
        array $attributes,
        array $excludedAttributes
    ) {
        $mapping = $resourceType->getMapping();
        $excludes = $resourceType->getExcludes();

        foreach ($mapping as $key => $value) {
            if ((count($attributes) === 0 || self::inArrayi($key, $attributes)) &&
                (count($excludedAttributes) === 0 || !self::inArrayi($key, $excludedAttributes))) {
                if (count($excludes) <> 0) {
                    if (!in_array($key, $excludes)) {
                        $result = self::resultFromKeyValue($result, $key, $value, $object);
                    }
                } else {
                    $result = self::resultFromKeyValue($result, $key, $value, $object);
                }
            }
        }

        return $result;
    }

    private static function resultFromKeyValue($result, $key, $value, $object, $exclude = null)
    {
        if ($value <> null && $key <> $exclude) {
            if (strpos($key, '.') > 0) {
                $keys = explode('.', $key);
                if ($keys[0] === "emails") {
                    if (empty($attributes) || self::inArrayi($keys[0], $attributes)) {
                        $result[$keys[0]] = [[
                            'type' => $keys[1],
                            'value' => $object->email,
                            'primary' => true
                        ]];
                    }
                }
            } elseif ($key === "members") {
                $result[$key] = [];
                foreach ($object->users() as $member) {
                    $result[$key][] = self::objectToSCIMArray(
                        $member,
                        new UsersResourceType('Users', config('azureprovisioning.Users'))
                    );
                }
            } else {
                if (empty($attributes) || self::inArrayi($key, $attributes)) {
                    if ($key === "active") {
                        $result[$key] = ($object->{$value} === true || $object->{$value} === "1") ? true : false;
                    } else {
                        $result[$key] = $object->{$value};
                    }
                }
            }
        }

        return $result;
    }

    private static function inArrayi($needle, $haystack)
    {
        return in_array(strtolower($needle), array_map('strtolower', $haystack));
    }

    private static function applyWhereConditionToQuery($attribute, &$query, $operator, $value)
    {
        switch ($operator) {
            case "eq":
                $query->where($attribute, $value);
                break;
            case "ne":
                $query->where($attribute, '<>', $value);
                break;
            case "co":
                $query->where($attribute, 'like', '%' . addcslashes($value, '%_') . '%');
                break;
            case "sw":
                $query->where($attribute, 'like', addcslashes($value, '%_') . '%');
                break;
            case "ew":
                $query->where($attribute, 'like', '%' . addcslashes($value, '%_'));
                break;
            case "pr":
                $query->whereNotNull($attribute);
                break;
            case "gt":
                $query->where($attribute, '>', $value);
                break;
            case "ge":
                $query->where($attribute, '>=', $value);
                break;
            case "lt":
                $query->where($attribute, '<', $value);
                break;
            case "le":
                $query->where($attribute, '<=', $value);
                break;
            default:
                die("Not supported!!");
                break;
        }
    }
}
