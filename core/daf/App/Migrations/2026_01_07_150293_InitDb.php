<?php

use DafDb\Migrations\Migration;
use DafDb\Migrations\MigrationBuilder;
use DafDb\Migrations\Builders\TableBuilder;


return new class extends Migration {

    public function Up(MigrationBuilder $migrationBuilder): void
    {
        $migrationBuilder->CreateTable('Roles', function(TableBuilder $t) {
            $t->Int('Id')->Nullable(false)->AutoIncrement();
            $t->String('Name')->Nullable(false);
            $t->Constraints->PrimaryKey(['Id']);
        });
        $migrationBuilder->CreateTable('Users', function(TableBuilder $t) {
            $t->Int('Id')->Nullable(false)->AutoIncrement();
            $t->String('Username')->MaxLength(60)->Nullable(false);
            $t->String('Password')->Nullable(false);
            $t->Constraints->PrimaryKey(['Id']);
        });
        $migrationBuilder->CreateTable('UserRoles', function(TableBuilder $t) {
            $t->Int('UserId')->Nullable(false);
            $t->Int('RoleId')->Nullable(false);
            $t->Constraints->PrimaryKey(['UserId', 'RoleId']);
            $t->Constraints->ForeignKey(['RoleId'], 'Roles', ['Id'], 'CASCADE');
            $t->Constraints->ForeignKey(['UserId'], 'Users', ['Id'], 'CASCADE');
            $t->Index('IX_UserRoles_RoleId', ['RoleId'], false);
        });
    }

    public function Down(MigrationBuilder $migrationBuilder): void
    {
        $migrationBuilder->DropTable('UserRoles');
        $migrationBuilder->DropTable('Users');
        $migrationBuilder->DropTable('Roles');
    }
};