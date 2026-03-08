<?php 
namespace DafCore;

use DafCore\AutoConstruct\Name;
use DafCore\AutoConstruct\Ignore;

class AutoConstruct implements \JsonSerializable
{
    private static array $meta = []; // [class => [prop => meta]]

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
        } else if ($numberOfArguments < 1) { /** do nothing */ } 
        else if ($this->isAssociativeArray($arguments[0] ?? null)) {
            $this->initializeFromNamedArray($arguments[0]);
        } else {
            $this->initializeFromPositionalArray($arguments);
        }
        
        $this->onAfterLoad();
    }

    public function onLoad(){}
    public function onAfterLoad(){}


    public static function metaFor(object $obj): array
    {
        $class = $obj::class;

        if (!isset(self::$meta[$class])) {
            $r = new \ReflectionClass($obj);
            $map = [];
            $aliases = [];

            foreach ($r->getProperties(\ReflectionProperty::IS_PUBLIC) as $p) {
                
                $type = $p->getType();
                $typeName = null;
                $builtin = false;
                $nullable = true;

                if ($type instanceof \ReflectionNamedType) {
                    $typeName = $type->getName();
                    $builtin = $type->isBuiltin();
                    $nullable = $type->allowsNull();
                } elseif ($type instanceof \ReflectionUnionType) {
                    $nullable = false;
                    foreach ($type->getTypes() as $t) {
                        if ($t instanceof \ReflectionNamedType) {
                            if ($t->getName() === 'null') {
                                $nullable = true;
                                continue;
                            }

                            // Prefer class type for hydration
                            $name = $t->getName();
                            if (!$t->isBuiltin() && class_exists($name) && is_subclass_of($name, self::class)) {
                                $typeName = $name;
                                $builtin = false;
                                break;
                            }

                            // fallback to first builtin
                            if ($typeName === null) {
                                $typeName = $t->getName();
                                $builtin = $t->isBuiltin();
                            }
                        }
                    }
                }


                $ignore = false;
                $jsonName = $p->getName();
                $propName = $p->getName();
                $displayName = "";
                $validationRules = [];

                foreach ($p->getAttributes() as $a) {
                    $an = $a->getName();

                    if ($an === Ignore::class) {
                        $ignore = true;
                        break;
                    }

                    if ($an === Name::class) {
                        $jsonName = $a->newInstance()->value ?: $jsonName;
                        continue;
                    }

                    if ($an === \DafCore\Attributes\DisplayName::class) {
                        $displayName = $a->newInstance()->Text ?? "";
                        continue;
                    }

                    if (is_subclass_of($an, \DafCore\Attributes\ValidationAttribute::class)) {
                        $instance = $a->newInstance();
                        $rule = $instance->ToClientRule($propName, $displayName);
                        if ($rule !== null) $validationRules[] = $rule;
                        continue;
                    }

                }


                $aliases[$propName] = $propName;
                if (!isset($aliases[$jsonName])) $aliases[$jsonName] = $propName;

                $map[$propName] = [
                    'p' => $p,
                    'type' => $typeName,
                    'builtin' => $builtin,
                    'ignore' => $ignore,
                    'json' => $jsonName,
                    'nullable' => $nullable,
                    'display' => !empty($displayName) ? $displayName : $propName,
                    'validators' => $validationRules,
                ];

            }

            self::$meta[$class] = ['props' => $map, 'aliases' => $aliases];
        }

        return self::$meta[$class];
    }

    public function jsonSerialize(): mixed
    {
        $meta = self::metaFor($this);
        $map = $meta['props'];

        $data = [];
        foreach ($map as $prop => $m) {
            if ($m['ignore']) continue;

            $p = $m['p'];
            if ($p->isInitialized($this)) {
                $data[$m['json']] = $p->getValue($this);
            }
        }
        return $data;
    }


    function initializeProperties(): self
    {
        $meta = self::metaFor($this);
        $map = $meta['props'];

        foreach ($map as $m) {
            /** @var \ReflectionProperty $p */
            $p = $m['p'];

            if ($p->isInitialized($this)) continue;

            $typeName = $m['type'];
            $default = null;

            if ($m['nullable']) {
                $default = null;
            } else if ($m['builtin']) {
                $default = match ($typeName) {
                    'int' => 0,
                    'float' => 0.0,
                    'string' => '',
                    'bool' => false,
                    'array' => [],
                    default => null
                };
            } else if ($typeName && is_subclass_of($typeName, self::class)) {
                try { $default = new $typeName(); } catch (\Throwable) {}
            }

            try { $p->setValue($this, $default); } catch (\Throwable) {}
        }

        return $this;
    }

    private function initializeFromNamedArray(array $data): void
    {
        $meta = self::metaFor($this);
        $map = $meta['props'];
        $aliases = $meta['aliases'];

        foreach ($data as $key => $value) {
            $propKey = $aliases[$key] ?? null;
            if ($propKey === null) continue;

            $setter = 'set' . ucfirst($propKey);

            try {
                if (method_exists($this, $setter)) {
                    $this->$setter($value);
                    continue;
                }

                $m = $map[$propKey]; // safe because propKey came from aliases
                $p = $m['p'];
                $typeName = $m['type'];

                if (is_array($value) && $typeName && class_exists($typeName)
                    && is_subclass_of($typeName, self::class)) {
                    $p->setValue($this, new $typeName($value));
                } else {
                    $p->setValue($this, $value);
                }
            } catch (\Throwable) {}
        }
    }


    private function initializeFromPositionalArray(array $args): void
    {

        $meta = self::metaFor($this);
        $map = $meta['props'];

        $props = array_keys($map); // public props in reflection order

        foreach ($args as $i => $value) {
            $key = $props[$i] ?? null;
            if (!$key) continue;

            $setter = 'set' . ucfirst($key);

            try {
                if (method_exists($this, $setter)) {
                    $this->$setter($value);
                    continue;
                }

                $m = $map[$key];
                $p = $m['p'];
                $typeName = $m['type'];

                if (is_array($value) && $typeName && class_exists($typeName) && is_subclass_of($typeName, self::class)) {
                    $p->setValue($this, new $typeName($value));
                } else {
                    $p->setValue($this, $value);
                }
            } catch (\Throwable) {}
        }
    }

    private function isAssociativeArray($value): bool
    {
        return is_array($value) && array_keys($value) !== range(0, count($value) - 1);
    }
}

namespace DafCore\AutoConstruct;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Ignore { }
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Name { 
    function __construct(public string $value = ""){}
}


