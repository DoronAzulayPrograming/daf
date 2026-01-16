<?php
namespace App\Repositories;

use DafDb\Repository;
use DafDb\Attributes\Table;
use App\Models\UserRole;

#[Table(Model: UserRole::class)]
class UserRolesRepository extends Repository
{
    
}