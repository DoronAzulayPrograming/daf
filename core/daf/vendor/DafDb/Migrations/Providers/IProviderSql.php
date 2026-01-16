<?php
namespace DafDb\Migrations\Providers;

use DafDb\Context;
use DafDb\Migrations\Builders\TableBuilder;
use DafDb\Migrations\Builders\AlterTableBuilder;

interface IProviderSql
{
    /** @return string[] */
    public function CompileCreateTable(TableBuilder $t): array;

    /** @return string[] */
    public function CompileDropTable(string $tableName): array;

    public function CompileAlterTable(AlterTableBuilder $alter, ?array $oldSchema = null): array;
}
