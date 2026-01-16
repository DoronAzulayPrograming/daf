<?php
namespace DafDb\Migrations\Providers;

use DafDb\Context;
use DafDb\Migrations\Builders\TableBuilder;
use DafDb\Migrations\Builders\ColumnBuilder;
use DafDb\Migrations\Builders\AlterTableBuilder;
use DafDb\Migrations\Builders\Definitions\IndexDef;
use DafDb\Migrations\Services\ISqlExpression;
use DafDb\Migrations\Services\SqlExpression;

final class SqliteProviderSql implements IProviderSql
{
    use SqlHelpersTrait;
    private string $providerId = 'sqlite';

    public function CompileAlterTable(AlterTableBuilder $alter, ?array $oldTable = null): array
    {
        $sqls = [];

        // decide rebuild (everything except rename-only + pure index ops requires rebuild)
        $needsRebuild =
            !empty($alter->AddColumns) ||
            !empty($alter->DropColumns) ||
            !empty($alter->ModifyColumns) ||
            !empty($alter->AddForeignKeys) ||
            !empty($alter->DropForeignKeys) ||
            $alter->PrimaryKey !== null ||
            $alter->PrimaryKeyNeedToDrop;

        if (!$needsRebuild) {

            $alteredName = $alter->TableName;

            if ($alter->RenameTo !== null) {
                $sqls[] = "ALTER TABLE " . $this->q($alter->TableName) . " RENAME TO " . $this->q($alter->RenameTo) . ";";
                $alteredName = $alter->RenameTo;
            }
            foreach ($alter->RenameColumns as $old => $new) {
                $sqls[] = "ALTER TABLE " . $this->q($alteredName) . " RENAME COLUMN " . $this->q($old) . " TO " . $this->q($new) . ";";
            }

            foreach ($alter->AddIndexes as $idx) {
                $sqls = array_merge($sqls, $this->compileCreateIndex($idx));
            }
            foreach ($alter->DropIndexes as $idxName) {
                $sqls[] = "DROP INDEX IF EXISTS " . $this->q($idxName) . ";";
            }
            foreach ($alter->RenameIndexes as $old => $new) {
                $sqls[] = "ALTER INDEX " . $this->q($old) . " RENAME TO " . $this->q($new) . ";";
            }

            return $sqls;
        }

        if (!$oldTable) {
            $sqls[] = "-- TODO: SQLite old schema missing table `{$alter->TableName}`";
            return $sqls;
        }

        // apply operations to compute target schema
        $newTable = $this->applyAlterToSchema($oldTable, $alter);

        $oldName = $alter->TableName;
        $newName = $alter->RenameTo ?? $alter->TableName;

        return array_merge($sqls, $this->compileRebuildTableFromSchemas(
            $oldName,
            $newName,
            $oldTable,
            $newTable,
            $alter
        ));
    }

    public function CompileDropTable(string $tableName): array
    {
        return ["DROP TABLE IF EXISTS " . $this->q($tableName) . ";"];
    }

    public function CompileCreateTable(TableBuilder $t): array
    {
        $parts = [];
        foreach ($t->Columns as $col) {
            $parts[] = $this->compileColumn($t, $col);
        }

        // composite PK only (single PK is handled inline for AUTOINCREMENT compatibility)
        $pk = $t->Constraints->primaryKey;
        if ($pk && count($pk->Columns) > 1) {
            $cols = implode(',', array_map(fn($c) => $this->q($c), $pk->Columns));
            $parts[] = "PRIMARY KEY ({$cols})";
        }

        // foreign keys
        foreach ($t->Constraints->ForeignKeys as $fk) {
            $cols = implode(',', array_map(fn($c) => $this->q($c), $fk->Columns));
            $refCols = implode(',', array_map(fn($c) => $this->q($c), $fk->RefColumns));

            $sql = "FOREIGN KEY ({$cols}) REFERENCES " . $this->q($fk->RefTable) . "({$refCols})";
            if ($fk->OnDelete)
                $sql .= " ON DELETE {$fk->OnDelete}";
            $parts[] = $sql;
        }

        $sqls = [];
        $sqls[] = "CREATE TABLE IF NOT EXISTS " . $this->q($t->Name) . " (" . implode(',', $parts) . ");";

        // indexes after create table
        foreach ($t->Indexes as $idx) {
            $sqls = array_merge($sqls, $this->compileCreateIndex($idx));
        }

        // EF-style: Unique columns => unique indexes with deterministic names
        foreach ($t->Columns as $col) {
            if (!$col->Unique)
                continue;

            $idxName = "UX_{$t->Name}_{$col->Name}";
            $def = new IndexDef($idxName, $t->Name, [$col->Name], true);
            $sqls = array_merge($sqls, $this->compileCreateIndex($def));
        }

        // EF-style: Unique constraints => unique indexes (named)
        foreach ($t->Constraints->Uniques as $uq) {
            $def = new IndexDef($uq->Name, $t->Name, $uq->Columns, true);
            $sqls = array_merge($sqls, $this->compileCreateIndex($def));
        }

        return $sqls;
    }


    

