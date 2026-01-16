<?php
namespace DafDb\Migrations;

use DafDb\Migrations\Builders\TableBuilder;

class SnapshotBuilder {

    protected array $tables = [];
    private array $_useing = [];

    public function __construct(array $tables = []){
        $this->tables = $tables;
    }

    public function GetTable(string $name): mixed { return $this->tables[$name] ?? null; }
    public function GetTables(): array { return $this->tables; }
    public function SetTables(array $tables): void { $this->tables = $tables; }

    public function CreateTable(string $tableName, callable $build): void
    {
        $t = new TableBuilder($tableName);
        $build($t);
        $this->tables[$tableName] = $t;
    }

    private function addMigrationUseing(array|string $useing): void{

        if(is_array($useing)){
            $this->_useing = array_merge($this->_useing, $useing);
            return;
        }

        $this->_useing[$useing] = 1;
    }
    private function getMigrationUseing(): string
    {
        $usingStr = "";

        $paths = array_keys($this->_useing);

        usort($paths, function ($a, $b) {
            $len = strlen($a) <=> strlen($b);
            return $len !== 0 ? $len : strcmp($a, $b);
        });

        foreach ($paths as $path) {
            $usingStr .= 'use ' . $path . ';' . PHP_EOL;
        }

        return $usingStr;
    }


    private function joinIndentedLines(array $lines): string
    {
        // This string is inserted already at indentation level of the migration body.
        // We want:
        // - first line:        $MigrationBuilder->CreateTable(...)
        // - inner lines:            $t->Int(...)
        // - closing:        });
        $out = [];
        foreach ($lines as $i => $line) {
            // If the line starts with $t-> or "});" etc, we control indentation by how we build $lines.
            $out[] = $line;
        }

        // Indent *all* lines by 8 spaces, and extra indentation is baked into emitted lines.
        return implode("\n        ", $out);
    }

    
    public function __tostring(): string {
        $this->addMigrationUseing("DafDb\Migrations\Snapshot");
        $this->addMigrationUseing("DafDb\Migrations\SnapshotBuilder");
        $lines = [];
        /** @var TableBuilder $table */
        foreach($this->tables as $table){
            $lines[] = $table->EmitCreateTable();
            $this->addMigrationUseing($table->GetMigrationUseing());
        }

        $lines[] = '';
        $lines[] = 'return $builder;';

        $usingStr = $this->getMigrationUseing();
        $body = $this->joinIndentedLines($lines);
        
        return <<<PHP
<?php

$usingStr

return new class extends Snapshot {

    public function GetBuilder(SnapshotBuilder \$builder): SnapshotBuilder
    {
        {$body}    
    }

    public function Down(SnapshotBuilder \$builder): void { }
};
PHP;
    }
}
