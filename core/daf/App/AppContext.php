<?php 
namespace App;

use App\Repositories\RolesRepository;
use App\Repositories\SocialProfilesRepository;
use App\Repositories\UserRolesRepository;
use App\Repositories\UsersRepository;

class AppContext extends \DafDb\DbContext {
    public RolesRepository $Roles;
    public UsersRepository $Users;
    public SocialProfilesRepository $SocialProfiles;
    public UserRolesRepository $UserRoles;
}