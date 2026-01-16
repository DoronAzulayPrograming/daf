<?php
namespace DafDb\Migrations\Services;

use DafDb\Context;
use DafGlobals\IO\Path;
use DafDb\Migrations\Snapshot;
use DafDb\Migrations\SnapshotBuilder;
use DafDb\Migrations\Builders\TableBuilder;
use DafDb\Migrations\Services\ISqlExpression;
use DafDb\Migrations\Storage\Repositories\MigrationsRepository;

trait MigrationsSchemaHelper
{
    private function ensureMigrationsTable(Context $db): void
    {
        $mRepo = new MigrationsRepository($db);
        $mRepo->EnsureTableCreated();
    }

    /**
     * Normalize snapshot map (tableName => TableBuilder) into a clean comparable shape.
     */
    public function NormalizeSnapshotMap(array $snapMap): array
    {
        $out = [];
        foreach ($snapMap as $tableName => $t) {
            $out[(string) $tableName] = $this->NormalizeTableSnap((string) $tableName, $t);
        }

        ksort($out);
        return $out;
    }

    public function NormalizeTableSnap(string $tableName, TableBuilder $t): array
    {
        // ---------------- columns ----------------
        $cols = [];
        foreach ($t->Columns as $name => $c) {
            $cols[$name] = [
                'type' => $c->PhpType,
                'nullable' => $c->Nullable,
                'autoIncrement' => $c->AutoIncrement,
                // ✅ model unique flag
                'unique' => (bool) $c->Unique,
                'hasDefault' => $c->HasDefault,
                'default' => $c->HasDefault ? $this->normalizeDefaultSig($c->Default) : null,
                'length' => $c->Length
            ];
        }
        //ksort($cols);

        // ---------------- primary keys ----------------
        $pk = $t->Constraints->primaryKey;
        $pks = $pk ? array_values($pk->Columns) : [];

        // ---------------- foreign keys ----------------
        $fks = [];
        foreach ($t->Constraints->ForeignKeys as $fk) {

            $onDelete = $fk->OnDelete ?? null;
            if (is_string($onDelete)) {
                $onDelete = strtoupper(trim($onDelete));
                if ($onDelete === '' || $onDelete === 'RESTRICT' || $onDelete === 'NO ACTION') {
                    $onDelete = null;
                }
            }

            $arr = [
                'columns' => array_values($fk->Columns),
                'refTable' => $fk->RefTable,
                'refColumns' => array_values($fk->RefColumns),
                'onDelete' => $onDelete,
            ];

            $fkName = $this->FkDefaultName($tableName, $arr);
            $arr['name'] = $fkName;

            // Backward compatibility in snapshot (optional)
            if (count($arr['columns']) === 1) {
                $arr['column'] = $arr['columns'][0];
                $arr['refColumn'] = $arr['refColumns'][0];
            }

            $fks[$fkName] = $arr;
        }
        ksort($fks);

        // ---------------- indexes (canonical by signature) ----------------
        $idxMap = []; // sig => index array

        $addIndex = function (string $name, array $columns, bool $unique) use (&$idxMap, $tableName) {
            $colsArr = array_values($columns);
            $sig = ($unique ? '1' : '0') . '|' . implode(',', $colsArr);

            if (isset($idxMap[$sig])) {
                $existing = $idxMap[$sig];

                $autoPrefix = "IX_{$tableName}_";
                $existingIsAuto = str_starts_with($existing['name'] ?? '', $autoPrefix);
                $newIsAuto = str_starts_with($name, $autoPrefix);

                if ($existingIsAuto && !$newIsAuto) {
                    $idxMap[$sig] = ['name' => $name, 'columns' => $colsArr, 'unique' => $unique];
                } else {
                    if (($name ?? '') < ($existing['name'] ?? '')) {
                        $idxMap[$sig] = ['name' => $name, 'columns' => $colsArr, 'unique' => $unique];
                    }
                }
                return;
            }

            $idxMap[$sig] = [
                'name' => $name,
                'columns' => $colsArr,
                'unique' => $unique,
            ];
        };

        // helpers: avoid redundant FK indexes (PK already covers prefix)
        $indexCovers = function (array $indexCols, array $targetCols): bool {
            if (count($indexCols) < count($targetCols))
                return false;
            for ($i = 0; $i < count($targetCols); $i++) {
                if ($indexCols[$i] !== $targetCols[$i])
                    return false;
            }
            return true;
        };

        $hasCoveringIndex = function (array $targetCols) use (&$idxMap, $pks, $indexCovers): bool {
            if (!empty($pks) && $indexCovers($pks, $targetCols))
                return true;

            foreach ($idxMap as $ix) {
                $cols = $ix['columns'] ?? [];
                if ($indexCovers($cols, $targetCols))
                    return true;
            }
            return false;
        };

        // 1) Explicit indexes
        foreach ($t->Indexes as $idx) {
            $addIndex($idx->Name, $idx->Columns, (bool) $idx->Unique);
        }

        // 2) ColumnBuilder->Unique into UNIQUE INDEX (EF-style)
        foreach ($t->Columns as $colName => $c) {
            if (!$c->Unique)
                continue;

            $name = "UX_{$t->Name}_" . $colName;
            $addIndex($name, [$colName], true);
        }

        // 3) Unique constraints -> Unique indexes
        foreach ($t->Constraints->Uniques as $uq) {
            $addIndex($uq->Name, $uq->Columns, true);
        }

        // 4) EF-style: FK indexes by convention (skip if covered)
        foreach ($t->Constraints->ForeignKeys as $fk) {
            $fkCols = array_values($fk->Columns);
            if (empty($fkCols))
                continue;

            if ($hasCoveringIndex($fkCols)) {
                continue; // ✅ avoid redundant IX when PK/index already covers
            }

            $ixName = "IX_{$tableName}_" . implode('_', $fkCols);
            $addIndex($ixName, $fkCols, false);
        }

        // final list
        $idxs = array_values($idxMap);
        usort($idxs, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

        // ✅ reflect single-col unique indexes into column.unique
        foreach ($idxs as $ix) {
            if (($ix['unique'] ?? false) !== true)
                continue;
            $cArr = $ix['columns'] ?? [];
            if (count($cArr) !== 1)
                continue;

            $cn = $cArr[0];
            if (isset($cols[$cn])) {
                $cols[$cn]['unique'] = true;
            }
        }

        return [
            'table' => $tableName,
            'columns' => $cols,
            'primaryKeys' => $pks,
            'foreignKeys' => $fks,
            'indexes' => $idxs,
        ];
    }


    private function FkDefaultName(string $tableName, array $fk): string
    {
        $name = $fk['name'] ?? $fk['Name'] ?? null;
        if ($name)
            return (string) $name;

        $cols = $fk['columns'] ?? null;
        if (is_array($cols) && count($cols) > 0) {
            return "FK_{$tableName}_" . implode('_', $cols);
        }

        $col = $fk['column'] ?? $fk['Column'] ?? '';
        return "FK_{$tableName}_{$col}";
    }

    private function normalizeDefaultSig(mixed $v): mixed
    {
        if ($v === null)
            return null;

        // Raw SQL expression
        if ($v instanceof ISqlExpression) {
            return $v->Signature(); // "kind:*" / "raw:*"
        }

        // scalar OR already-signature string
        return $v;
    }


    private function loadSnapshotTables(string $appFolder, ?string $migrationName): array
    {
        if (!$migrationName) return [];

        $snapshotFile = Path::Combine($appFolder, 'Migrations', 'Snapshots', "{$migrationName}_Snapshot.php");
        if (!file_exists($snapshotFile)) return [];

        /** @var Snapshot $snap */
        $snap = require $snapshotFile;
        $builder = $snap->GetBuilder(new SnapshotBuilder());
        return $builder->GetTables();
    }

    private function phpStringLiteral(string $s): string { return var_export($s, true); }
    private function phpArrayShort(array $a): string
    {
        $a = array_values($a);
        $parts = array_map(fn($v) => $this->phpStringLiteral($v), $a);
        return '[' . implode(', ', $parts) . ']';
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
}