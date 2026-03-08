<?php
namespace DafDb\Migrations;

use DafDb\Context\DbContext;
use DafDb\Migrations\Builders\AlterTableBuilder;

final class MigrationBuilder {

    private DbContext $context;
    private array $actions = [];
    private array $alterActionIndex = [];

    private function addAction(string $type, mixed $payload): void
    {
        $this->actions[] = [
            'type' => $type,
            'payload' => $payload,
        ];
    }

    public function GetChanges() : array{ return $this->actions; }

    public function __construct(DbContext $context)
    {
        $this->context = $context;
    }

    public function GetContext(): DbContext { return $this->context; }

    public function CreateTable(string $tableName, callable $build): void
    {
        $this->addAction('create_table',['name' => $tableName,'build' => $build]);
    }
    public function DropTable(string $tableName): void
    {
        $this->addAction('drop_table',['name' => $tableName]);
    }

    public function AlterTable(string $tableName, callable $alter): void
    {
        if (!isset($this->alterActionIndex[$tableName])) {
            $this->addAction('alter_table', ['name' => $tableName, 'alter' => $alter]);
            $this->alterActionIndex[$tableName] = count($this->actions) - 1;
            return;
        }

        $idx = $this->alterActionIndex[$tableName];
        $prev = $this->actions[$idx]['payload']['alter'];

        $merged = function (AlterTableBuilder $builder) use ($prev, $alter) {
            $prev($builder);
            $alter($builder);
        };

        $this->actions[$idx]['payload']['alter'] = $merged;
    }


    public function Sql(string $sql): void { 
        $this->addAction('raw_sql',['sql' => $sql]);
    }

}
