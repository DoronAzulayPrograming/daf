<?php
namespace DafDb\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DefaultValue
{
    public function __construct(public mixed $Value) {
        if ($Value === null) {
            throw new \InvalidArgumentException("DefaultValue attribute requires Value.");
        }
    }
}