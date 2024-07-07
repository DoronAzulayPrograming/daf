<?php
namespace App\Middlewares;

use DafCore\Request;
use DafCore\Response;
use DafCore\Session;
use DafCore\IViewManager;
use App\Services\JwtService;

trait AuthorizationExtantions
{
    function ValidateUserAccess(array $user_roles, array $roles) : bool {
        $valid = empty($roles) ? true : false;
        foreach ($user_roles as $role) {
            if(isset($roles[$role])){
                $valid = true;
                break;
            }
        }
        return $valid;
    }
}

class JwtAuthentioaction {
    use AuthorizationExtantions;
    function Authentication(Request $req, Response $res, JwtService $jwt, Session $session, IViewManager $vm){
        //Add Authentication logic for jwt
        $headers = $req->GetHeaders();
        if($headers['Authorization'] && str_starts_with($headers['Authorization'], 'Bearer ')){
            $token = str_replace('Bearer ', '', $headers['Authorization']);
            
            $claims = $jwt->ValidateToken($token);
            if($claims !== false){
                $req->user = $claims;
                return true;
            }
        }

        //Render regular http Unauthorized response with text
        $res->Status(Response::HTTP_UNAUTHORIZED)->Send(Response::HTTP_UNAUTHORIZED." Unauthorized");
    }

    function Authorization(Request $req, Response $res, IViewManager $vm, array $roles){
        //Add Authentication logic 
        if($this->ValidateUserAccess($req->user->roles ?? [], $roles)) return true;

        //Render regular http Forbidden response with text
        $res->Status(Response::HTTP_FORBIDDEN)->Send(Response::HTTP_FORBIDDEN." Forbidden");
    }
}

class CookieAuthentioaction{
    use AuthorizationExtantions;
    function Authentication(Request $req, Response $res, Session $session, IViewManager $vm){
        $session->Start();

        //Add Authentication logic 
        if ($session->TryGetValueFromJson('loggedInUser', $user)) {
            $req->user = $user;
            return true;
        }

        //Render Unauthorized Error page with 401 status
        $res->Status(Response::HTTP_UNAUTHORIZED);
        echo $vm->RenderView("_ErrorPage", ['Msg'=>Response::HTTP_UNAUTHORIZED." Unauthorized"]);
    }

    function Authorization(Request $req, Response $res, IViewManager $vm, array $roles){
        //Add Authorization logic 
        if($this->ValidateUserAccess($req->user->roles ?? [], $roles)) return true;

        //Render Unauthorized Error page with 401 status
        $res->Status(Response::HTTP_FORBIDDEN);
        echo $vm->RenderView("_ErrorPage", ['Msg'=>Response::HTTP_FORBIDDEN." Forbidden"]);
    }
}