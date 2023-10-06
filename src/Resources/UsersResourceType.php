<?php

namespace RobTrehy\LaravelAzureProvisioning\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use RobTrehy\LaravelAzureProvisioning\Exceptions\AzureProvisioningException;

class UsersResourceType extends ResourceType
{
    /**
     *
     */
    public function createFromSCIM(array $validatedData)
    {
        $model = $this->getModel();
        $data = [];

        foreach ($validatedData as $scimAttribute => $scimValue) {
            if (is_array($scimValue)) {
                $array = $this->getMappingForArrayAttribute($scimAttribute, $scimValue);
                $map = $array[0];
                $value = $array[1];
            } else {
                $map = $this->getMappingForAttribute($scimAttribute);
                $value = $scimValue;
            }

            if ($map <> null) {
                if (is_array($map)) {
                    foreach ($map as $key => $attribute) {
                        if ($key !== "password") {
                            $data[$attribute] = $value[$key];
                        } else {
                            $data[$attribute] = Hash::make($value[$key]);
                        }
                    }
                } else {
                    if ($map !== "password") {
                        $data[$map] = $scimValue;
                    } else {
                        $data[$map] = Hash::make($scimValue);
                    }
                }
            }

            foreach ($this->getDefaults() as $key => $value) {
                if (!array_key_exists($key, $data)) {
                    if ($key <> 'password') {
                        $data[$key] = $value;
                    } else {
                        $data[$key] = Hash::make($value);
                    }
                }
            }
        }

        try {
            $resourceObject = $model::create($data);
        } catch (QueryException $exception) {
            if ($exception->getPrevious()->errorInfo[1] === 1062) {
                throw (new AzureProvisioningException("User already exists"))->setCode(409);
            } else {
                throw $exception;
            }
        }

        return $resourceObject;
    }

    public function replaceFromSCIM(array $validatedData, Model $object)
    {
        // Keep an array of written values
        $uses = [];
        $updated = [];

        foreach ($validatedData as $key => $value) {
            if (!isset($original[$key]) || json_encode($original[$key]) != json_encode($validatedData[$key])) {
                $updated[$key] = $validatedData[$key];
            }
        }

        // Write all values
        foreach ($validatedData as $scimAttribute => $scimValue) {
            if (is_array($scimValue)) {
                $array = $this->getMappingForArrayAttribute($scimAttribute, $scimValue);
                $map = $array[0];
                $value = $array[1];
            } else {
                $map = $this->getMappingForAttribute($scimAttribute);
                $value = $scimValue;
            }

            if ($map <> null) {
                if (is_array($map)) {
                    foreach ($map as $key => $attribute) {
                        if ($key !== "password") {
                            $object->{$attribute} = $value[$key];
                        } else {
                            $object->{$attribute} = Hash::make($value[$key]);
                        }
                        $uses[] = $attribute;
                    }
                } else {
                    if ($map !== "password") {
                        $object->{$map} = $scimValue;
                    } else {
                        $object->{$map} = Hash::make($scimValue);
                    }
                    $uses[] = $map;
                }
            }
        }

        // Find values that have not been written in order to empty these
        $allAttributes = $this->getMapping();

        foreach ($uses as $use) {
            foreach ($allAttributes as $key => $value) {
                if ($use === $value) {
                    unset($allAttributes[$key]);
                }
            }
        }

        foreach ($allAttributes as $scimAttribute => $attribute) {
            if ($attribute <> 'id' && !is_null($attribute)) {
                // Set all others to default value
                if ($attribute <> 'password') {
                    $object->{$attribute} = $this->getDefaultValueForAttribute($attribute);
                } else {
                    $object->{$attribute} = Hash::make($this->getDefaultValueForAttribute($attribute));
                }
            }
        }

        $object->save();

        return $object;
    }

    public function patch(array $operation, Model $object)
    {
        switch (strtolower($operation['op'])) {
            case "add":
                if (isset($operation['path'])) {
                    $attribute = $this->getMappingForAttribute($operation['path']);
                    foreach ($operation['value'] as $value) {
                        $object->{$attribute}->add($value);
                    }
                } else {
                    foreach ($operation['value'] as $key => $value) {
                        $attribute = $this->getMappingForAttribute($key);
                        foreach ($value as $v) {
                            $object->{$attribute}->add($v);
                        }
                    }
                }
                break;
            case "remove":
                if (isset($operation['path'])) {
                    $attribute = $this->getMappingForAttribute($operation['path']);
                    $object->{$attribute}->remove();
                } else {
                    throw new AzureProvisioningException("You must provide a \"Path\"");
                }
                break;
            case "replace":
                if (isset($operation['path'])) {
                    $attribute = $this->getMappingForAttribute($operation['path']);
                    if ($attribute === "active" || $operation['path'] === "active") {
                        $object->{$attribute} = ($operation['value'] == true) ? '1' : '0';
                    } else {
                        $object->{$attribute} = $operation['value'];
                    }
                } else {
                    foreach ($operation['value'] as $key => $value) {
                        $attribute = $this->getMappingForAttribute($key);
                        if ($attribute === "active") {
                            $object->{$attribute} = ($value === true) ? '1' : '0';
                        } else {
                            $object->{$attribute} = $value;
                        }
                    }
                }
                break;
            default:
                throw new AzureProvisioningException(sprintf('Operation "%s" is not supported', $operation['op']));
        }

        $object->save();

        return $object;
    }
}
