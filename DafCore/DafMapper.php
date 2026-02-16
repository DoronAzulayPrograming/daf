<?php
namespace DafCore;

final class DafMapper
{
    /**
     * @param object $source
     * @param object $target
     * @param array<string,string|callable(mixed,object,object):mixed|array{target?:string,map?:callable(mixed,object,object):mixed,useGetter?:bool,useSetter?:bool}> $mapping
     */
    public static function Map(object $source, object $target, array $mapping = []): object
    {
        $srcMeta = AutoConstruct::metaFor($source); // ['props'=>..., 'aliases'=>...]
        $dstMeta = AutoConstruct::metaFor($target);

        $srcProps   = $srcMeta['props'];
        $dstProps   = $dstMeta['props'];
        $dstAliases = $dstMeta['aliases'];

        // Normalize mapping keys -> real source property names (so mapping can use json alias too)
        $mapBySrcName = [];
        foreach ($mapping as $fromKey => $def) {
            if (!is_string($fromKey)) continue;

            $srcName = self::resolveSourcePropName($fromKey, $srcProps);
            if ($srcName === null) continue;

            $mapBySrcName[$srcName] = $def;
        }

        // Walk source props in original order
        foreach ($srcProps as $srcName => $m) {
            if (!empty($m['ignore'])) continue;

            // 1) If mapping exists for this src prop -> use it
            if (array_key_exists($srcName, $mapBySrcName)) {
                self::mapOneResolvedSource(
                    $source,
                    $target,
                    $srcName,
                    $mapBySrcName[$srcName],
                    $srcProps,
                    $dstProps,
                    $dstAliases
                );
                continue;
            }

            // 2) Otherwise auto-map (same as your existing auto-map)
            $candidateTargets = [$srcName, $m['json'] ?? $srcName];

            foreach ($candidateTargets as $cand) {
                $resolved = self::resolveTargetPropName((string)$cand, $dstAliases);
                if ($resolved === null) continue;

                $value = self::readSource($source, $srcName, $srcProps, false);
                if ($value === self::SKIP) break;

                self::writeTarget($target, $resolved, $value, $dstProps, false);
                break;
            }
        }

        return $target;
    }


    // ---------------- Internals ----------------

    private const SKIP = "__DAF_MAPPER_SKIP__";

    private static function mapOneResolvedSource(
        object $source,
        object $target,
        string $srcName,
        mixed $definition,
        array $srcProps,
        array $dstProps,
        array $dstAliases
    ): void {
        if (!isset($srcProps[$srcName])) return;
        if (!empty($srcProps[$srcName]['ignore'])) return;

        [$toKey, $transform, $useGetter, $useSetter] =
            self::resolveDefinition($srcName, $definition);

        $value = self::readSource($source, $srcName, $srcProps, $useGetter);
        if ($value === self::SKIP) return;

        if ($transform) {
            $value = $transform($value, $source, $target);
        }

        $dstName = self::resolveTargetPropName($toKey, $dstAliases);
        if ($dstName === null) return;

        self::writeTarget($target, $dstName, $value, $dstProps, $useSetter);
    }


    private static function mapOne(
        object $source,
        object $target,
        string $fromKey,
        mixed $definition,
        array $srcProps,
        array $dstProps,
        array $dstAliases
    ): void {
        [$toKey, $transform, $useGetter, $useSetter] = self::resolveDefinition($fromKey, $definition);

        // Resolve source prop name (allow mapping keys to be json/name aliases too)
        $srcName = self::resolveSourcePropName($fromKey, $srcProps);
        if ($srcName === null) return;

        if (!empty($srcProps[$srcName]['ignore'])) return;

        $value = self::readSource($source, $srcName, $srcProps, $useGetter);
        if ($value === self::SKIP) return;

        if ($transform) {
            $value = $transform($value, $source, $target);
        }

        // Resolve target prop name using target aliases (allows json/name keys)
        $dstName = self::resolveTargetPropName($toKey, $dstAliases);
        if ($dstName === null) return;

        self::writeTarget($target, $dstName, $value, $dstProps, $useSetter);
    }

