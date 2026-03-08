<?php
namespace DafDb\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DbInclude
{
    public string $Table;
    public string $Condition;
    public ?string $Model = null;

    public function __construct(string $table, string $condition, ?string $model = null) { }
}