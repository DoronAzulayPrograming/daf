<?php
/** @var \DafCore\IComponent $this  */
/** @var \DafCore\Request $req  */
/** @var string $Roles  */
$Roles = $this->Parameter("Roles");

$req = $this->Inject(\DafCore\Request::class);
if(isset($req->user->Username)){
    $valid = true;
    if(isset($Roles)){
        $valid = false;
        //Authorization logic
        $temp = explode(",", $Roles);
        $roles = [];

        foreach ($temp as $role) {
            if(!empty($role))
                $roles[$role] = 1;
        }
        foreach($req->user->Roles as $role){
            if(isset($roles[$role])){
                $valid = true;
                break;
            }
        }
    }
    if($valid){
        $this->RenderChildrenOfType("App\Views\Components\Auth\Authorized");
        return;
    }
}
$this->RenderChildrenOfType("App\Views\Components\Auth\NotAuthorized");