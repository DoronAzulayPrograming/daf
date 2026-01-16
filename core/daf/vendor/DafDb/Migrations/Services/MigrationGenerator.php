<?php
namespace DafDb\Migrations\Services;

use DafDb\DbContext;
use DafDb\Migrations\SnapshotBuilder;
use DafDb\Migrations\Builders\TableBuilder;
use DafDb\Migrations\Builders\ColumnBuilder;
use DafDb\Migrations\Storage\Repositories\MigrationsRepository;

class MigrationGenerator {
    use MigrationsSchemaHelper;

    private SnapshotBuilder $_currSnapshot;
    private SnapshotBuilder $_prevSnapshot;

    private array $_migrationUsing = [];

    /**
     * Generate a migration file from model changes.
     *
     * @param DbContext $context       Your AppContext that has public repositories
     * @param string    $migrationName Name for the migration, e.g. "InitDb" or "AddUserAge"
     */
    public function Generate(DbContext $context, string $migrationName, string $appFolder): void
    {
        $this->ensureMigrationsTable($context->context);
        $this->addMigrationUseing("DafDb\Migrations\Migration");
        $this->addMigrationUseing("DafDb\Migrations\MigrationBuilder");

        $migrationsRepo = new MigrationsRepository($context->context);
        $lastMigration = $migrationsRepo
            ->OrderByDescending(fn($m) => $m->Id)
            ->FirstOrDefault();

        $lastMigrationName = $lastMigration?->Name ?? null;     
        $prevTables = $this->loadSnapshotTables($appFolder, $lastMigrationName);
        $this->_prevSnapshot = new SnapshotBuilder($prevTables);
        $this->_currSnapshot = new SnapshotBuilder($context->getModelSnapshot());

        // Normalize current snapshots to a compact comparable shape.
        // echo $currentDb === $curr;
        $prev = $this->NormalizeSnapshotMap($this->_prevSnapshot->GetTables());
        $curr = $this->NormalizeSnapshotMap($this->_currSnapshot->GetTables());

        $renamePairs = $this->findPossibleTableRenamePairs($prev, $curr); // [new => old]
        $renamePairsReverse = [];
        foreach ($renamePairs as $new => $old) $renamePairsReverse[$old] = $new; // [old => new]

        $upOps = [];
        $downOps = [];

        // 1) New tables
        $upOrder = $this->topoSortTables($curr);
        foreach ($upOrder as $tableName) {
            if (isset($prev[$tableName])) continue;

            // ✅ Add the “maybe rename” comment block BEFORE the default ops
            if (isset($renamePairs[$tableName])) {
                $oldName = $renamePairs[$tableName];
                $upOps[] = $this->emitTableRename(
                    'UP',
                    $oldName,
                    $tableName,
                    $prev[$oldName],
                    $curr[$tableName]
                );
            }else{
                $upOps[] = $this->emitCreateTable($this->_currSnapshot->GetTable($tableName));
                $downOps[] = "\$builder->DropTable(" . $this->phpStringLiteral($tableName) . ");";
            }

        }

        // 2) Removed tables
        $downDropOrder = array_reverse($this->topoSortTables($prev));
        foreach ($downDropOrder as $tableName) {
            if (isset($curr[$tableName])) continue;
        
            // ✅ Add the “maybe rename” comment block to DOWN ops (reverse direction)
            if (isset($renamePairsReverse[$tableName])) {
                $newName = $renamePairsReverse[$tableName];
                $downOps[] = $this->emitTableRename(
                    'DOWN',
                    $tableName,
                    $newName,
                    $prev[$tableName],
                    $curr[$newName]
                );
            }else{
                $upOps[] = "\$builder->DropTable(" . $this->phpStringLiteral($tableName) . ");";
                $downOps[] = $this->emitCreateTable($this->_prevSnapshot->GetTable($tableName));
            }
        }

        // 3) Changed tables
        foreach ($curr as $tableName => $currTable) {
            if (!isset($prev[$tableName])) continue;
            $prevTable = $prev[$tableName];

            $oldTableBuilder = $this->_prevSnapshot->GetTable($tableName);
            $newTableBuilder = $this->_currSnapshot->GetTable($tableName);
            
            if (!$oldTableBuilder || !$newTableBuilder) continue;
            if ($this->tablesEqual($prevTable, $currTable)) continue;

            $upOps[] = $this->emitAlterTable($oldTableBuilder, $newTableBuilder, $prevTable, $currTable);
            $downOps[] = $this->emitAlterTable($newTableBuilder, $oldTableBuilder, $currTable, $prevTable);

            // $upOps[] = $this->emitAlterTableOp($prevTable, $currTable);
            // $downOps[] = $this->emitAlterTableOp($currTable, $prevTable);
        }

        $downOps = $this->reorderDropTableOps($downOps, $curr);

        // If nothing changed - still generate? up to you.
        // If you want "No changes detected" just return here.
        // if (empty($upOps) && empty($downOps)) { echo "No Changes Detected.\n"; return; }

        // 4) Write migration file
        $fileDir = $appFolder . '/Migrations';
        $timestamp = date('Y_m_d_His');
        $fileBase  = $timestamp . '_' . $migrationName;
        $filePath  = $fileDir . '/' . $fileBase . '.php';

        if (!is_dir($fileDir)) mkdir($fileDir, 0777, true);

        $php = $this->buildMigrationPhpFile_ModelBuilderOnly($upOps, $downOps);
        file_put_contents($filePath, $php);

        echo "Migration created: {$filePath}\n";
    }


