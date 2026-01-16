<?php
namespace DafDb\Migrations\Providers;

use DafDb\Context;
use DafDb\Migrations\Builders\TableBuilder;
use DafDb\Migrations\Builders\ColumnBuilder;
use DafDb\Migrations\Services\SqlExpression;
use DafDb\Migrations\Services\ISqlExpression;
use DafDb\Migrations\Builders\AlterTableBuilder;
use DafDb\Migrations\Builders\Definitions\IndexDef;

final class MysqlProviderSql implements IProviderSql
{
    use SqlHelpersTrait;
    private string $providerId = 'mysql';

    public function CompileAlterTable(AlterTableBuilder $alter, ?array $oldSchema = null): array
    {
        $tableName = $alter->TableName;
        $sqls = [];

        $autoIncLater = []; // ColumnBuilder[] to MODIFY later with AUTO_INCREMENT

        if ($alter->RenameTo !== null) {
            $sqls[] = "RENAME TABLE " . $this->q($tableName) . " TO " . $this->q($alter->RenameTo) . ";";
            $tableName = $alter->RenameTo;
        }

        foreach ($alter->RenameColumns as $old => $new) {
            $sqls[] = "ALTER TABLE " . $this->q($tableName) . " RENAME COLUMN " . $this->q($old) . " TO " . $this->q($new) . ";";
        }

        foreach ($alter->DropForeignKeys as $fkName) {
            $sqls[] = "ALTER TABLE " . $this->q($tableName) . " DROP FOREIGN KEY " . $this->q($fkName) . ";";
        }

        foreach ($alter->DropIndexes as $idxName) {
            $sqls[] = "ALTER TABLE " . $this->q($tableName) . " DROP INDEX " . $this->q($idxName) . ";";
        }

        if ($alter->PrimaryKeyNeedToDrop) {
            $sqls[] = "ALTER TABLE " . $this->q($tableName) . " DROP PRIMARY KEY;";
        }

        foreach ($alter->DropColumns as $name) {
            $sqls[] = "ALTER TABLE " . $this->q($tableName) . " DROP COLUMN " . $this->q($name) . ";";
        }

        foreach ($alter->RenameIndexes as $old => $new) {
            $sqls[] = "ALTER TABLE " . $this->q($tableName) . " RENAME INDEX " . $this->q($old) . " TO " . $this->q($new) . ";";
        }

        // ---- MODIFY columns (delay AUTO_INCREMENT if needed)
        foreach ($alter->ModifyColumns as $col) {
            if ($col->AutoIncrement) {
                // first pass: without auto_increment
                $def = $this->compileColumnDefinition($col, includeAutoIncrement: false);
                $sqls[] = "ALTER TABLE " . $this->q($tableName) . " MODIFY " . $this->q($col->Name) . " {$def};";
                $autoIncLater[] = $col;
            } else {
                $def = $this->compileColumnDefinition($col, includeAutoIncrement: true);
                $sqls[] = "ALTER TABLE " . $this->q($tableName) . " MODIFY " . $this->q($col->Name) . " {$def};";
            }
        }

        // ---- ADD columns (delay AUTO_INCREMENT if needed)
        foreach ($alter->AddColumns as $col) {
            if ($col->AutoIncrement) {
                $def = $this->compileColumnDefinition($col, includeAutoIncrement: false);
                $sqls[] = "ALTER TABLE " . $this->q($tableName) . " ADD COLUMN " . $this->q($col->Name) . " {$def};";
                $autoIncLater[] = $col;
            } else {
                $def = $this->compileColumnDefinition($col, includeAutoIncrement: true);
                $sqls[] = "ALTER TABLE " . $this->q($tableName) . " ADD COLUMN " . $this->q($col->Name) . " {$def};";
            }
        }

        // ---- PRIMARY KEY
        if ($alter->PrimaryKey) {
            $pk = $alter->PrimaryKey;
            $cols = implode(',', array_map(fn($c) => $this->q($c), $pk->Columns));

            // safest MySQL form (constraint name is not reliably meaningful for PK)
            $sqls[] = "ALTER TABLE " . $this->q($tableName) . " ADD PRIMARY KEY ({$cols});";
        }

        // ---- INDEXES
        foreach ($alter->AddIndexes as $idx) {
            $cols = implode(',', array_map(fn($c) => $this->q($c), $idx->Columns));
            $u = $idx->Unique ? "UNIQUE " : "";
            $sqls[] = "ALTER TABLE " . $this->q($tableName) . " ADD {$u}INDEX " . $this->q($idx->Name) . " ({$cols});";
        }

        // ---- NOW apply AUTO_INCREMENT (after PK/UNIQUE exists)
        foreach ($autoIncLater as $col) {
            $def = $this->compileColumnDefinition($col, includeAutoIncrement: true);
            $sqls[] = "ALTER TABLE " . $this->q($tableName) . " MODIFY " . $this->q($col->Name) . " {$def};";
        }

        // ---- FOREIGN KEYS
        foreach ($alter->AddForeignKeys as $fk) {
            $cols = implode(',', array_map(fn($c) => $this->q($c), $fk->Columns));
            $refCols = implode(',', array_map(fn($c) => $this->q($c), $fk->RefColumns));

            $sql = "ALTER TABLE " . $this->q($tableName) . " ADD CONSTRAINT " . $this->q($fk->Name) . " " .
                "FOREIGN KEY ({$cols}) REFERENCES " . $this->q($fk->RefTable) . "({$refCols})";
            if ($fk->OnDelete)
                $sql .= " ON DELETE {$fk->OnDelete}";
            $sqls[] = $sql . ";";
        }

        return $sqls;
    }

