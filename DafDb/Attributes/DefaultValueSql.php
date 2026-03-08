<?php
namespace DafDb\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DefaultValueSql
{
    public string $Sql;

    public function __construct(string $sql) { $this->Sql = $sql; }
}