    private function addMigrationUseing(array|string $useing){

        if(is_array($useing)){
            $this->_migrationUsing = array_merge($this->_migrationUsing, $useing);
            return;
        }

        $this->_migrationUsing[$useing] = 1;
    }
    private function getMigrationUseing(): string
    {
        $usingStr = "";

        $paths = array_keys($this->_migrationUsing);

        usort($paths, function ($a, $b) {
            $len = strlen($a) <=> strlen($b);
            return $len !== 0 ? $len : strcmp($a, $b);
        });

        foreach ($paths as $path) {
            $usingStr .= 'use ' . $path . ';' . PHP_EOL;
        }

        return $usingStr;
    }

    private function emitCreateTable(TableBuilder $table): string{
        $op = $table->EmitCreateTable();
        $this->addMigrationUseing($table->GetMigrationUseing());
        return $op;
    }
    private function emitTableRename(string $direction, string $oldName, string $newName, TableBuilder $oldTable, TableBuilder $newTable ): string {
        // We output a single big comment that contains the "full command hits" you requested.

        // Build the three options using your existing emitters:

        $createNew = $this->emitCreateTable($newTable);
        $createOld = $this->emitCreateTable($oldTable);

        // For UP: rename old->new, OR create new, OR drop old
        // For DOWN: rename new->old, OR drop new, OR create old
        $lines = [];
        $lines[] = "/*";
        $lines[] = "POSSIBLE TABLE RENAME DETECTED ({$direction})";
        $lines[] = "Old: {$oldName}";
        $lines[] = "New: {$newName}";
        $lines[] = "";

        if ($direction === 'UP') {
            $lines[] = "UP - choose one option:";
            $lines[] = "";
            $lines[] = "Option 1: Rename table";
            $lines[] = "\$migrationBuilder->AlterTable(" . $this->phpStringLiteral($oldName) . ", function(AlterTableBuilder \$t) {";
            $lines[] = "    \$t->RenameTable(" . $this->phpStringLiteral($newName) . ");";
            $lines[] = "});";
            $lines[] = "";
            $lines[] = "Option 2: Create table (generated)";
            foreach (explode("\n", $createNew) as $l) $lines[] = $l;
            $lines[] = "";
            $lines[] = "Option 3: Drop table (generated)";
            $lines[] = "\$migrationBuilder->DropTable(" . $this->phpStringLiteral($oldName) . ");";
        } else {
            $lines[] = "DOWN - choose one option:";
            $lines[] = "";
            $lines[] = "Option 1: Rename table";
            $lines[] = "\$migrationBuilder->AlterTable(" . $this->phpStringLiteral($newName) . ", function(AlterTableBuilder \$t) {";
            $lines[] = "    \$t->RenameTable(" . $this->phpStringLiteral($oldName) . ");";
            $lines[] = "});";
            $lines[] = "";
            $lines[] = "Option 2: Drop table (generated)";
            $lines[] = "\$migrationBuilder->DropTable(" . $this->phpStringLiteral($newName) . ");";
            $lines[] = "";
            $lines[] = "Option 3: Create table (generated)";
            foreach (explode("\n", $createOld) as $l) $lines[] = $l;
        }

        $lines[] = "*/";

        // Return as a single string op line (already indented by joinIndentedLines later)
        // We'll prefix each line with "// " to keep it as PHP comments safely.
        return implode("\n", array_map(fn($l) => "// " . $l, $lines));
    }
    private function emitAlterTable(TableBuilder $oldTableBuilder, TableBuilder $newTableBuilder, array $oldTable, array $newTable): string
    {
        $this->addMigrationUseing('DafDb\Migrations\Builders\AlterTableBuilder');

        $tableName = $newTableBuilder->Name;

        $oldCols = $oldTable['columns'] ?? [];
        $newCols = $newTable['columns'] ?? [];

        $oldColumnBuilders = $oldTableBuilder->Columns;
        $newColumnBuilders = $newTableBuilder->Columns;

        $adds           = [];
        $addsForCompare = [];
        $drops          = [];
        $mods           = [];

        foreach ($newCols as $colName => $colDef) {
            if (!isset($oldCols[$colName])) {
                $adds[$colName]           = $newColumnBuilders[$colName];
                $addsForCompare[$colName] = $colDef;
                continue;
            }

            if (!$this->columnsEqualLite($oldCols[$colName], $colDef)) {
                $mods[$colName] = $newColumnBuilders[$colName];
            }
        }

        foreach ($oldCols as $colName => $_) {
            if (!isset($newCols[$colName])) {
                $drops[] = $colName;
            }
        }

        $possibleRenames = $this->findPossibleColumnRenamePairs($oldCols, $addsForCompare, $drops);

        $renameAddCols = [];
        foreach ($possibleRenames as $newName => $oldName) {
            $renameAddCols[$newName] = $adds[$newName];
            unset($adds[$newName], $addsForCompare[$newName]);
            $drops = array_values(array_filter($drops, fn ($d) => $d !== $oldName));
        }

        $oldPkCols = array_values($oldTable['primaryKeys'] ?? []);
        $newPkCols = array_values($newTable['primaryKeys'] ?? []);

        $oldFks = $oldTable['foreignKeys'] ?? [];
        $newFks = $newTable['foreignKeys'] ?? [];
        $diff  = $this->diffFkSignatures($oldFks, $newFks);

        $oldBySig = $this->fkMapBySignature($oldFks);
        $newBySig = $this->fkMapBySignature($newFks);

        $fkDropNames = [];
        $fkAddDefs   = [];

        foreach ($diff['removed'] as $sig) {
            foreach (($oldBySig[$sig] ?? []) as $entry) {
                if (!empty($entry['name'])) {
                    $fkDropNames[] = $entry['name'];
                }
            }
        }

        foreach ($diff['added'] as $sig) {
            foreach (($newBySig[$sig] ?? []) as $entry) {
                $fkAddDefs[] = $entry['fk'];
            }
        }

        $fkDropNames = array_values(array_unique($fkDropNames));

        $lines   = [];
        $lines[] = "\$builder->AlterTable(" . $this->phpStringLiteral($tableName) . ", function(AlterTableBuilder \$t) {";

        foreach ($possibleRenames as $newColName => $oldColName) {
            $factory = $this->emitColumn('$t', $renameAddCols[$newColName]);

            $comment   = [];
            $comment[] = "/*";
            $comment[] = "POSSIBLE COLUMN RENAME DETECTED";
            $comment[] = "Old: {$oldColName}";
            $comment[] = "New: {$newColName}";
            $comment[] = "";
            $comment[] = "OPTIONS (choose one):";
            $comment[] = "";
            $comment[] = "Option 1: Rename column";
            $comment[] = "\$t->RenameColumn(" . $this->phpStringLiteral($oldColName) . ", " . $this->phpStringLiteral($newColName) . ");";
            $comment[] = "";
            $comment[] = "Option 2: Add column (generated)";
            $comment[] = "\$t->AddColumn(fn(AlterTableBuilder \$t) => {$factory});";
            $comment[] = "";
            $comment[] = "Option 3: Drop column (generated)";
            $comment[] = "\$t->DropColumn(" . $this->phpStringLiteral($oldColName) . ");";
            $comment[] = "*/";

            foreach ($comment as $c) {
                $lines[] = $c;
            }
        }

        foreach ($adds as $colName => $column) {
            $factory = $this->emitColumn('$t', $column);
            $lines[] = "    \$t->AddColumn(fn(AlterTableBuilder \$t) => {$factory});";
        }

        foreach ($drops as $colName) {
            $lines[] = "    \$t->DropColumn(" . $this->phpStringLiteral($colName) . ");";
        }

        foreach ($mods as $colName => $column) {
            $factory = $this->emitColumn('$t', $column);
            $lines[] = "    \$t->ModifyColumn(fn(AlterTableBuilder \$t) => {$factory});";
        }

        foreach ($fkDropNames as $fkName) {
            $lines[] = "    \$t->DropForeignKey(" . $this->phpStringLiteral($fkName) . ");";
        }

        foreach ($fkAddDefs as $fk) {
            $args = $this->emitForeignKeyFactory($fk);
            $lines[] = $args === null
                ? "    // TODO: AddForeignKey (missing details in snapshot)"
                : "    \$t->AddForeignKey({$args});";
        }

        if ($oldPkCols !== $newPkCols) {
            if (!empty($oldPkCols)) {
                $lines[] = "    \$t->DropPrimaryKey();";
            }
            if (!empty($newPkCols)) {
                $lines[] = "    \$t->AddPrimaryKey(" . $this->phpArrayShort($newPkCols) . ");";
            }
        }

        foreach ($this->emitIndexAlterLines($oldTable, $newTable) as $ln) {
            $lines[] = "    {$ln}";
        }

        $lines[] = "});";

        return $this->joinIndentedLines($lines);
    }
    private function emitColumn(string $var, ColumnBuilder $column): string
    {
        $this->addMigrationUseing($column->GetMigrationUsing());
        return $column->EmitColumn($var);
    }

