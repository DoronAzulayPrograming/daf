<?php
namespace DafGlobals\Mapper;

final class ObjectMapper
{
    /**
     * @param object $source
     * @param object $target
     * @param array<string,string|callable(mixed,object,object):mixed|array{target?:string,map?:callable(mixed,object,object):mixed,useGetter?:bool,useSetter?:bool}> $mapping
     */
    public static function Map(object $source, object $target, array $mapping = []): object
    {
        $sourceRef = new \ReflectionObject($source);
        $targetRef = new \ReflectionObject($target);

        foreach ($sourceRef->getProperties() as $prop) {
            $name = $prop->getName();

            // mapping: rename/transform + flags
            [$targetName, $transform, $useGetter, $useSetter] = self::resolveMapping($name, $mapping);

            // read
            if (!self::canReadSource($sourceRef, $source, $prop, $name, $useGetter)) {
                continue;
            }
            $value = self::readSourceValue($sourceRef, $source, $prop, $name, $useGetter);

            // transform
            if ($transform !== null) {
                $value = $transform($value, $source, $target);
            }

            // write
            self::writeTargetValue($targetRef, $target, $targetName, $value, $useSetter);
        }

        return $target;
    }

    /**
     * @param array<string,string|callable(mixed,object,object):mixed|array{target?:string,map?:callable(mixed,object,object):mixed,useGetter?:bool,useSetter?:bool}> $mapping
     * @return array{0:string,1:(callable(mixed,object,object):mixed|null),2:bool,3:bool}
     */
    private static function resolveMapping(string $property, array $mapping): array
    {
        $targetProperty = $property;
        $transform = null;

        // defaults per your request:
        $useGetter = false;
        $useSetter = false;

        if (!array_key_exists($property, $mapping)) {
            return [$targetProperty, $transform, $useGetter, $useSetter];
        }

        $definition = $mapping[$property];

        if (is_string($definition)) {
            $targetProperty = $definition;
        } elseif (is_callable($definition)) {
            $transform = $definition;
        } elseif (is_array($definition)) {
            if (isset($definition['target']) && is_string($definition['target'])) {
                $targetProperty = $definition['target'];
            }
            if (isset($definition['map']) && is_callable($definition['map'])) {
                $transform = $definition['map'];
            }
            if (isset($definition['useGetter'])) {
                $useGetter = (bool)$definition['useGetter'];
            }
            if (isset($definition['useSetter'])) {
                $useSetter = (bool)$definition['useSetter'];
            }
        }

        return [$targetProperty, $transform, $useGetter, $useSetter];
    }

    private static function getterNames(string $prop): array
    {
        $u = ucfirst($prop);
        return ["get{$u}", "is{$u}", "has{$u}"];
    }

    private static function setterName(string $prop): string
    {
        return "set" . ucfirst($prop);
    }

    private static function canReadSource(
        \ReflectionObject $sourceRef,
        object $source,
        \ReflectionProperty $prop,
        string $name,
        bool $useGetter
    ): bool {
        // prefer getter (when enabled)
        if ($useGetter) {
            foreach (self::getterNames($name) as $getter) {
                if ($sourceRef->hasMethod($getter)) {
                    $m = $sourceRef->getMethod($getter);
                    if ($m->isPublic() && $m->getNumberOfRequiredParameters() === 0) {
                        return true;
                    }
                }
            }
            // fallback to property (guard uninitialized)
            $prop->setAccessible(true);
            return $prop->isInitialized($source);
        }

        // default: prefer property first
        $prop->setAccessible(true);
        if ($prop->isInitialized($source)) {
            return true;
        }

        // fallback to getter
        foreach (self::getterNames($name) as $getter) {
            if ($sourceRef->hasMethod($getter)) {
                $m = $sourceRef->getMethod($getter);
                if ($m->isPublic() && $m->getNumberOfRequiredParameters() === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function readSourceValue(
        \ReflectionObject $sourceRef,
        object $source,
        \ReflectionProperty $prop,
        string $name,
        bool $useGetter
    ): mixed {
        if ($useGetter) {
            foreach (self::getterNames($name) as $getter) {
                if ($sourceRef->hasMethod($getter)) {
                    $m = $sourceRef->getMethod($getter);
                    if ($m->isPublic() && $m->getNumberOfRequiredParameters() === 0) {
                        return $source->$getter();
                    }
                }
            }
            $prop->setAccessible(true);
            return $prop->getValue($source);
        }

        // default: property first
        $prop->setAccessible(true);
        if ($prop->isInitialized($source)) {
            return $prop->getValue($source);
        }

        // fallback: getter
        foreach (self::getterNames($name) as $getter) {
            if ($sourceRef->hasMethod($getter)) {
                $m = $sourceRef->getMethod($getter);
                if ($m->isPublic() && $m->getNumberOfRequiredParameters() === 0) {
                    return $source->$getter();
                }
            }
        }

        // should not happen if canReadSource() was used
        return null;
    }

    private static function writeTargetValue(
        \ReflectionObject $targetRef,
        object $target,
        string $targetName,
        mixed $value,
        bool $useSetter
    ): void {
        // prefer setter (when enabled)
        if ($useSetter) {
            $setter = self::setterName($targetName);
            if ($targetRef->hasMethod($setter)) {
                $m = $targetRef->getMethod($setter);
                if ($m->isPublic() && $m->getNumberOfRequiredParameters() <= 1) {
                    $target->$setter($value);
                    return;
                }
            }
            // fallback to property
            if ($targetRef->hasProperty($targetName)) {
                $p = $targetRef->getProperty($targetName);
                $p->setAccessible(true);
                $p->setValue($target, $value);
            }
            return;
        }

        // default: property first
        if ($targetRef->hasProperty($targetName)) {
            $p = $targetRef->getProperty($targetName);
            $p->setAccessible(true);
            $p->setValue($target, $value);
            return;
        }

        // fallback: setter
        $setter = self::setterName($targetName);
        if ($targetRef->hasMethod($setter)) {
            $m = $targetRef->getMethod($setter);
            if ($m->isPublic() && $m->getNumberOfRequiredParameters() <= 1) {
                $target->$setter($value);
            }
        }
    }
}
