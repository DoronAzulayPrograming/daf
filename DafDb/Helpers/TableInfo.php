<?php
namespace DafDb\Helpers;

final class TableInfo
{
    public string $Name;
    public string $ModelClass;

    /** @var array<string, ColumnInfo> */
    private array $Columns = [];

    /** @var array<int, string> */
    private array $PrimaryKeys = [];

    /** @var array<string, ForeignKeyInfo> key = local column name */
    private array $ForeignKeys = [];

    /** @var array<string, IncludeInfo> key = property name */
    private array $Includes = [];

    public function __construct(string $name, string $modelClass)
    {
        $this->Name = $name;
        $this->ModelClass = $modelClass;
    }

    // ---- Columns ----
    public function AddColumn(ColumnInfo $col): void
    {
        $this->Columns[$col->Name] = $col;
    }

    public function HasColumn(string $name): bool
    {
        return isset($this->Columns[$name]);
    }

    /** @return array<string, ColumnInfo> */
    public function GetColumns(): array
    {
        return $this->Columns;
    }

    /** @return array<int, string> */
    public function GetColumnNames(): array
    {
        return array_keys($this->Columns);
    }

    // ---- PK ----
    public function AddPrimaryKey(string $name): void
    {
        if (!in_array($name, $this->PrimaryKeys, true)) {
            $this->PrimaryKeys[] = $name;
        }
    }

    /** @return array<int, string> */
    public function GetPrimaryKeys(): array
    {
        return $this->PrimaryKeys;
    }

    public function IsPrimaryKey(string $name): bool
    {
        return in_array($name, $this->PrimaryKeys, true);
    }

    // ---- FK ----
    public function AddForeignKey(ForeignKeyInfo $fk): void
    {
        $this->ForeignKeys[$fk->Column] = $fk;
    }

    public function GetForeignKey(string $column): ?ForeignKeyInfo
    {
        return $this->ForeignKeys[$column] ?? null;
    }

    /** @return array<string, ForeignKeyInfo> */
    public function GetForeignKeys(): array
    {
        return $this->ForeignKeys;
    }

    // ---- Includes ----
    public function AddInclude(IncludeInfo $inc): void
    {
        $this->Includes[$inc->Property] = $inc;
    }

    public function GetInclude(string $property): ?IncludeInfo
    {
        return $this->Includes[$property] ?? null;
    }

    /** @return array<string, IncludeInfo> */
    public function Includes(): array
    {
        return $this->Includes;
    }

    public function GetAutoIncrementColumns(): array{
        $out = [];

        /** @var ColumnInfo $c */
        foreach ($this->Columns as $key => $c) {
            if($c->IsAutoIncrement) $out[$key] = $c;
        }

        return $out;
    }
    public function IsAutoIncrement(string $col): bool {
        if(!$this->HasColumn($col)) return false;

        return $this->Columns[$col]->IsAutoIncrement;
    }
}