    /**
     * Compare two normalized tables.
     * Keep it strict on columns + primary keys (indexes/fks can be added later).
     */
    private function tablesEqual(array $a, array $b): bool
    {
        $ac = $a['columns'] ?? [];
        $bc = $b['columns'] ?? [];
        if (count($ac) !== count($bc)) return false;

        foreach ($bc as $name => $col) {
            if (!isset($ac[$name])) return false;

            $keys = ['type','nullable','autoIncrement','unique','hasDefault','default','length'];
            foreach ($keys as $k) {
                if (($ac[$name][$k] ?? null) !== ($col[$k] ?? null)) return false;
            }
        }

        // PKs
        if (($a['primaryKeys'] ?? []) !== ($b['primaryKeys'] ?? [])) return false;
        
        // FKs
        if (!$this->foreignKeysEqualLite($a['foreignKeys'] ?? [], $b['foreignKeys'] ?? [])) return false;

        // ✅ indexes (order-independent by name)
        if (!$this->indexListEqualLite($a['indexes'] ?? [], $b['indexes'] ?? [])) return false;

        return true;
    }

    private function foreignKeysEqualLite(array $a, array $b): bool
    {
        // Compare by definition signature, not by constraint name.
        // This avoids false diffs when providers/name generation differ.

        return $this->fkSignatureList($a) === $this->fkSignatureList($b);
    }

