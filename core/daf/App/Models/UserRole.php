<?php 
namespace App\Models;
use DafCore\AutoConstruct;
use DafDb\OnDeleteAction;

class UserRole extends AutoConstruct {
    
    #[\DafDb\Attributes\PrimaryKey]
    #[\DafDb\Attributes\ForeignKey("Users", "Id", OnDeleteAction::CASCADE)]
    public int $UserId;
    
    #[\DafDb\Attributes\PrimaryKey]
    #[\DafDb\Attributes\ForeignKey("Roles", "Id", OnDeleteAction::CASCADE)]
    public int $RoleId;

    #[\DafDb\Attributes\DbInclude("Users", "Users.Id = UserRoles.UserId")]
    public User $User;
    #[\DafDb\Attributes\DbInclude("Roles", "Roles.Id = UserRoles.RoleId")]
    public Role $Role;
}