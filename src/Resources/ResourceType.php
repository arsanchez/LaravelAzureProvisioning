<?php

namespace RobTrehy\LaravelAzureProvisioning\Resources;

use Illuminate\Database\Eloquent\Model;
use RobTrehy\LaravelAzureProvisioning\Exceptions\AzureProvisioningException;
use RobTrehy\LaravelAzureProvisioning\Utils\AzureHelper;

class ResourceType
{
    protected $configuration = null;

    protected $name = null;

    public function __construct($name, $configuration)
    {
        $this->name = $name;
        $this->configuration = $configuration;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getSingular()
    {
        return $this->configuration['singular'];
    }

    public function getSchema()
    {
        return $this->configuration['schema'];
    }

    public function getModel()
    {
        return $this->configuration['model'];
    }

    public function getExcludes()
    {
        return $this->configuration['exclude'];
    }

    public function getMapping()
    {
        return $this->configuration['mapping'];
    }

    public function getMappingForAttribute($attribute)
    {
        $mapping = $this->getMapping();
        $attribute = strtolower($attribute);

        if (array_key_exists($attribute, $mapping)) {
            return $mapping[$attribute];
        }

        return false;
//        throw (new AzureProvisioningException(sprintf("Attribute \"%s\" is not valid", $attribute)))->setCode(400);
    }

    public function getMappingForArrayAttribute($attribute, $scimArray)
    {
        $mapping = $this->getMapping();
        $array = AzureHelper::flattenArrayValue($scimArray, [$this->getSchema()], [$attribute]);
        $results = [];
        $values = [];

        foreach ($array as $key => $v) {
            $key = $this->getAttributefromSCIMAttribute($key);
            if (array_key_exists($key, $mapping)) {
                $results[$key] = $mapping[$key];
                $values[$key] = $v;
            }
        }

        return [$results, $values];
    }

    public function getDefaults()
    {
        return $this->configuration['defaults'];
    }

    public function getDefaultValueForAttribute($attribute)
    {
        $attribute = strtolower($attribute);

        if (array_key_exists($attribute, $this->configuration['defaults'])) {
            return $this->configuration['defaults'][$attribute];
        } else {
            return null;
        }
    }

    public function getValidations()
    {
        return $this->configuration['validations'];
    }

    public function getAttributefromSCIMAttribute($scimAttribute)
    {
        if (!is_array($this->getSchema())) {
            if (strpos($scimAttribute, $this->getSchema()) !== false) {
                return str_replace($this->getSchema().':', '', $scimAttribute);
            }
        } else {
            foreach ($this->getSchema() as $schema) {
                if (strpos($scimAttribute, $schema) !== false) {
                    return str_replace($schema.':', '', $scimAttribute);
                }
            }
        }

        return $scimAttribute;
    }

    public function createFromSCIM(array $data)
    {
        return new Model();
    }

    public function replaceFromSCIM(array $validatedData, Model $object)
    {
        return $object;
    }

    public function patch(array $operation, Model $object)
    {
        return $object;
    }

    public function user()
    {
        return new UsersResourceType('Users', config('azureprovisioning.Users'));
    }

    public function group()
    {
        return new GroupsResourceType('Groups', config('azureprovisioning.Groups'));
    }
}
