<?php
namespace DafDb\Context;

use DafDb\Migrations\SnapshotBuilder;
use DafDb\Migrations\Mapper\AttributeToBuilderMapper;

class DbContext
{
    private static array $dbSetsPropsCache = [];
    private AttributeToBuilderMapper|null $snapshotMapper = null;

    public function __construct(public Context $context)
    {
        $this->snapshotMapper = new AttributeToBuilderMapper();
        $this->initDbSets();
    }

    public function ModelSnapshot(SnapshotBuilder $builder): SnapshotBuilder | null {
        return null;
    }

    public function getModelSnapshot(): array
    {
        $manualSnapshot = $this->ModelSnapshot(new SnapshotBuilder());
        if($manualSnapshot !== null){
            $tables = $manualSnapshot->GetTables();
            ksort($tables);
            return $tables;
        }

        $result = [];
        $props = self::getDbSetProperties(static::class);

        foreach ($props as $prop) {
            if (!$prop->isInitialized($this)) continue;

            $dbSet = $prop->getValue($this);
            if (!$dbSet instanceof \DafDb\Query\DbSet) continue;

            $tableInfo = $dbSet->GetTableInfo();
            $tName = $tableInfo->Name;
            $mName = $tableInfo->ModelClass;

            $result[$tName] = $this->snapshotMapper->BuildTable($tName, $mName);
        }
        //ksort($result);

        return $result;
    }

    public function SaveChanges(): void {
        $this->context->SaveChanges();
    }


    private function initDbSets(): void
    {
        $cls = static::class;
        $props = self::getDbSetProperties($cls);

        foreach ($props as $prop) {
            if ($prop->isInitialized($this)) continue;

            $type = $prop->getType();
            if (!$type instanceof \ReflectionNamedType) continue;

            $dbSet = $type->getName();

            $prop->setValue($this, new $dbSet($this->context));
        }
    }

    /** @return \ReflectionProperty[] */
    private static function getDbSetProperties(string $cls): array
    {
        return self::$dbSetsPropsCache[$cls] ??= (function () use ($cls) {
            $r = new \ReflectionClass($cls);
            $out = [];

            foreach ($r->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
                if ($prop->getDeclaringClass()->getName() === DbContext::class) continue;

                $type = $prop->getType();
                if (!$type instanceof \ReflectionNamedType) continue;

                $dbSetClass = $type->getName();
                if (!is_a($dbSetClass, \DafDb\Query\DbSet::class, true)) continue;

                $out[] = $prop;
            }

            return $out;
        })();
    }
}
