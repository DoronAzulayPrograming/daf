<?php
namespace DafDb\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Table
{
    public function __construct(public string $Name = "", public string $Model) {}
}