    /**
     * Build stable signatures list for FK maps/lists.
     * Accepts your normalized "map keyed by name".
     */
    private function fkSignatureList(array $fks): array
    {
        $out = [];

        foreach ($fks as $fk) {
            if (!is_array($fk)) continue;
            $out[] = $this->fkSignature($fk);
        }

        sort($out, SORT_STRING);
        return $out;
    }

    /**
     * Deterministic FK signature (order-sensitive for composite keys).
     * IMPORTANT: we do NOT sort columns/refColumns because FK order matters.
     */
    private function fkSignature(array $fk): string
    {
        $cols     = $fk['columns'] ?? [];
        $refTable = (string)($fk['refTable'] ?? '');
        $refCols  = $fk['refColumns'] ?? [];
        $onDelete = $fk['onDelete']   ?? null;

        // Normalize (extra safety)
        $cols = array_values(array_map(fn($c) => strtolower((string)$c), is_array($cols) ? $cols : []));
        $refCols = array_values(array_map(fn($c) => strtolower((string)$c), is_array($refCols) ? $refCols : []));
        $refTable = strtolower($refTable);

        if ($onDelete !== null) {
            $onDelete = strtoupper(trim((string)$onDelete));
        }

        return
            'cols:' . implode(',', $cols) .
            '|ref:' . $refTable .
            '|refCols:' . implode(',', $refCols) .
            '|onDelete:' . ($onDelete ?? '');
    }
    
