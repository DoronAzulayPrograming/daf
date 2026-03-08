<?php
namespace DafDb\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DefaultValue
{
    public mixed $Value;
    public function __construct(mixed $value) {
        if ($value === null) {
            throw new \InvalidArgumentException("DefaultValue attribute requires Value.");
        }
        $this->Value = $value;
    }
}