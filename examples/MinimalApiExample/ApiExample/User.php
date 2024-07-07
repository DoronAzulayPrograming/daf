<?php
namespace ApiExample;
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

     /**
     *  Serialized user data
     *
     * @return array<string, mixed> array<string, mixed>
     * 
     * where string is the key and mixed is the value
     */
    function GetSerialize() : array {
        $arr = (array) $this;
        unset($arr["Password"]);
        return $arr;
    }
}
