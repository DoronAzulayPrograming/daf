<?php
namespace App\Repositories;

use DafDb\Repository;
use DafDb\Attributes\Table;
use App\Models\User;

#[Table(Model: User::class)]
class UsersRepository extends Repository
{
    
}