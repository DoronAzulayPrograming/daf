<?php
namespace DafDb\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DbInclude
{
    public function __construct(public string $Table, public string $Condition, public string | null $Model = null) { }
}