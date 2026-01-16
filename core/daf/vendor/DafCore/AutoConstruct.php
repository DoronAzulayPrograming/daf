<?php 
namespace DafCore;

use DafCore\AutoConstruct\Name;
use DafCore\AutoConstruct\Ignore;

class AutoConstruct implements \JsonSerializable
{
    public function __construct()
    {
        $this->onLoad();

        $arguments = func_get_args();
        $numberOfArguments = func_num_args();

        $constructor = method_exists(
            $this,
            $fn = "__construct" . $numberOfArguments
        );

        if ($constructor) {
            call_user_func_array([$this, $fn], $arguments);
        } else if ($numberOfArguments < 1) {
            $this->onAfterLoad();
        } else if ($this->isAssociativeArray($arguments[0] ?? null)) {
            $this->initializeFromNamedArray($arguments[0]);
        } else {
            $this->initializeFromPositionalArray($arguments);
        }
        
        $this->onAfterLoad();
    }

    public function onLoad(){}
    public function onAfterLoad(){}

    function jsonSerialize(): mixed
    {
        $data = [];
        $reflection = new \ReflectionClass($this);

        foreach ($reflection->getProperties() as $property) {
            $skip = false;
            foreach ($property->getAttributes() as $attribute) {
                if($attribute->getName() === Ignore::class){
                    $skip = true;
                    break;
                }
                if($attribute->getName() === Name::class){
                    $name = $attribute->newInstance()->value;
                }
            }

            if($skip) continue;

            if($property->isPublic() && $property->isInitialized($this))
                $data[$name ?? $property->getName()] = $property->getValue($this);
        }
        return $data;
    }

    function initializeProperties(): self
    {
        $reflection = new \ReflectionClass($this);
    
        foreach ($reflection->getProperties() as $property) {
            if (!$property->isPublic() || $property->isInitialized($this)) {
                continue;
            }
    
            $type = $property->getType();
            $default = null;
    
            if ($type instanceof \ReflectionNamedType) {
                $typeName = $type->getName();
    
                if ($type->isBuiltin()) {
                    $default = match ($typeName) {
                        'int' => 0,
                        'float' => 0.0,
                        'string' => '',
                        'bool' => false, // or false — change this based on your preference
                        'array' => [], // or false — change this based on your preference
                        default => null
                    };
                } elseif (class_exists($typeName)) {
                    try {
                        $default = new $typeName();
                    } catch (\Throwable) {
                        // leave null
                    }
                }
            }
    
            $property->setValue($this, $default);
        }
        return $this;
    }
    private function initializeFromPositionalArray(array $args): void
    {
        $properties = array_keys(get_class_vars(get_called_class()));

        foreach ($args as $i => $value) {
            $key = $properties[$i] ?? null;
            if (!$key) continue;

            $setter = "set" . ucfirst($key);

            try {
                if (method_exists($this, $setter)) {
                    $this->$setter($value);
                } elseif (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            } catch (\Throwable) {
                // Silent fail for safety
            }
        }
    }
    private function initializeFromNamedArray(array $data): void
    {
        foreach ($data as $key => $value) {
            $setter = "set" . ucfirst($key);

            try {
                if (method_exists($this, $setter)) {
                    $this->$setter($value);
                } elseif (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            } catch (\Throwable) {
                // Silent fail for safety
            }
        }
    }
    private function isAssociativeArray($value): bool
    {
        return is_array($value) && count(array_filter(array_keys($value), 'is_string')) > 0;
    }


}

namespace DafCore\AutoConstruct;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Ignore { }
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Name { 
    function __construct(public string $value = ""){}
}