    /**
     * Map FKs by deterministic signature.
     * Returns: [ signature => [ ['name' => string, 'fk' => array], ... ] ]
     *
     * Supports both shapes:
     * - map keyed by constraint name (your normalized shape)
     * - list-like arrays (numeric keys)
     */
    private function fkMapBySignature(array $fks): array
    {
        $map = [];

        foreach ($fks as $key => $fk) {
            if (!is_array($fk)) continue;

            $sig = $this->fkSignature($fk);

            // Prefer the array key if it's a string; otherwise fallback to $fk['name']
            $name = is_string($key) ? $key : (string)($fk['name'] ?? '');

            $map[$sig][] = [
                'name' => $name,
                'fk'   => $fk,
            ];
        }

        return $map;
    }
    private function diffFkSignatures(array $oldFks, array $newFks): array
    {
        $a = $this->fkSignatureList($oldFks);
        $b = $this->fkSignatureList($newFks);

        return [
            'removed' => array_values(array_diff($a, $b)),
            'added'   => array_values(array_diff($b, $a)),
        ];
    }



    /**
     * Build the PHP code for a migration file from arrays of SQL strings.
     */
    private function buildMigrationPhpFile_ModelBuilderOnly(array $upOps, array $downOps): string
    {
        $upBody = empty($upOps) ? "        // No operations.\n" : implode("\n", array_map(fn($l)=>"        {$l}", $upOps)) . "\n";
        $downBody = empty($downOps) ? "        // No operations.\n" : implode("\n", array_map(fn($l)=>"        {$l}", $downOps)) . "\n";

        $usingStr = $this->getMigrationUseing();
        return <<<PHP
<?php

$usingStr

return new class extends Migration {

    public function Up(MigrationBuilder \$builder): void
    {
{$upBody}    }

    public function Down(MigrationBuilder \$builder): void
    {
{$downBody}    }
};
PHP;
    }


