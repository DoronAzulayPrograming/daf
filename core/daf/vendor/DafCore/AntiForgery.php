<?php
namespace DafCore;

class AntiForgery {
    public function __construct(
        private Session $session,
        private Request $request
        ) {}

    function RegisterToken():void{
        $this->session->Start();
        $token = md5(uniqid(mt_rand(), true));
        $this->session->Set("CSRF-token", $token);
    }
    function GetToken() : string{
        $this->session->Start();
        if($this->session->TryGetValue("CSRF-token", $token)){
            return $token;
        }
        return "";
    }
    function ValidateToken():bool{
        $this->session->Start();

        if($this->session->TryGetValue("CSRF-token", $token)){
            $req_parmas = $this->request->GetBodyArray();
            if(isset($req_parmas['csrft']) && $token === $req_parmas['csrft']){
                return true;
            }
        }
        return false;
    }
}