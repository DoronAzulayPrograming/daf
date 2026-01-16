<?php
namespace DafDb\Migrations;

use DafDb\Context;
use DafDb\Migrations\Builders\AlterTableBuilder;

final class MigrationBuilder {

    private array $tablesToCreate = [];
    private array $tablesToDrop = [];
    private array $tablesToAlter = [];

    public function GetChanges() : array{ return [$this->tablesToCreate, $this->tablesToDrop, $this->tablesToAlter]; }

    public function __construct(private Context $context)
    {
        $this->context = $context;
    }

    public function GetContext(): Context { return $this->context; }

    public function CreateTable(string $tableName, callable $build): void
    {
        $this->tablesToCreate[$tableName] = $build;
    }
    public function DropTable(string $tableName): void
    {
        $this->tablesToDrop[] = $tableName;
    }
    public function AlterTable(string $tableName, callable $alter): void
    {
        if (!isset($this->tablesToAlter[$tableName])) {
            $this->tablesToAlter[$tableName] = $alter;
            return;
        }

        $prev = $this->tablesToAlter[$tableName];
        $this->tablesToAlter[$tableName] = function (AlterTableBuilder $builder) use ($prev, $alter) {
            $prev($builder);
            $alter($builder);
        };
    }


}