    private function findPossibleColumnRenamePairs(array $oldCols, array $adds, array $drops): array
    {
        // returns: [ newColName => oldColName ]
        $pairs = [];
        $usedOld = [];

        foreach ($adds as $newName => $newCol) {
            foreach ($drops as $oldName) {
                if (isset($usedOld[$oldName])) continue;
                if (!isset($oldCols[$oldName])) continue;

                if ($this->columnsEqualLite($oldCols[$oldName], $newCol)) {
                    $pairs[$newName] = $oldName;
                    $usedOld[$oldName] = true;
                    break;
                }
            }
        }

        return $pairs;
    }
    private function findPossibleTableRenamePairs(array $prev, array $curr): array
    {
        // returns: [ newTableName => oldTableName ]
        $newOnly = [];
        foreach ($curr as $name => $_) if (!isset($prev[$name])) $newOnly[] = $name;

        $oldOnly = [];
        foreach ($prev as $name => $_) if (!isset($curr[$name])) $oldOnly[] = $name;

        $pairs = [];
        $usedOld = [];

        foreach ($newOnly as $newName) {
            foreach ($oldOnly as $oldName) {
                if (isset($usedOld[$oldName])) continue;

                // ✅ use YOUR tablesEqual (already supports PK/FK/IDX)
                if ($this->tablesEqual($prev[$oldName], $curr[$newName])) {
                    $pairs[$newName] = $oldName;
                    $usedOld[$oldName] = true;
                    break;
                }
            }
        }

        return $pairs;
    }


    //sorts
    private function buildDependencies(array $tables): array
    {
        // deps[A] = [B,C] means A depends on B and C (FK refs)
        $deps = [];
        foreach ($tables as $name => $t) {
            $deps[$name] = [];
            foreach (($t['foreignKeys'] ?? []) as $fk) {
                $ref = $fk['refTable'] ?? null;
                if ($ref && isset($tables[$ref]) && $ref !== $name) {
                    $deps[$name][$ref] = true;
                }
            }
            $deps[$name] = array_keys($deps[$name]);
        }
        return $deps;
    }
    private function topoSortTables(array $tables): array
    {
        $deps = $this->buildDependencies($tables);

        $inDeg = [];
        foreach ($tables as $name => $_) $inDeg[$name] = 0;
        foreach ($deps as $a => $refs) {
            foreach ($refs as $b) $inDeg[$a]++; // a has incoming edges from its deps
        }

        $queue = [];
        foreach ($inDeg as $n => $d) if ($d === 0) $queue[] = $n;

        $out = [];
        while (!empty($queue)) {
            $n = array_shift($queue);
            $out[] = $n;

            // remove edges where X depends on n
            foreach ($deps as $x => $refs) {
                if (!in_array($n, $refs, true)) continue;
                $inDeg[$x]--;
                if ($inDeg[$x] === 0) $queue[] = $x;
            }
        }

        // fallback: if cycle, keep original order (should be rare)
        if (count($out) !== count($tables)) {
            return array_keys($tables);
        }

        return $out;
    }
    private function reorderDropTableOps(array $downOps, array $tables): array
    {
        $drops = [];
        $other = [];

        foreach ($downOps as $op) {
            if (preg_match("/\\\$migrationBuilder->DropTable\\('([^']+)'\\);/", $op, $m)) {
                $drops[$m[1]] = $op;
            } else {
                $other[] = $op;
            }
        }

        if (empty($drops)) return $downOps;

        $order = array_reverse($this->topoSortTables($tables));
        $sortedDrops = [];
        foreach ($order as $t) {
            if (isset($drops[$t])) $sortedDrops[] = $drops[$t];
        }

        // any leftover drops (unknown tables)
        foreach ($drops as $t => $op) {
            if (!in_array($op, $sortedDrops, true)) $sortedDrops[] = $op;
        }

        return array_merge($other, $sortedDrops);
    }

