<?php
namespace App\Repositories;

use DafDb\Repository;
use DafDb\Attributes\Table;
use App\Models\SocialProfile;

#[Table(Model: SocialProfile::class)]
class SocialProfilesRepository extends Repository
{
    
}