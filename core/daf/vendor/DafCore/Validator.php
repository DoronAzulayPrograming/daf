<?php
namespace DafCore;

class Validator{

    public static array $errorMsgs = [];

    public static function Validate($object, $deep = false): bool {
        list($isValid, $em) = self::validateObject($object, '', $deep);
        self::$errorMsgs = $em;
        return $isValid;
    }

    private static function validateObject($object, $path = '', $deep = false): array {
        $reflector = new \ReflectionClass($object);
        $properties = $reflector->getProperties();
        $isValid = true;
        $errorMsgs = [];
    
        foreach ($properties as $property) {
            $isInit = $property->isInitialized($object);
            $attributes = $property->getAttributes();
            $displayName = "";
            foreach ($attributes as $attribute) {
                $an = $attribute->getName();
                if (!is_subclass_of($an, Attributes\ValidationAttribute::class)) 
                    if ($an !== Attributes\DisplayName::class) continue;
                    else {
                        $displayName = $attribute->newInstance()->Text;
                        continue;
                    }

                $instance = $attribute->newInstance();
                $value = $isInit ? $property->getValue($object) : null;
                $propertyPath = $path ? "$path.{$property->getName()}" : $property->getName();
                
                if (is_object($value)) {
                    list($isPropValid, $propErrorMsgs) = self::validateObject($value, $propertyPath, $deep);
                    $isValid = $isValid && $isPropValid;
                    $errorMsgs = array_merge($errorMsgs, $propErrorMsgs);
                } else{
                    $isPropValid = $instance->Validate($property->getName(), $value, $displayName);
                    if (!$isPropValid) {
                        //$errorMsgs[] = $instance->msg;
                        $errorMsgs[] = [
                            'field'=>$propertyPath,
                            'msg'=>$instance->Msg
                        ];
                        $isValid = false;
                    }
                }

                if($deep === false && $isValid === false) break;
            }
        }
    
        return [$isValid, $errorMsgs];
    }
}