    private function compileRebuildTableFromSchemas(string $oldName, string $newName, array $oldTable, array $newTable, AlterTableBuilder $alter): array
    {
        $tmpName = "__daf_tmp_{$oldName}_" . substr(md5(uniqid('', true)), 0, 8);

        $oldQ = $this->q($oldName);
        $newQ = $this->q($newName);
        $tmpQ = $this->q($tmpName);

        // 1) create the NEW table under the new name
        $createSql = $this->compileCreateTableFromSchemaArray($newName, $newTable);

        // 2) build safe copy plan (renames + defaults + NULL fill + NOT NULL protection)
        // returns: ['insertCols' => [...], 'selectExprs' => [...]]
        $copy = $this->buildCopyPlan($oldTable, $newTable, $alter->RenameColumns);

        $insertSql = "-- No columns to copy for {$newQ}";
        if (!empty($copy['insertCols'])) {
            $insertSql =
                "INSERT INTO {$newQ} (" . $this->joinCols($copy['insertCols']) . ") " .
                "SELECT " . implode(', ', $copy['selectExprs']) . " FROM {$tmpQ};";
        }

        // 3) recreate indexes on the NEW table name
        $idxSqls = [];
        foreach (($newTable['indexes'] ?? []) as $idx) {
            $def = new IndexDef(
                $idx['name'],
                $newName,
                $idx['columns'],
                (bool) ($idx['unique'] ?? false)
            );
            $idxSqls = array_merge($idxSqls, $this->compileCreateIndex($def));
        }

        return [
            '-- DAFDB_SQLITE_REBUILD_BEGIN',
            "PRAGMA foreign_keys=OFF;",
            "BEGIN;",
            "ALTER TABLE {$oldQ} RENAME TO {$tmpQ};",
            $createSql,
            $insertSql,
            ...$idxSqls,
            "DROP TABLE {$tmpQ};",
            "COMMIT;",
            "PRAGMA foreign_keys=ON;",
            '-- DAFDB_SQLITE_REBUILD_END',
        ];
    }

    private function compileCreateTableFromSchemaArray(string $tableName, array $schema): string
    {
        $cols = $schema['columns'] ?? ($schema['fields'] ?? []);
        $parts = [];

        foreach ($cols as $name => $col) {
            $def = $this->compileColumnFromSchema($schema, $name, $col);
            $parts[] = $def;
        }

        // composite PK
        if (!empty($schema['primaryKeys']) && count($schema['primaryKeys']) > 1) {
            $pkCols = implode(',', array_map(fn($c) => $this->q($c), $schema['primaryKeys']));
            $parts[] = "PRIMARY KEY ({$pkCols})";
        }

        // foreign keys
        if (!empty($schema['foreignKeys']) && is_array($schema['foreignKeys'])) {
            foreach ($schema['foreignKeys'] as $fk) {
                if (is_string($fk)) {
                    $parts[] = trim($fk);
                    continue;
                }
                if (!is_array($fk))
                    continue;

                $name = $fk['name'] ?? null;

                $cols = $fk['columns'] ?? [];
                $refCols = $fk['refColumns'] ?? [];

                $colsSql = $this->joinCols($cols);
                $refColsSql = $this->joinCols($refCols);

                $sql = "";
                if ($name)
                    $sql .= "CONSTRAINT " . $this->q($name) . " ";
                $sql .= "FOREIGN KEY ({$colsSql}) REFERENCES " . $this->q($fk['refTable']) . "({$refColsSql})";

                if (!empty($fk['onDelete']))
                    $sql .= " ON DELETE {$fk['onDelete']}";

                $parts[] = $sql;
            }
        }


        return "CREATE TABLE IF NOT EXISTS " . $this->q($tableName) . " (" . implode(',', $parts) . ");";
    }

