<?php
namespace App\Models;

use DafCore\AutoConstruct;
use DafDb\OnDeleteAction;

class SocialProfile extends AutoConstruct {
    #[\DafDb\Attributes\PrimaryKey]
    #[\DafDb\Attributes\AutoIncrement]
    public int $Id;
    
    #[\DafDb\Attributes\ForeignKey("Users", "Id", OnDeleteAction::CASCADE)]
    public int $UserId;
    
    public string $Github;
    public string $Youtube;
    public string $Facebook;
}