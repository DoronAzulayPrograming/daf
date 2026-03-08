<?php
namespace DafDb\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ForeignKey
{
    public $Value;

    public function __construct(public string $Table, public string $Column, public ?string $OnDelete = null) {
        $this->Value = new \stdClass();
        $this->Value->OnDelete = $OnDelete;
        $this->Value->Table = $Table;
        $this->Value->Column = $Column;
    }
}