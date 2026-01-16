<?php
namespace DafDb\Migrations\Services;

use DafDb\Migrations\SnapshotBuilder;
use DafDb\Migrations\MigrationBuilder;
use DafDb\Migrations\Builders\TableBuilder;
use DafDb\Migrations\Builders\AlterTableBuilder;
use DafDb\Migrations\Builders\Definitions\IndexDef;

final class SnapshotRunner extends SnapshotBuilder {

    public function DropTable(string $tableName): void
    {
        unset($this->tables[$tableName]);
    }

    public function AlterTable(string $tableName, callable $alter): void
    {
        $plan = new AlterTableBuilder($tableName);
        $alter($plan);

        if (!$plan->HasChanges()) return;

        $table = $this->tables[$tableName];
        $this->applyAlter($table, $plan);
    }

    public function ApplyChanges(MigrationBuilder $builder){
        list($tablesToCreate, $tablesToDrop, $tablesToAlter) = $builder->GetChanges();
        foreach($tablesToCreate as $tableName => $build){
            $this->CreateTable($tableName, $build);
        }
        foreach($tablesToDrop as $_ => $tableName){
            $this->DropTable($tableName);
        }
        foreach($tablesToAlter as $tableName => $build){
            $this->AlterTable($tableName, $build);
        }
    }

    /**
     * Write snapshot file
     */
    public function Save(string $appFolder, string $migrationName){
        $fileDir = $appFolder . '/Migrations/Snapshots';
        $fileBase  = $migrationName . '_Snapshot';
        $filePath  = $fileDir . '/' . $fileBase . '.php';

        if (!is_dir($fileDir)) mkdir($fileDir, 0777, true);

        $php = (string)$this;
        file_put_contents($filePath, $php);
    }

    public function Delete(string $appFolder, string $migrationName){
        $fileDir = $appFolder . '/Migrations/Snapshots';
        $fileBase  = $migrationName . '_Snapshot';
        $filePath  = $fileDir . '/' . $fileBase . '.php';

        if(file_exists($filePath)) unlink($filePath);
    }

    private function applyAlter(TableBuilder $table, AlterTableBuilder $alter): void
    {
        $originalName = $table->Name;
        if ($alter->RenameTo !== null) {
            $table->Name = $alter->RenameTo;
        }

        if ($table->Name !== $originalName) {
            unset($this->tables[$originalName]);
            $this->tables[$table->Name] = $table;
        }

        // rename columns
        foreach ($alter->RenameColumns as $old => $new) {
            if (!isset($table->Columns[$old])) continue;

            $table->Columns[$new] = $table->Columns[$old];
            $table->Columns[$new]->Name = $new;
            unset($table->Columns[$old]);

            // rename in PK
            if ($table->Constraints->primaryKey) {
                $table->Constraints->primaryKey->Columns =
                    array_map(fn($c) => $c === $old ? $new : $c, $table->Constraints->primaryKey->Columns);
            }

            // rename in FKs
            foreach ($table->Constraints->ForeignKeys as $fk) {
                $fk->Columns = array_map(fn($c) => $c === $old ? $new : $c, $fk->Columns);
            }

            // rename in indexes
            foreach ($table->Indexes as $idx) {
                $idx->Columns = array_map(fn($c) => $c === $old ? $new : $c, $idx->Columns);
            }

            // rename in unique constraints
            foreach ($table->Constraints->Uniques as $unique) {
                $unique->Columns = array_map(fn($c) => $c === $old ? $new : $c, $unique->Columns);
            }
        }

        // drop columns
        foreach ($alter->DropColumns as $col) {
            unset($table->Columns[$col]);

            if ($table->Constraints->primaryKey) {
                $table->Constraints->primaryKey->Columns =
                    array_values(array_filter($table->Constraints->primaryKey->Columns, fn($c) => $c !== $col));
            }

            // drop FK that uses this column
            $table->Constraints->ForeignKeys = array_filter(
                $table->Constraints->ForeignKeys,
                fn($fk) => !in_array($col, $fk->Columns, true)
            );

            // drop indexes that reference it
            $table->Indexes = array_filter(
                $table->Indexes,
                fn(IndexDef $idx) => !in_array($col, $idx->Columns, true)
            );
        }

        foreach ($table->Constraints->Uniques as $idx => $unique) {
            $unique->Columns = array_values(array_filter(
                $unique->Columns,
                fn($c) => $c !== $col
            ));
            if (empty($unique->Columns)) {
                unset($table->Constraints->Uniques[$idx]);
            }
        }


        // add columns
        foreach ($alter->AddColumns as $colBuilder) {
            $table->Columns[$colBuilder->Name] = $colBuilder;
        }

        // modify columns
        foreach ($alter->ModifyColumns as $colBuilder) {
            if (isset($table->Columns[$colBuilder->Name])) {
                $table->Columns[$colBuilder->Name] = $colBuilder;
            }
        }

        // PK changes
        if ($alter->PrimaryKeyNeedToDrop) {
            $table->Constraints->primaryKey = null;
        }
        if ($alter->PrimaryKey !== null) {
            $table->Constraints->primaryKey = $alter->PrimaryKey;
        }

        // foreign key drops/adds
        foreach ($alter->DropForeignKeys as $name) {
            $table->Constraints->ForeignKeys = array_filter(
                $table->Constraints->ForeignKeys,
                fn($fk) => $fk->Name !== $name
            );
        }

        foreach ($alter->AddForeignKeys as $fk) {
            $table->Constraints->ForeignKeys[$fk->Name] = $fk;
        }

        // indexes
        foreach ($alter->DropIndexes as $name) {
            $table->Indexes = array_values(array_filter(
                $table->Indexes,
                fn(IndexDef $idx) => $idx->Name !== $name
            ));
        }
        foreach ($alter->RenameIndexes as $old => $newName) {
            foreach ($table->Indexes as $idx) {
                if ($idx->Name === $old) {
                    $idx->Name = $newName;
                    break;
                }
            }
        }
        foreach ($alter->AddIndexes as $idxDef) {
            $table->Indexes[] = $idxDef;
        }
    }
}
