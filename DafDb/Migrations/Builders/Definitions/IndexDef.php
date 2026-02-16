<?php
namespace DafDb\Migrations\Builders\Definitions;

final class IndexDef {
    public function __construct(
        public string $Name,
        public string $Table,
        public array $Columns,
        public bool $Unique = false
    ) {}
}
