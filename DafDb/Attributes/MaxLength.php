<?php
namespace DafDb\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MaxLength {
    public int $Value;
    public function __construct(int $value) { $this->Value = $value; }
}