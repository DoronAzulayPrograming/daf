<?php
namespace DafDb;

use DafDb\Migrations\Mapper\AttributeToBuilderMapper;

class DbContext
{
    private static array $repoPropsCache = [];
    private static ?AttributeToBuilderMapper $snapshotMapper = null;

    public function __construct(public Context $context)
    {
        $this->context->UseMigrations = true;
        $this->initRepositories();
    }

    /** @return \ReflectionProperty[] */
    private static function getRepoProperties(string $cls): array
    {
        return self::$repoPropsCache[$cls] ??= (function () use ($cls) {
            $r = new \ReflectionClass($cls);
            $out = [];

            foreach ($r->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
                if ($prop->getDeclaringClass()->getName() === \DafDb\DbContext::class) continue;

                $type = $prop->getType();
                if (!$type instanceof \ReflectionNamedType) continue;

                $repoClass = $type->getName();
                if (!is_a($repoClass, \DafDb\Repository::class, true)) continue;

                $out[] = $prop;
            }

            return $out;
        })();
    }

    protected function initRepositories(): void
    {
        $cls = static::class;
        $props = self::getRepoProperties($cls);

        foreach ($props as $prop) {
            if ($prop->isInitialized($this)) continue;

            $type = $prop->getType();
            if (!$type instanceof \ReflectionNamedType) continue;

            $repoClass = $type->getName();

            $prop->setValue($this, new $repoClass($this->context));
        }
    }

    public function getModelSnapshot(): array
    {
        $mapper = self::$snapshotMapper ??= new AttributeToBuilderMapper();

        $result = [];
        $props = self::getRepoProperties(static::class);

        foreach ($props as $prop) {
            if (!$prop->isInitialized($this)) continue;

            $repo = $prop->getValue($this);
            if (!$repo instanceof \DafDb\Repository) continue;

            $tName = $repo->GetTableName();
            $mName = $repo->GetModelClass();

            $result[$tName] = $mapper->BuildTable($tName, $mName);
        }
        ksort($result);
        return $result;
    }

    public function SaveChanges(){
        $this->context->SaveChanges();
    }
}
