<?php
namespace DafDb\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ForeignKey
{
    public string $Table;
    public string $Column;
    public ?string $OnDelete;

    public function __construct(string $table, string $column, ?string $onDelete = null) {
        $this->Table = $table;
        $this->Column = $column;
        $this->OnDelete = $onDelete;
    }
}