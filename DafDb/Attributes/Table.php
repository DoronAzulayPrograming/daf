<?php
namespace DafDb\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Table
{
    public string $Name;
    public string $Model;
    
    public function __construct(string $name = "", string $model) {
        $this->Name = $name;
        $this->Model = $model;
    }
}