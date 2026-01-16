<?php
namespace DafDb\Migrations\Builders\Definitions;

final class PrimaryKeyDef {
    public function __construct(public string $Name, public array $Columns) {}
}