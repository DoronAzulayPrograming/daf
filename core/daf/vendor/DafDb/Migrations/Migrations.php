<?php
namespace DafDb\Migrations;

use DafDb\DbContext;
use DafDb\Migrations\Services\MigrationGenerator;
use DafDb\Migrations\Services\MigrationRunner;

class Migrations
{
    public function Migrate(DbContext $dbContext, string $appFolder){
        (new MigrationRunner())->Migrate($dbContext, $appFolder);
    }

    public function Rollback(DbContext $dbContext, string $appFolder){
        (new MigrationRunner())->Rollback($dbContext, $appFolder);
    }

    public function Generate(DbContext $dbContext, string $migrationName, string $appFolder){
        (new MigrationGenerator())->Generate($dbContext,$migrationName, $appFolder);
    }
}