<?php 
namespace App\Models;
use DafCore\AutoConstruct;

class User extends AutoConstruct {
    
    #[\DafDb\Attributes\PrimaryKey]
    #[\DafDb\Attributes\AutoIncrement]
    public int $Id;
    
    #[\DafCore\Attributes\Required]
    public string $Username;

    #[\DafCore\Attributes\Required]
    public string $Password;

    public string $Roles;
}