<?php
namespace DafDb\Migrations\Services;

use DafDb\Context\Context;
use DafDb\Context\DbContext;
use DafDb\Migrations\SnapshotBuilder;
use DafDb\Migrations\MigrationBuilder;
use DafDb\Migrations\Builders\TableBuilder;
use DafDb\Migrations\Providers\IProviderSql;
use DafDb\Migrations\Builders\AlterTableBuilder;
use DafDb\Migrations\Providers\MysqlProviderSql;
use DafDb\Migrations\Providers\SqliteProviderSql;
use DafDb\Migrations\Storage\DbSet\MigrationsDbSet;
use DafDb\Migrations\Storage\Repositories\MigrationsRepository;
use DafDb\Query\SqlCommand;
use Exception;

class MigrationRunner {
    use MigrationsSchemaHelper;


    public function Migrate(DbContext $dbContext, string $appFolder): void
    {
        $context  = $dbContext->context;
        $provider = $dbContext->context->GetSqlProvider();
        $this->ensureMigrationsTable($context);

        $files    = $this->orderedMigrationFiles($appFolder);
        $applied  = $this->getAppliedMigrationNames($context);
        $pending  = $this->pendingMigrations($files, $applied);

        if (!$pending) {
            echo "No Changes Detected.\n";
            return;
        }

        $batch    = $this->getNextBatchNumber($context);
        $snapshot = $this->bootstrapSnapshot($appFolder, end($applied) ?: null);

        try {
            foreach ($pending as [$name, $file]) {
                echo "Running migration: {$name}\n";

                $builder   = $this->createModelBuilder($dbContext);
                $migration = require $file;
                $migration->Up($builder);

                $this->applyMigration($context, $provider, $builder, $snapshot);
                $snapshot->ApplyChanges($builder->GetChanges());
                $snapshot->Save($appFolder, $name);

                $this->recordMigration($context, $name, $batch);
            }
            echo "Migrations completed.\n";
        } catch (\Throwable $e) {
            echo "Migration failed: {$e->getMessage()}\n";
            throw $e;
        }
    }

    public function Rollback(DbContext $dbContext, string $appFolder): void
    {
        $context  = $dbContext->context;
        $provider = $context->GetSqlProvider();
        $this->ensureMigrationsTable($context);

        $batch      = $this->latestBatchNumber($context);
        $migrations = $this->migrationsInBatch($context, $batch);

        if (!$migrations) {
            echo "Nothing to rollback.\n";
            return;
        }

        foreach ($migrations as $name) {
            $file = "{$appFolder}/Migrations/{$name}.php";
            if (!file_exists($file)) {
                echo "WARNING: file for migration {$name} not found. Skipping\n";
                continue;
            }

            echo "Rolling back: {$name}\n";

            $snapshot = $this->bootstrapSnapshot($appFolder, $name);
            $builder  = $this->createModelBuilder($dbContext);
            $migration = require $file;
            $migration->Down($builder);

            $this->applyMigration($context, $provider, $builder, $snapshot);
            $snapshot->ApplyChanges($builder->GetChanges());

            $snapshot->Delete($appFolder, $name);
            $context->Table(MigrationsDbSet::class)->Remove(fn($m) => $m->Name == $name);
            $context->SaveChanges();
        }

        echo "Rollback completed.\n";
    }

    /* ---------- core helpers ---------- */

    private function applyMigration(Context $context, IProviderSql $provider, MigrationBuilder $builder, SnapshotBuilder $snapshot): void
    {

        $actions = $builder->GetChanges();

        foreach ($actions as $action) {
            switch ($action['type']) {
                case 'create_table':
                    $name = $action['payload']['name'];
                    $build = $action['payload']['build'];

                    $table = new TableBuilder($name);
                    $build($table);
                    foreach ($provider->CompileCreateTable($table) as $sql) {
                        $context->Execute(new SqlCommand($sql));
                    }
                    break;

                case 'drop_table':
                    $name = $action['payload']['name'];
                    foreach ($provider->CompileDropTable($name) as $sql) {
                        $context->Execute(new SqlCommand($sql));
                    }
                    break;

                case 'alter_table':
                    $name = $action['payload']['name'];
                    $plan = $action['payload']['alter'];

                    $alter = new AlterTableBuilder($name);
                    $plan($alter);
                    if (!$alter->HasChanges()) break;

                    $oldTable = $snapshot->GetTable($name);
                    $oldSchema = $oldTable ? $this->NormalizeTableSnap($name, $oldTable) : null;

                    $sqls = $provider->CompileAlterTable($alter, $oldSchema);
                    $this->executeSqlList($context, $sqls);
                    break;

                case 'raw_sql':
                    $context->Execute(new SqlCommand($action['payload']['sql']));
                    break;
                default: throw new Exception("unknown action type.!");
            }
        }

    }

