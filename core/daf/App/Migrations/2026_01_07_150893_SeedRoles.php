<?php

use App\Repositories\RolesRepository;
use DafDb\Migrations\Migration;
use DafDb\Migrations\MigrationBuilder;
use DafDb\Migrations\Builders\TableBuilder;
use DafDb\Migrations\Builders\AlterTableBuilder;

return new class extends Migration {

    public function Up(MigrationBuilder $migrationBuilder): void
    {
        $ctx = $migrationBuilder->GetContext()->Table(RolesRepository::class);
        $ctx->Add(['Name' => 'Admin']);
        $ctx->Add(['Name' => 'Member']);
        $ctx->SaveChanges();
    }

    public function Down(MigrationBuilder $migrationBuilder): void
    {
        $ctx = $migrationBuilder->GetContext()->Table(RolesRepository::class);
        $ctx->Remove(['Id' => 1]);
        $ctx->Remove(['Id' => 2]);
        $ctx->SaveChanges();
    }
};