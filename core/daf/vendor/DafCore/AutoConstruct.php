<?php 
namespace DafCore;

use DafCore\AutoConstruct\Name;
use DafCore\AutoConstruct\Ignore;

class AutoConstruct implements \JsonSerializable
{
    public function __construct()
    {
        $arguments = func_get_args();
        $numberOfArguments = func_num_args();

        $constructor = method_exists(
            $this,
            $fn = "__construct" . $numberOfArguments
        );

        $this->onLoad();

        if ($constructor) {
            call_user_func_array([$this, $fn], $arguments);
        } else {

            if ($numberOfArguments < 1){
                $this->onAfterLoad();
                return;
            }

            // Check if the first argument is an associative array for named initialization
            if (isset($arguments[0]) && is_array($arguments[0]) && count(array_filter(array_keys($arguments[0]), 'is_string')) > 0) {
                
                foreach ($arguments[0] as $key => $value) {
                    // Use setter method if available, otherwise assign directly
                    $setter = "set" . ucfirst($key);
                   try {
                        if (method_exists($this, $setter)) {
                            $this->$setter($value);
                        } elseif (property_exists($this, $key)) {
                            $this->$key = $value;
                        }
                   } catch (\Throwable $th) {
                    //throw $th;
                   }
                }
            } else {
                // If positional arguments, match them to properties in the order defined in the class
                $properties = array_keys(get_class_vars(get_called_class()));
                
                foreach ($arguments as $index => $value) {
                    try {
                        if (isset($properties[$index])) {
                            $key = $properties[$index];
                            $setter = "set" . ucfirst($key);
                            if (method_exists($this, $setter)) {
                                $this->$setter($value);
                            } elseif (property_exists($this, $key)) {
                                $this->$key = $value;
                            }
                        }
                    } catch (\Throwable $th) {
                        //throw $th;
                    }
                }
            }
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
}

namespace DafCore\AutoConstruct;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Ignore { }
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Name { 
    function __construct(public string $value = ""){}
}