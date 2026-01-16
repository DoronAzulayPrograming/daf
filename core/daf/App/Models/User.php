<?php 
namespace App\Models;
use DafCore\AutoConstruct;
use DafDb\Migrations\Services\SqlExpression;
use DafGlobals\Collections\Collection;
use DafGlobals\Collections\ICollection;
use DafGlobals\Dates\DateOnly;

class User extends AutoConstruct {
    
    #[\DafDb\Attributes\PrimaryKey]
    #[\DafDb\Attributes\AutoIncrement]
    public int $Id;

    #[\DafDb\Attributes\MaxLength(60)]
    public string $Email;
    public string $Password;
    public ?string $Phone;

    #[\DafDb\Attributes\DefaultValue(SqlExpression::CURRENT_TIMESTAMP)]
    public DateOnly $CreatedDate;


    #[\DafDb\Attributes\DbInclude("UserRoles", "UserRoles.UserId = Users.Id", UserRole::class)]
    public ICollection $Roles;

    public function SetRoles(array $userRoles): void{
        $this->Roles = (new Collection($userRoles))->Map(fn($ur) => $ur->Role);
    }
}