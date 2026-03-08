<?php
namespace DafDb\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DefaultValueSql
{
    public function __construct(public string $Sql) {
        if ($Sql === null) {
            throw new \InvalidArgumentException("DefaultValue attribute requires Sql Value.");
        }
    }
}