<?php
namespace DafDb\Migrations;

abstract class Migration
{
    /** Apply schema changes */
    public abstract function Up(MigrationBuilder $migrationBuilder): void;

    /** Revert schema changes */
    public abstract function Down(MigrationBuilder $migrationBuilder): void;
}
