<?php
namespace ApiAuthenticationExample;

class UserCreateModel extends User {
    public int $Id;
    public string $Roles;

    function onAfterLoad(){
        $this->Id = 0;
        $this->Roles = "";
    }
}