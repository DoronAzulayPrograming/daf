<?php

use DafDb\Migrations\Snapshot;
use DafDb\Migrations\SnapshotBuilder;
use DafDb\Migrations\Builders\TableBuilder;


return new class extends Snapshot {

    public function GetBuilder(SnapshotBuilder $builder): SnapshotBuilder
    {
        $builder->CreateTable('Roles', function(TableBuilder $t) {
            $t->Int('Id')->Nullable(false)->AutoIncrement();
            $t->String('Name')->Nullable(false);
            $t->Constraints->PrimaryKey(['Id']);
        });
        $builder->CreateTable('Users', function(TableBuilder $t) {
            $t->Int('Id')->Nullable(false)->AutoIncrement();
            $t->String('Username')->MaxLength(60)->Nullable(false);
            $t->String('Password')->Nullable(false);
            $t->Constraints->PrimaryKey(['Id']);
        });
        $builder->CreateTable('UserRoles', function(TableBuilder $t) {
            $t->Int('UserId')->Nullable(false);
            $t->Int('RoleId')->Nullable(false);
            $t->Constraints->PrimaryKey(['UserId', 'RoleId']);
            $t->Constraints->ForeignKey(['RoleId'], 'Roles', ['Id'], 'CASCADE');
            $t->Constraints->ForeignKey(['UserId'], 'Users', ['Id'], 'CASCADE');
            $t->Index('IX_UserRoles_RoleId', ['RoleId'], false);
        });
        
        return $builder;    
    }

    public function Down(SnapshotBuilder $builder): void { }
};