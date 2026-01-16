<?php
namespace App\Repositories;

use DafDb\Repository;
use DafDb\Attributes\Table;
use App\Models\Role;

#[Table(Model: Role::class)]
class RolesRepository extends Repository
{
    
}