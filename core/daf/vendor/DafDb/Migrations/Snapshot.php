<?php
namespace DafDb\Migrations;


abstract class Snapshot
{
    /** Apply schema changes */
    public abstract function GetBuilder(SnapshotBuilder $migrationBuilder): SnapshotBuilder;
}
