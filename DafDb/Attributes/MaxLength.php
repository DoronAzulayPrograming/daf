<?php
namespace DafDb\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MaxLength {
    public function __construct(public int $Value) {}
}