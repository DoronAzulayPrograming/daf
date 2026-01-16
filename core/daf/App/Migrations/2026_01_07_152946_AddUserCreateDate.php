<?php

use DafDb\Migrations\Migration;
use DafDb\Migrations\MigrationBuilder;
use DafDb\Migrations\Builders\AlterTableBuilder;
use DafDb\Migrations\Services\SqlExpression;


return new class extends Migration {

    public function Up(MigrationBuilder $migrationBuilder): void
    {
        $migrationBuilder->AlterTable('Users', function(AlterTableBuilder $t) {
            $t->AddColumn(fn(AlterTableBuilder $t) => $t->Date('CreatedDate')->Nullable(false)->Default(SqlExpression::CURRENT_TIMESTAMP));
        });
    }

    public function Down(MigrationBuilder $migrationBuilder): void
    {
        $migrationBuilder->AlterTable('Users', function(AlterTableBuilder $t) {
            $t->DropColumn('CreatedDate');
        });
    }
};