    //emits
    private function emitIndexAlterLines(array $oldTable, array $newTable): array
    {
        $old = $this->indexMapByName($oldTable['indexes'] ?? []);
        $new = $this->indexMapByName($newTable['indexes'] ?? []);

        $lines = [];

        // --- detect renames (same signature, different name)
        $oldSig = [];
        foreach ($old as $name => $def) {
            $cols = array_values($def['columns'] ?? []); 
            $sig = (!empty($def['unique']) ? '1' : '0') . '|' . implode(',', $cols);
            $oldSig[$sig] = $name;
        }
        $newSig = [];
        foreach ($new as $name => $def) {
            $cols = array_values($def['columns'] ?? []);
            $sig = (!empty($def['unique']) ? '1' : '0') . '|' . implode(',', $cols);
            $newSig[$sig] = $name;
        }

        foreach ($oldSig as $sig => $oldName) {
            if (!isset($newSig[$sig])) continue;
            $newName = $newSig[$sig];
            if ($oldName === $newName) continue;

            $lines[] = "\$t->RenameIndex(" . $this->phpStringLiteral($oldName) . ", " . $this->phpStringLiteral($newName) . ");";

            unset($old[$oldName]);
            unset($new[$newName]);
        }

        // --- drops (removed or changed)
        foreach ($old as $name => $oldIdx) {
            if (!isset($new[$name])) {
                $lines[] = "\$t->DropIndex(" . $this->phpStringLiteral($name) . ");";
                continue;
            }
            if (!$this->indexesEqualLite($oldIdx, $new[$name])) {
                $lines[] = "\$t->DropIndex(" . $this->phpStringLiteral($name) . ");";
            }
        }

        // --- adds (new or changed)
        foreach ($new as $name => $newIdx) {
            if (!isset($old[$name]) || !$this->indexesEqualLite($old[$name], $newIdx)) {
                $unique = !empty($newIdx['unique']) ? 'true' : 'false';
                $lines[] = "\$t->AddIndex(" .
                    $this->phpStringLiteral($name) . ", " .
                    $this->phpArrayShort($newIdx['columns'] ?? []) . ", " .
                    $unique .
                ");";
            }
        }

        return $lines;
    }
    private function emitForeignKeyFactory(array $fk): ?string
    {
        // Prefer the new composite shape
        $cols = $fk['columns'] ?? null;
        $refCols = $fk['refColumns'] ?? null;

        $refTable = $fk['refTable'] ?? null;
        $onDelete = $fk['onDelete'] ?? null;
        $name = $fk['name'] ?? null;

        if (!$refTable || !is_array($cols) || !is_array($refCols) || count($cols) === 0 || count($refCols) === 0) {
            return null;
        }

        $args = [
            $this->phpArrayShort(array_values($cols)),
            $this->phpStringLiteral((string)$refTable),
            $this->phpArrayShort(array_values($refCols)),
            $onDelete !== null ? $this->phpStringLiteral((string)$onDelete) : 'null',
            $name !== null ? $this->phpStringLiteral((string)$name) : 'null',
        ];

        return implode(', ', $args);
    }

    //emits helpers
    private function indexMapByName(array $idxs): array
    {
        $out = [];
        foreach ($idxs as $idx) {
            $name = $idx['name'] ?? null;
            if (!$name) continue;

            $out[$name] = [
                'name' => $name,
                'columns' => array_values($idx['columns'] ?? []),
                'unique' => !empty($idx['unique']),
            ];
        }
        return $out;
    }
    private function indexesEqualLite(array $a, array $b): bool
    {
        return ($a['unique'] ?? false) === ($b['unique'] ?? false)
            && ($a['columns'] ?? []) === ($b['columns'] ?? []);
    }
    private function indexListEqualLite(array $a, array $b): bool
    {
        $am = $this->indexMapByName($a);
        $bm = $this->indexMapByName($b);

        if (count($am) !== count($bm)) return false;

        ksort($am);
        ksort($bm);

        foreach ($bm as $name => $idx) {
            if (!isset($am[$name])) return false;
            if (!$this->indexesEqualLite($am[$name], $idx)) return false;
        }

        return true;
    }
    private function columnsEqualLite(array $a, array $b): bool
    {
        $keys = ['type','nullable','autoIncrement','unique','hasDefault','default'];
        foreach ($keys as $k) {
            if (($a[$k] ?? null) !== ($b[$k] ?? null)) return false;
        }
        return true;
    }


    /**
     * Convert "InitDb" / "AddUserAge" to "init_db", "add_user_age" for file names.
     */
    private function toSnakeCase(string $name): string
    {
        $name = preg_replace('/[^\pL\pN]+/u', '_', $name); // non letters/numbers → _
        $name = preg_replace('/([a-z0-9])([A-Z])/u', '$1_$2', $name);
        $name = strtolower($name);
        $name = trim($name, '_');
        return $name;
    }
}