    public function CompileDropTable(string $tableName): array
    {
        return ["DROP TABLE IF EXISTS " . $this->q($tableName) . ";"];
    }

    public function CompileCreateTable(TableBuilder $t): array
    {
        $parts = [];

        foreach ($t->Columns as $col) {
            $parts[] = $this->compileColumn($col);
        }

        // PK (single or composite)
        $pk = $t->Constraints->primaryKey;
        if ($pk) {
            $cols = implode(',', array_map(fn($c) => $this->q($c), $pk->Columns));
            $parts[] = "CONSTRAINT " . $this->q($pk->Name) . " PRIMARY KEY ({$cols})";
        }

        // FKs
        foreach ($t->Constraints->ForeignKeys as $fk) {
            $cols = implode(',', array_map(fn($c) => $this->q($c), $fk->Columns));
            $refCols = implode(',', array_map(fn($c) => $this->q($c), $fk->RefColumns));

            $sql = "CONSTRAINT " . $this->q($fk->Name) . " FOREIGN KEY ({$cols}) REFERENCES " . $this->q($fk->RefTable) . "({$refCols})";
            if ($fk->OnDelete)
                $sql .= " ON DELETE {$fk->OnDelete}";
            $parts[] = $sql;
        }

        $sqls = [];
        $sqls[] = "CREATE TABLE IF NOT EXISTS " . $this->q($t->Name) . " (" . implode(',', $parts) . ");";

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



    private function compileCreateIndex(IndexDef $idx): array
    {
        $cols = implode(',', array_map(fn($c) => $this->q($c), $idx->Columns));
        $unique = $idx->Unique ? "UNIQUE " : "";
        return ["CREATE {$unique}INDEX " . $this->q($idx->Name) . " ON " . $this->q($idx->Table) . " ({$cols});"];
    }

    private function compileColumn(ColumnBuilder $c): string
    {
        $type = match ($c->PhpType) {
            'int' => 'INTEGER',
            'string' => $this->mapStringType($c->Length),
            'float' => 'FLOAT',
            'bool' => 'BOOLEAN',
            'date' => 'DATE',
            'datetime' => 'DATETIME',
            default => 'TEXT',
        };

        $sql = $this->q($c->Name) . " {$type}";
        if (!$c->Nullable)
            $sql .= " NOT NULL";

        if ($c->HasDefault) {
            $sql .= " DEFAULT " . $this->compileDefault($c->Default, $c->PhpType);
        }

        if ($c->AutoIncrement)
            $sql .= " AUTO_INCREMENT";

        // if ($c->Unique) $sql .= " UNIQUE";
        return $sql;
    }

    private function compileColumnDefinition(ColumnBuilder $col, bool $includeAutoIncrement): string
    {
        $type = match ($col->PhpType) {
            'int' => 'INTEGER',
            'string' => $this->mapStringType($col->Length),
            'float' => 'FLOAT',
            'bool' => 'BOOLEAN',
            'date' => 'DATE',
            'datetime' => 'DATETIME',
            default => 'TEXT',
        };

        $def = $type;

        if (!$col->Nullable)
            $def .= ' NOT NULL';

        if ($col->HasDefault) {
            $def .= ' DEFAULT ' . $this->compileDefault($col->Default, $col->PhpType);
        }

        if ($includeAutoIncrement && $col->AutoIncrement) {
            $def .= ' AUTO_INCREMENT';
        }

        // if ($col->Unique)
        //     $def .= " UNIQUE";

        return $def;
    }

    private function mapStringType(?int $length): string {
        if ($length === null) return 'VARCHAR(255)';
        if ($length <= 0) return 'TEXT'; // optional fallback
        return "VARCHAR({$length})";
    }

    private function compileDefault(mixed $default, string $phpType): string
    {
        if (is_string($default) && (str_starts_with($default, "raw:") || str_starts_with($default, "kind:"))) {
            $expr = SqlExpression::FromSignature($default);
            return $expr->Compile($this->providerId);
        }

        if ($default instanceof ISqlExpression) {
            return $default->Compile($this->providerId);
        }

        // Scalar defaults
        if ($default === null)
            return "NULL";
        if ($phpType === 'bool')
            return $default ? "1" : "0";
        if ($phpType === 'int' || $phpType === 'float')
            return (string) $default;

        // string literal
        return "'" . str_replace("'", "''", (string) $default) . "'";
    }


}