    /**
     * @return array{0:string,1:(callable|null),2:bool,3:bool}
     */
    private static function resolveDefinition(string $fromKey, mixed $definition): array
    {
        $target = $fromKey;
        $map = null;
        $useGetter = false;
        $useSetter = false;

        if (is_string($definition)) {
            $target = $definition;
        } elseif (is_callable($definition)) {
            $map = $definition;
        } elseif (is_array($definition)) {
            if (isset($definition['target']) && is_string($definition['target'])) $target = $definition['target'];
            if (isset($definition['map']) && is_callable($definition['map'])) $map = $definition['map'];
            if (isset($definition['useGetter'])) $useGetter = (bool)$definition['useGetter'];
            if (isset($definition['useSetter'])) $useSetter = (bool)$definition['useSetter'];
        }

        return [$target, $map, $useGetter, $useSetter];
    }

    private static function resolveSourcePropName(string $key, array $srcProps): ?string
    {
        // direct property name
        if (isset($srcProps[$key])) return $key;

        // try match by json alias
        foreach ($srcProps as $propName => $m) {
            if (($m['json'] ?? null) === $key) return $propName;
        }

        return null;
    }

    private static function resolveTargetPropName(string $key, array $dstAliases): ?string
    {
        // aliases already includes propName->propName and jsonName->propName
        return $dstAliases[$key] ?? null;
    }

    private static function getterCandidates(string $prop): array
    {
        $u = ucfirst($prop);
        return ["get{$u}", "is{$u}", "has{$u}"];
    }

    private static function setterName(string $prop): string
    {
        return "set" . ucfirst($prop);
    }

    private static function readSource(object $source, string $propName, array $srcProps, bool $useGetter): mixed
    {
        if (!isset($srcProps[$propName])) return self::SKIP;

        $rp = $srcProps[$propName]['p']; // ReflectionProperty (public)

        // Prefer getter
        if ($useGetter) {
            foreach (self::getterCandidates($propName) as $g) {
                if (method_exists($source, $g)) {
                    return $source->$g();
                }
            }
            // fallback to prop, but only if initialized
            if (method_exists($rp, 'isInitialized') && !$rp->isInitialized($source)) {
                return self::SKIP;
            }
            try {
                return $source->$propName;
            } catch (\Throwable) {
                return self::SKIP;
            }
        }

        // Default: prefer prop (safe init check)
        if (method_exists($rp, 'isInitialized') && !$rp->isInitialized($source)) {
            // fallback to getter
            foreach (self::getterCandidates($propName) as $g) {
                if (method_exists($source, $g)) {
                    return $source->$g();
                }
            }
            return self::SKIP;
        }

        try {
            return $source->$propName;
        } catch (\Throwable) {
            // fallback to getter
            foreach (self::getterCandidates($propName) as $g) {
                if (method_exists($source, $g)) {
                    return $source->$g();
                }
            }
            return self::SKIP;
        }
    }

    private static function writeTarget(object $target, string $propName, mixed $value, array $dstProps, bool $useSetter): void
    {
        // Prefer setter
        if ($useSetter) {
            $setter = self::setterName($propName);
            if (method_exists($target, $setter)) {
                try {
                    $target->$setter($value);
                    return;
                } catch (\Throwable) {
                    return;
                }
            }
            // fallback to prop
        }

        if (!isset($dstProps[$propName])) {
            // fallback to setter if property not present
            $setter = self::setterName($propName);
            if (method_exists($target, $setter)) {
                try { $target->$setter($value); } catch (\Throwable) {}
            }
            return;
        }

        // public prop set (still can TypeError if incompatible)
        try {
            $target->$propName = $value;
        } catch (\Throwable) {
            // fallback to setter
            $setter = self::setterName($propName);
            if (method_exists($target, $setter)) {
                try { $target->$setter($value); } catch (\Throwable) {}
            }
        }
    }
}
