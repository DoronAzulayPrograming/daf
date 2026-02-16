<?php
namespace DafDb\Helpers;

final class IncludeInfo
{
    public string $Property;
    public string $Table;
    public string $Condition;
    public string $ModelClass;
    public bool $IsMany;
    public function __construct(
        string $property,     // property name on model
        string $table,        // include table name
        string $condition,    // include condition string (your DSL)
        string $modelClass,   // target model class
        bool $isMany,
    ) {
        $this->Property = $property;
        $this->Table = $table;
        $this->Condition = $condition;
        $this->ModelClass = $modelClass;
        $this->IsMany = $isMany;
    }
}