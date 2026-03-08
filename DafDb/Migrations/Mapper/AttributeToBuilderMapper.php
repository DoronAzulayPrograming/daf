<?php
namespace DafDb\Migrations\Mapper;

use DafGlobals\Dates\IDate;
use DafGlobals\Dates\DateOnly;
use DafDb\Migrations\Builders\TableBuilder;

final class AttributeToBuilderMapper
{
    /**
     * Builds a TableBuilder schema plan from a model class + your attributes.
     * Returns a TableBuilder (schema plan) - not SQL.
     */
    public function BuildTable(string $tableName, string $modelClass): TableBuilder
    {
        $r = new \ReflectionClass($modelClass);
        $defaults = $r->getDefaultProperties(); // property defaults without instantiation

        $t = new TableBuilder($tableName);

        $primaryKeys = [];

        foreach ($r->getProperties(\ReflectionProperty::IS_PUBLIC) as $p) {
            // DbIgnore
            if ($p->getAttributes(\DafDb\Attributes\DbIgnore::class)) {
                continue;
            }

            $name = $p->getName();

            $type = $p->getType();
            if (!$type instanceof \ReflectionNamedType) {
                continue;
            }

            $typeName = $type->getName();
            $isBuiltIn = $type->isBuiltin();
            $isDate = is_a($typeName, IDate::class, true);

            if (!$isBuiltIn && !$isDate) {
                continue;
            }

            if ($isDate) {
                $col = $typeName === DateOnly::class
                    ? $t->Date($name)    
                    : $t->DateTime($name);
            } else {
                $col = match ($typeName) {
                    'int'    => $t->Int($name),
                    'string' => $t->String($name),
                    'float'  => $t->Float($name),
                    'bool'   => $t->Bool($name),
                    default  => null,
                };
            }

            if (!$col) continue;

            $defaultAttr = $p->getAttributes(\DafDb\Attributes\DefaultValue::class)[0] ?? null;
            $defaultSqlAttr = $p->getAttributes(\DafDb\Attributes\DefaultValueSql::class)[0] ?? null;

            if ($defaultAttr) {
                $value = $defaultAttr->newInstance()->Value;
                $col->Default($value);
            } elseif ($defaultSqlAttr) {
                $expr = $defaultSqlAttr->newInstance()->Sql;
                $col->DefaultSql($expr);
            } elseif (array_key_exists($name, $defaults)) {
                // default from property initializer
                $v = $defaults[$name];
                if ($v instanceof IDate) {
                    $col->Default((string)$v);
                } elseif ($v !== null) {
                    $col->Default($v);
                }
            }

            $col->Nullable($type->allowsNull());

            $maxLength = $p->getAttributes(\DafDb\Attributes\MaxLength::class);
            if ($maxLength) {
                $args = $maxLength[0]->getArguments();
                $value = $args[0] ?? $args['Value'] ?? $args['value'];
                $col->MaxLength($value);
            }

            if ($p->getAttributes(\DafDb\Attributes\AutoIncrement::class)) {
                $col->AutoIncrement();
            }

            if ($p->getAttributes(\DafDb\Attributes\Unique::class)) {
                $col->Unique();
            }

            if ($p->getAttributes(\DafDb\Attributes\PrimaryKey::class)) {
                $primaryKeys[] = $name;
            }

            $fk = $p->getAttributes(\DafDb\Attributes\ForeignKey::class);
            if ($fk) {
                $args = $fk[0]->getArguments();
                $refTable  = $args[0] ?? $args['Table'];
                $refColumn = $args[1] ?? $args['Column'];
                $onDelete  = $args[2] ?? $args['OnDelete'] ?? null;

                $t->Constraints->ForeignKey($name, $refTable, $refColumn, $onDelete);

                // ⭐ Recommended default: FK columns get an index automatically
                //$t->Index("IX_{$tableName}_{$name}", [$name], false);
            }
        }

        if (!empty($primaryKeys)) {
            $t->Constraints->PrimaryKey($primaryKeys);
        }

        // ⭐ Optional: unique indexes from unique columns (instead of column-level UNIQUE)
        // If you prefer unique indexes (more consistent), you can also generate:
        // foreach ($t->columns as $col) if ($col->unique) $t->index("UX_{$tableName}_{$col->name}", [$col->name], true);

        return $t;
    }
}