    private function compileCreateIndex(IndexDef $idx): array
    {
        $cols = implode(',', array_map(fn($c) => $this->q($c), $idx->Columns));
        $unique = $idx->Unique ? "UNIQUE " : "";
        return ["CREATE {$unique}INDEX IF NOT EXISTS " . $this->q($idx->Name) . " ON " . $this->q($idx->Table) . " ({$cols});"];
    }
    private function compileColumn(TableBuilder $t, ColumnBuilder $c): string
    {
        return $this->compileColumnDefinition(
            $c->Name,
            [
                'type'          => $c->PhpType,
                'nullable'      => $c->Nullable,
                'length'        => $c->Length,
                'autoIncrement' => $c->AutoIncrement,
                'hasDefault'    => $c->HasDefault,
                'default'       => $c->Default,
            ],
            $t->Constraints->primaryKey ? [$t->Constraints->primaryKey->Columns] : []
        );
    }

    private function compileColumnFromSchema(array $tableSchema, string $colName, array $c): string
    {
        return $this->compileColumnDefinition(
            $colName,
            [
                'type'          => $c['type'] ?? 'string',
                'nullable'      => $c['nullable'] ?? true,
                'length'        => $c['length'] ?? null,
                'autoIncrement' => $c['autoIncrement'] ?? false,
                'hasDefault'    => $c['hasDefault'] ?? false,
                'default'       => $c['default'] ?? null,
            ],
            [$tableSchema['primaryKeys'] ?? []]
        );
    }

    private function compileColumnDefinition(string $name, array $def, ?array $pkCols = null): string
    {
        $type = match ($def['type']) {
            'int'      => 'INTEGER',
            'string'   => $def['length'] !== null ? "VARCHAR({$def['length']})" : 'TEXT',
            'float'    => 'FLOAT',
            'bool'     => 'BOOLEAN',
            'date'     => 'DATE',
            'datetime' => 'DATETIME',
            default    => 'TEXT',
        };

        $sql = $this->q($name) . " {$type}";

        if (!$def['nullable']) $sql .= " NOT NULL";
        if ($def['hasDefault']) $sql .= " DEFAULT " . $this->compileDefault($def['default'], $def['type']);

        $isSinglePk = $pkCols && count($pkCols[0]) === 1 && $pkCols[0][0] === $name;
        if ($isSinglePk) {
            $sql .= " PRIMARY KEY";
            if ($def['autoIncrement']) $sql .= " AUTOINCREMENT";
        }

        return $sql;
    }