    private function executeSqlList(Context $context, array $sqls): void
    {
        $hasRebuild = $this->planHasSqliteRebuild($sqls);

        try {
            foreach ($sqls as $sql) {
                if ($sql === '-- DAFDB_SQLITE_REBUILD_BEGIN' || $sql === '-- DAFDB_SQLITE_REBUILD_END') continue;
                $context->Execute(new SqlCommand($sql));
            }
        } catch (\Throwable $e) {
            if ($hasRebuild) {
                $context->Execute(new SqlCommand("ROLLBACK;"));
                $context->Execute(new SqlCommand("PRAGMA foreign_keys=ON;"));
            }
            throw $e;
        } finally {
            if ($hasRebuild) {
                try { $context->Execute(new SqlCommand("PRAGMA foreign_keys=ON;")); } catch (\Throwable) {}
            }
        }
    }

    /* ---------- snapshot helpers ---------- */

    private function bootstrapSnapshot(string $appFolder, ?string $migrationName): SnapshotRunner
    {   
        $tables = $this->loadSnapshotTables($appFolder, $migrationName);
        return new SnapshotRunner($tables);
    }

    /* ---------- bookkeeping helpers ---------- */

    private function orderedMigrationFiles(string $appFolder): array
    {
        $files = glob("{$appFolder}/Migrations/*.php") ?: [];
        sort($files);
        return $files;
    }

    private function pendingMigrations(array $files, array $applied): array
    {
        $pending = [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (!in_array($name, $applied, true)) {
                $pending[] = [$name, $file];
            }
        }
        return $pending;
    }

    private function migrationsInBatch(Context $context, int $batch): array
    {
        return $context->Table(MigrationsDbSet::class)->RowToArray(false)
            ->OrderByDescending(fn($m) => $m->Id)
            ->Where(fn($m) => $m->Batch == $batch)
            ->Map(fn($m) => $m->Name)
            ->ToArray();
    }

    private function latestBatchNumber(Context $context): int
    {
        $table = $context->Table(MigrationsDbSet::class);
        $stmt = $table->Execute(new SqlCommand("SELECT MAX(batch) AS batch FROM daf_migrations"));
        $row = $stmt->fetch();
        return $row && $row['batch'] ? (int)$row['batch'] : 0;
    }

    private function recordMigration(Context $context, string $name, int $batch): void
    {
        $table = $context->Table(MigrationsDbSet::class);
        $table->Add([
            'Name'      => $name,
            'Batch'     => $batch,
            'AppliedAt' => date('Y-m-d H:i:s'),
            'Snapshot'  => ''
        ]);
        $context->SaveChanges();
    }

    /* ---------- provider helpers ---------- */


    private function createModelBuilder(DbContext $context): MigrationBuilder
    {
        return new MigrationBuilder($context);
    }

    private function getNextBatchNumber(Context $db): int
    {
        $migrationsRepo = new MigrationsDbSet($db);
        return $migrationsRepo->GetLastBatchNumber() + 1;
    }
    private function getAppliedMigrationNames(Context $db): array
    {
        return $db->Table(MigrationsDbSet::class)
            ->OrderBy(fn($x)=>$x->Id)
            ->Map(fn($m) => $m->Name)->ToArray();
    }
    private function planHasSqliteRebuild(array $sqls): bool
    {
        foreach ($sqls as $sql) {
            if ($sql === '-- DAFDB_SQLITE_REBUILD_BEGIN') {
                return true;
            }
        }
        return false;
    }
}