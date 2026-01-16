<?php 
namespace App\Models;
use DafCore\AutoConstruct;

class Role extends AutoConstruct {
    
    #[\DafDb\Attributes\PrimaryKey]
    #[\DafDb\Attributes\AutoIncrement]
    public int $Id;
    
    public string $Name;
}