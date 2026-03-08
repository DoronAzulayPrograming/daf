<?php
namespace DafDb\Migrations\Builders\Definitions;

final class UniqueConstraintDef {
    public function __construct(
        public string $Name,
        public array $Columns
    ) {}
}