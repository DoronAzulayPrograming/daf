<?php

use App\Repositories\UserRolesRepository;
use App\Repositories\UsersRepository;
use DafDb\Migrations\Migration;
use DafDb\Migrations\MigrationBuilder;

return new class extends Migration {

    public function Up(MigrationBuilder $migrationBuilder): void
    {
        $ctx = $migrationBuilder->GetContext()->Table(UsersRepository::class);
        $ctx->Add(['Username' => 'dufa13', 'Password' => password_hash("123", PASSWORD_DEFAULT)]);
        $ctx->SaveChanges();
        $ctx->Table(UserRolesRepository::class)->Add(['UserId' => 1, 'RoleId' => 1]);
        $ctx->SaveChanges();
    }

    public function Down(MigrationBuilder $migrationBuilder): void
    {
        $ctx = $migrationBuilder->GetContext();
        $ctx->Table(UserRolesRepository::class)->Remove(['UserId' => 1, 'RoleId' => 1]);
        $ctx->SaveChanges();
        $ctx->Table(UsersRepository::class)->Remove(['Id' => 1]);
        $ctx->SaveChanges();
    }
};