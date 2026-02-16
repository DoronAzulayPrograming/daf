<?php
namespace DafDb\Helpers;

final class ColumnInfo
{
    public string $Name;
    public ?string $PhpTypeName;
    public bool $IsBuiltIn;
    public bool $IsDate;
    public bool $IsAutoIncrement;

    public function __construct(string $name, ?string $phpTypeName, bool $isBuiltIn, bool $isDate, bool $isAutoIncrement) {
        $this->Name = $name;
        $this->PhpTypeName = $phpTypeName;
        $this->IsBuiltIn = $isBuiltIn;
        $this->IsDate = $isDate;
        $this->IsAutoIncrement = $isAutoIncrement;
    }
}