    private function applyAlterToSchema(array $oldTable, AlterTableBuilder $alter): array
    {
        $new = $oldTable;

        // table name (for rebuild output)
        $new['table'] = $alter->RenameTo ?? ($oldTable['table'] ?? $alter->TableName);

        // ----- columns: rename
        foreach ($alter->RenameColumns as $old => $newName) {
            if (!isset($new['columns'][$old]))
                continue;
            $new['columns'][$newName] = $new['columns'][$old];
            unset($new['columns'][$old]);

            // if PK contains old, update it
            $new['primaryKeys'] = array_map(fn($c) => $c === $old ? $newName : $c, $new['primaryKeys'] ?? []);

            // update uniques/checks if you store column lists (uniques do)
            if (!empty($new['uniques'])) {
                foreach ($new['uniques'] as &$uq) {
                    $uq['columns'] = array_map(fn($c) => $c === $old ? $newName : $c, $uq['columns']);
                }
            }

            // update indexes column list
            if (!empty($new['indexes'])) {
                foreach ($new['indexes'] as &$idx) {
                    $idx['columns'] = array_map(fn($c) => $c === $old ? $newName : $c, $idx['columns']);
                }
            }

            // update FK column
            if (!empty($new['foreignKeys'])) {
                foreach ($new['foreignKeys'] as &$fk) {
                    if (!empty($fk['columns']) && is_array($fk['columns'])) {
                        $fk['columns'] = array_map(fn($c) => $c === $old ? $newName : $c, $fk['columns']);
                    }
                    // back-compat
                    if (($fk['column'] ?? null) === $old)
                        $fk['column'] = $newName;
                }
            }
        }

        // ----- drop columns
        foreach ($alter->DropColumns as $col) {
            unset($new['columns'][$col]);

            // remove from PK
            $new['primaryKeys'] = array_values(array_filter($new['primaryKeys'] ?? [], fn($c) => $c !== $col));

            // remove from uniques
            if (!empty($new['uniques'])) {
                foreach ($new['uniques'] as $name => $uq) {
                    $uqCols = array_values(array_filter($uq['columns'] ?? [], fn($c) => $c !== $col));
                    if (empty($uqCols))
                        unset($new['uniques'][$name]);
                    else
                        $new['uniques'][$name]['columns'] = $uqCols;
                }
            }

            // remove from indexes
            if (!empty($new['indexes'])) {
                $new['indexes'] = array_values(array_filter($new['indexes'], function ($idx) use ($col) {
                    return !in_array($col, $idx['columns'] ?? [], true);
                }));
            }

            // remove FK that points from this column
            if (!empty($new['foreignKeys'])) {
                $new['foreignKeys'] = array_filter($new['foreignKeys'], function ($fk) use ($col) {
                    return !in_array($col, $fk['columns'] ?? [], true);
                });
            }
        }

        // ----- add columns
        foreach ($alter->AddColumns as $c) {
            $new['columns'][$c->Name] = [
                'type' => $c->PhpType,
                'nullable' => $c->Nullable,
                'autoIncrement' => $c->AutoIncrement,
                'unique' => $c->Unique,
                'hasDefault' => $c->HasDefault,
                'default' => $c->Default,
            ];
        }

        // ----- modify columns
        foreach ($alter->ModifyColumns as $c) {
            if (!isset($new['columns'][$c->Name]))
                continue;
            $new['columns'][$c->Name] = [
                'type' => $c->PhpType,
                'nullable' => $c->Nullable,
                'autoIncrement' => $c->AutoIncrement,
                'unique' => $c->Unique,
                'hasDefault' => $c->HasDefault,
                'default' => $c->Default,
            ];
        }

        // ----- primary key
        if ($alter->PrimaryKeyNeedToDrop) {
            $new['primaryKeys'] = [];
        }
        if ($alter->PrimaryKey !== null) {
            $new['primaryKeys'] = array_values($alter->PrimaryKey->Columns);
        }

        // ----- foreign keys
        if (!empty($alter->DropForeignKeys)) {
            foreach ($alter->DropForeignKeys as $fkName) {
                unset($new['foreignKeys'][$fkName]);
            }
        }
        foreach ($alter->AddForeignKeys as $fk) {
            $new['foreignKeys'][$fk->Name] = [
                'name' => $fk->Name,
                'columns' => $fk->Columns,
                'refTable' => $fk->RefTable,
                'refColumns' => $fk->RefColumns,
                'onDelete' => $fk->OnDelete
            ];
        }

        // ----- indexes (apply to schema so rebuild recreates them)
        // drop
        foreach ($alter->DropIndexes as $name) {
            $new['indexes'] = array_values(array_filter($new['indexes'] ?? [], fn($idx) => ($idx['name'] ?? null) !== $name));
        }
        // rename
        foreach ($alter->RenameIndexes as $old => $newName) {
            foreach ($new['indexes'] ?? [] as &$idx) {
                if (($idx['name'] ?? null) === $old) {
                    $idx['name'] = $newName;
                    break;
                }
            }
        }
        // add
        foreach ($alter->AddIndexes as $idxDef) {
            $new['indexes'][] = [
                'name' => $idxDef->Name,
                'columns' => array_values($idxDef->Columns),
                'unique' => (bool) $idxDef->Unique,
            ];
        }

        // make sure columns order stable
        //ksort($new['columns']);

        // make sure uniques/checks are maps
        if (!isset($new['uniques']) || !is_array($new['uniques']))
            $new['uniques'] = [];
        if (!isset($new['checks']) || !is_array($new['checks']))
            $new['checks'] = [];


        foreach ($new['foreignKeys'] ?? [] as $name => &$fkDef) {
            $ref = $fkDef['refTable'] ?? '';
            if (preg_match('/^__daf_tmp_([A-Za-z0-9_]+)_[0-9a-f]{8}$/i', $ref, $m)) {
                $fkDef['refTable'] = $m[1];
            }
        }

        return $new;
    }

