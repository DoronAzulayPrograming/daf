<?php
namespace DafDb\Migrations\Builders;

use DafDb\Migrations\Builders\Definitions\ForeignKeyDef;
use DafDb\Migrations\Builders\Definitions\PrimaryKeyDef;
use DafDb\Migrations\Builders\Definitions\UniqueConstraintDef;

final class ConstraintsBuilder
{
    public ?PrimaryKeyDef $primaryKey = null;

    /** @var ForeignKeyDef[] */
    public array $ForeignKeys = [];

    /** @var UniqueConstraintDef[] */
    public array $Uniques = [];

    public function __construct(public string $TableName) {}

    public function PrimaryKey(array $columns, ?string $name = null): self
    {
        $name ??= "PK_{$this->TableName}";
        $this->primaryKey = new PrimaryKeyDef($name, $columns);
        return $this;
    }

    public function ForeignKey(
        string|array $columns,
        string $refTable,
        string|array $refColumns,
        ?string $onDelete = null,
        ?string $name = null
    ): self {
        $cols = is_array($columns) ? array_values($columns) : [$columns];
        $refCols = is_array($refColumns) ? array_values($refColumns) : [$refColumns];

        // default name: FK_Table_colA_colB
        $name ??= "FK_{$this->TableName}_" . implode('_', $cols);

        $this->ForeignKeys[] = new ForeignKeyDef($name, $cols, $refTable, $refCols, $onDelete);
        return $this;
    }

    public function Unique(array $columns, ?string $name = null): self
    {
        $name ??= "UX_{$this->TableName}_" . implode('_', $columns);
        $this->Uniques[] = new UniqueConstraintDef($name, $columns);
        return $this;
    }

}
