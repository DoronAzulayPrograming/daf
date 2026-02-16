<?php
namespace DafDb\Helpers;

final class ForeignKeyInfo
{
    public string $Column;
    public string $RefTable;
    public string $RefColumn;
    public ?string $OnDelete;

    public function __construct(
        string $column,          // local column name (property)
        string $refTable,        // referenced table
        string $refColumn,       // referenced column
        ?string $onDelete = null // e.g. "CASCADE", "RESTRICT"...
    ) {
        $this->Column = $column;
        $this->RefTable = $refTable;
        $this->RefColumn = $refColumn;
        $this->OnDelete = $onDelete;
    }

    public function toSql(): string
    {
        $onDelete = $this->OnDelete ? (" ON DELETE " . $this->OnDelete) : "";
        return " FOREIGN KEY (`{$this->Column}`) REFERENCES `{$this->RefTable}`(`{$this->RefColumn}`){$onDelete}";
    }
}