    /**
     * Builds a safe INSERT..SELECT plan for SQLite table rebuild.
     *
     * - Handles rename columns
     * - Backfills new columns using DEFAULT literal or NULL (if nullable)
     * - Throws if new column is NOT NULL and has no default (cannot backfill)
     *
     * @return array{insertCols: string[], selectExprs: string[]}
     */
    private function buildCopyPlan(array $oldTable, array $newTable, array $renameMap): array
    {
        $oldCols = $oldTable['columns'] ?? [];
        $newCols = $newTable['columns'] ?? [];

        // map newColName => oldColName (supports rename)
        $oldNameByNew = [];

        // default: same name
        foreach ($oldCols as $oldName => $_) {
            $oldNameByNew[$oldName] = $oldName;
        }

        // apply renames: old => new (AlterTableBuilder stores old=>new)
        foreach ($renameMap as $old => $new) {
            $oldNameByNew[$new] = $old;
        }

        $insertCols = [];
        $selectExprs = [];

        foreach ($newCols as $newName => $newCol) {
            $insertCols[] = $newName;

            $oldName = $oldNameByNew[$newName] ?? null;

            // Existing (or renamed) column -> SELECT old column
            if ($oldName !== null && array_key_exists($oldName, $oldCols)) {
                $expr = $this->q($oldName);

                // Optional: CAST on type change (safe-ish)
                $oldType = $oldCols[$oldName]['type'] ?? null;
                $newType = $newCol['type'] ?? null;

                $cast = $this->sqliteCastForTypeChange($oldType, $newType);
                if ($cast !== null) {
                    $expr = "CAST({$expr} AS {$cast})";
                }

                $selectExprs[] = "{$expr} AS " . $this->q($newName);
                continue;
            }

            // New column -> need a literal or NULL
            $nullable = (bool) ($newCol['nullable'] ?? true);
            $hasDefault = (bool) ($newCol['hasDefault'] ?? false);

            if ($hasDefault) {
                $def = $newCol['default'] ?? null;
                $phpType = (string) ($newCol['type'] ?? 'string');
                $selectExprs[] = $this->compileDefault($def, $phpType) . " AS " . $this->q($newName);
                continue;
            }

            if ($nullable) {
                $selectExprs[] = "NULL AS " . $this->q($newName);
                continue;
            }

            // Hard stop: rebuild cannot backfill this safely
            throw new \Exception(
                "SQLite rebuild failed: new NOT NULL column '{$newName}' has no default, cannot backfill during table rebuild."
            );
        }

        return ['insertCols' => $insertCols, 'selectExprs' => $selectExprs];
    }
    private function sqliteCastForTypeChange(?string $oldType, ?string $newType): ?string
    {
        if (!$oldType || !$newType || $oldType === $newType)
            return null;

        return match ($newType) {
            'int', 'bool' => 'INTEGER',
            'float', 'double' => 'REAL',
            'string' => 'TEXT',
            default => null,
        };
    }



    public function FkDefaultName(string $tableName, array $fk): string
    {
        $name = $fk['name'] ?? $fk['Name'] ?? null;
        if ($name)
            return (string) $name;

        $cols = $fk['columns'] ?? null;
        if (is_array($cols) && count($cols) > 0) {
            return "FK_{$tableName}_" . implode('_', $cols);
        }

        $col = $fk['column'] ?? $fk['Column'] ?? '';
        return "FK_{$tableName}_{$col}";
    }

    private function compileDefault(mixed $v, string $phpType): string
    {
        if ($v === null)
            return "NULL";

        if (is_string($v) && (str_starts_with($v, "raw:") || str_starts_with($v, "kind:"))) {
            $expr = SqlExpression::FromSignature($v);
            return $expr->Compile($this->providerId);
        }

        if ($v instanceof ISqlExpression) {
            return $v->Compile($this->providerId);
        }

        if ($phpType === 'bool')
            return $v ? "1" : "0";
        if ($phpType === 'int' || $phpType === 'float')
            return (string) $v;

        return "'" . str_replace("'", "''", (string) $v) . "'";
    }

}
