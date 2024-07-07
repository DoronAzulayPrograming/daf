<?php
namespace App;

use App\Models\User;
use App\Services\JwtService;
use DafCore\Application;
use DafCore\Controllers\Attributes\AntiForgeryValidateToken;
use DafCore\IResponse;
use DafCore\IViewManager;
use DafCore\Request;
use DafCore\Session;
use DafCore\AntiForgery;
use DafCore\Attributes\Validate;

class ApplicationEx extends Application {

    public function __construct(string $baseFolder = 'App' ,bool $isRelease = false) {
        parent::__construct($baseFolder, $isRelease);

        $this->Services->AddSingleton(AuthenticationSchemes::class);
        $this->Services->AddSingleton(JwtService::class, fn()=> new JwtService("askljaskljslkajs", "HS256"));
    }

    function MapIdentityRoutes() {
        $this->AddGlobalMiddleware(function(Request $req, Session $session, callable $next){
            if ($session->TryGetValueFromJson('loggedInUser', $user)) {
                $req->user = $user;
            }
            $next();
        });

        $this->Router->Get("/Accounts/Login" ,fn(IViewManager $vm) => $vm->RenderView("/Accounts/Login"));
        
        $this->Router->Post("/Accounts/Login" , #[AntiForgeryValidateToken] function(IResponse $res, IViewManager $vm, Session $session, #[Validate] User $body){
            // Clear the session
            $session->Clear();

            //$body->Roles[] = "Admin";
            $session->Set('loggedInUser', $body);
            $res->Redirect("/");
        });
        
        $this->Router->Post("/Accounts/Logout" ,function(IResponse $res,  Session $session){
        
            // Clear the cookie
            setcookie('loggedInUser', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        
            // Clear the session
            $session->Destroy();
            
            $res->RedirectBack();
        });

        $this->Router->Post("/Api/Accounts/Login" ,function(IResponse $res, JwtService $jwt, #[Validate] User $body){
        
            $obj = [];
            $obj['token'] = $jwt->GenerateToken((array) $body);
            $obj['expires'] = $jwt->GetLastExpirationTime();
        
            return $res->Json($obj);
        });
    }

    function AddAuthentioactionScheme(string $name, string $class_name){
        try {
            $authSchemes = (fn() : AuthenticationSchemes => $this->Services->GetOne(AuthenticationSchemes::class))();
            $authSchemes->sceames[$name] = new $class_name(); 
        } catch (\Throwable $th) {
            echo $th;
            die();
        }
    }
}

class AuthenticationSchemes{
    public array $sceames = [];
}