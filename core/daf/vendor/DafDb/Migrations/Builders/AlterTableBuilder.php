<?php
namespace DafDb\Migrations\Builders;

use DafDb\Migrations\Builders\Definitions\IndexDef;
use DafDb\Migrations\Builders\Definitions\ForeignKeyDef;
use DafDb\Migrations\Builders\Definitions\PrimaryKeyDef;
use DafDb\Migrations\Builders\Definitions\CheckConstraintDef;

final class AlterTableBuilder
{
    public string $TableName;

    public ?PrimaryKeyDef $PrimaryKey = null;
    public bool $PrimaryKeyNeedToDrop = false;

    /** @var ColumnBuilder[] */
    public array $AddColumns = [];


    /** @var string[] */
    public array $DropColumns = [];


    /** @var ColumnBuilder[] */
    public array $ModifyColumns = [];
    

    public ?string $RenameTo = null;

    // ✅ NEW: rename columns (old => new)
    /** @var array<string,string> */
    public array $RenameColumns = [];
    
    
    /** @var ForeignKeyDef[] */
    public array $AddForeignKeys = [];

    /** @var string[] FK constraint names */
    public array $DropForeignKeys = [];
    

    /** @var IndexDef[] */
    public array $AddIndexes = [];
    /** @var string[] */
    public array $DropIndexes = [];
    /** @var array<string,string> old => new */
    public array $RenameIndexes = [];


    public function __construct(string $tableName)
    {
        $this->TableName = $tableName;
    }

    // Factory methods (like TableBuilder)
    public function Int(string $Name): ColumnBuilder    { return new ColumnBuilder($Name, 'int'); }
    public function String(string $Name): ColumnBuilder { return new ColumnBuilder($Name, 'string'); }
    public function Float(string $Name): ColumnBuilder  { return new ColumnBuilder($Name, 'float'); }
    public function Bool(string $Name): ColumnBuilder   { return new ColumnBuilder($Name, 'bool'); }
    public function Date(string $name): ColumnBuilder   { return new ColumnBuilder($name, 'date'); }
    public function DateTime(string $name): ColumnBuilder { return new ColumnBuilder($name, 'datetime'); }

    
    // Operations
    public function AddColumn(callable $columnFactory): void
    {
        /** @var ColumnBuilder $col */
        $col = $columnFactory($this);
        $this->AddColumns[] = $col;
    }
    public function DropColumn(string $name): void
    {
        $this->DropColumns[] = $name;
    }
    public function ModifyColumn(callable $columnFactory): void
    {
        /** @var ColumnBuilder $col */
        $col = $columnFactory($this);
        $this->ModifyColumns[] = $col;
    }


    public function AddForeignKey(string|array $columns, string $refTable, string|array $refColumns, ?string $onDelete = null, ?string $name = null): void {
        $cols = is_array($columns) ? array_values($columns) : [$columns];
        $refCols = is_array($refColumns) ? array_values($refColumns) : [$refColumns];

        $name ??= "FK_{$this->TableName}_" . implode('_', $cols);

        $this->AddForeignKeys[] = new ForeignKeyDef($name, $cols, $refTable, $refCols, $onDelete);
    }    
    public function DropForeignKey(string $name): void
    {
        $this->DropForeignKeys[] = $name;
    }


    public function RenameTable(string $newTableName): void
    {
        $this->RenameTo = $newTableName;
    }
    public function RenameColumn(string $oldName, string $newName): void
    {
        $this->RenameColumns[$oldName] = $newName;
    }


    public function AddPrimaryKey(array $columns, ?string $name = null): void
    {
        $name ??= "PK_{$this->TableName}";
        $this->PrimaryKey = new PrimaryKeyDef($name, $columns);
    }
    public function DropPrimaryKey(): void { $this->PrimaryKeyNeedToDrop = true; }


    public function AddUnique(array $columns, ?string $name = null): void
    {
        $name ??= "UX_{$this->TableName}_" . implode('_', $columns);
        $this->AddIndex($name, $columns, true);
    }

    public function DropUnique(string $name): void
    {
        $this->DropIndex($name);
    }

    public function AddIndex(string $name, array $columns, bool $unique = false): void
    {
        $this->AddIndexes[] = new IndexDef($name, $this->TableName, $columns, $unique);
    }
    public function DropIndex(string $name): void { $this->DropIndexes[] = $name; }
    
    public function RenameIndex(string $oldName, string $newName): void
    {
        $this->RenameIndexes[$oldName] = $newName;
    }

    public function HasChanges(): bool
    {
        return
            $this->RenameTo !== null ||
            !empty($this->RenameColumns) ||
            !empty($this->AddColumns) ||
            !empty($this->DropColumns) ||
            !empty($this->ModifyColumns) ||
            !empty($this->AddForeignKeys) ||
            !empty($this->DropForeignKeys) ||
            $this->PrimaryKey !== null ||
            $this->PrimaryKeyNeedToDrop ||
            !empty($this->AddIndexes)||
            !empty($this->DropIndexes)||
            !empty($this->RenameIndexes);
    }

}
