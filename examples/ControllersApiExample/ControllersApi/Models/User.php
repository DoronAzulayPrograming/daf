<?php
namespace ControllersApi\Models;
use DafCore\AutoConstruct;
use DafGlobals\Attributes\Length;
use DafGlobals\Attributes\Required;

class User extends AutoConstruct {
    #[Required]
    public int $Id;
    
    #[Required]
    #[Length(min: 3)]
    public string $Username;

    #[Required]
    #[Length(min: 3)]
    public string $Password;
}
