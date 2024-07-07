<?php
namespace ApiAuthenticationExample;
require_once __DIR__ . '/vendor/autoloader.php';

use DafCore\IResponse;
use DafCore\Request;
use DafCore\Response;
use DafGlobals\Attributes\Validate;

$app = new App();

$app->Services->AddSingleton(UsersRepository::class);
$app->Services->AddSingleton(JwtService::class, fn()=> new JwtService("askljaskljslkajs", "HS256"));

//** @var UsersRepository $ur */
//$ur = $app->Services->GetOne(UsersRepository::class);
// $ur->Add(new User(1, "admin", "123", "Admin"));
// $ur->SaveData();

$Authentication = function(Request $req, Response $res, JwtService $jwt, $next){
    //Add Authentication logic for jwt
    $headers = $req->GetHeaders();
    if($headers['Authorization'] && str_starts_with($headers['Authorization'], 'Bearer ')){
        $token = str_replace('Bearer ', '', $headers['Authorization']);
        
        $claims = $jwt->ValidateToken($token);
        if($claims !== false){
            $req->User = $claims;
            $next();
            return;
        }
    }

    //Render regular http Unauthorized response with text
    $res->Unauthorized(Response::HTTP_UNAUTHORIZED." Unauthorized");
};

$Authorization = function(string $roles){
    return function(Request $req, Response $res, $next) use ($roles) {
        //Add Authentication logic for jwt
        $rolesAllowed = explode(",", $roles);
        $user_roles = explode(",", $req->User->Roles);
        $intersect = array_intersect($rolesAllowed, $user_roles);
        if(count($intersect) > 0){
            $next();
            return;
        }
        //Render regular http Forbidden response with text
        $res->Forbidden(Response::HTTP_FORBIDDEN." Forbidden");
    };
};

$app->Get("/" , fn() => "<h1>Hello World! With Daf</h1>");

$app->SetRouteBasePath("/Api");
$app->Get("/Accounts", fn(Response $res, UsersRepository $usersRepo) => $res->Ok($usersRepo->Map(fn(User $u)=> $u->GetSerialize())));

$app->Get("/Accounts/:id", function(int $id, Response $res, UsersRepository $usersRepo){
    /** @var User */
    $user = $usersRepo->Find(fn(User $u)=> $u->Id === $id);
    if(is_null($user))
        return $res->NotFound("User not found");

    $res->Ok($user->GetSerialize());
});

$app->Post("/Accounts", $Authentication, $Authorization("Admin,SubAdmin"), function(Response $res, #[Validate] UserCreateModel $user, UsersRepository $usersRepo){
    if($usersRepo->Any(fn($u) => $u->Username === $user->Username))
        return $res->BadRequest("Username already exists");
    
    $usersRepo->Add($user);
    $usersRepo->SaveData();
    $res->Created($usersRepo->Last()->GetSerialize());
});

$app->Put("/Accounts/:id", $Authentication, $Authorization("Admin,SubAdmin"), function(int $id, Response $res, #[Validate] User $user, UsersRepository $usersRepo){
    if($user->Id !== $id)
        return $res->BadRequest("Id in body is different from id in url");

    /** @var User $userInDb */
    $userInDb = $usersRepo->Find(fn(User $u)=> $u->Id === $id);
    if(is_null($userInDb))
        return $res->NotFound("User not found");

    $userInDb->Username = $user->Username;
    $userInDb->Password = $user->Password;
    $usersRepo->SaveData();
    $res->NoContent();
});

$app->Delete("/Accounts/:id", $Authentication, $Authorization("Admin"), function(int $id, Response $res, UsersRepository $usersRepo){
    if(!$usersRepo->Any(fn(User $u)=> $u->Id === $id))
        return $res->NotFound("User not found");

    $usersRepo->Remove(fn(User $u)=> $u->Id === $id);
    $usersRepo->SaveData();
    $res->NoContent();
});

$app->Post("/Accounts/Login" , function(IResponse $res, JwtService $jwt, #[Validate] LoginModel $body, UsersRepository $usersRepo){
        
    $user = $usersRepo->Find(fn(User $u)=> $u->Username === $body->Username);
    if(is_null($user))
        return $res->NotFound("User not found");

    if($user->Password !== $body->Password)
        return $res->BadRequest("Invalid password");

    $user_payload = $user->GetSerialize();

    $obj = [];
    $obj['token'] = $jwt->GenerateToken($user_payload);
    $obj['expires'] = $jwt->GetLastExpirationTime();

    return $res->Ok($obj);
});

$app->Run();