<?php
namespace ApiExample;
require_once __DIR__ . '/vendor/autoloader.php';

use DafCore\Application;
use DafCore\Response;
use DafGlobals\Attributes\Validate;

$app = new Application('ApiExample');

$app->Services->AddSingleton(UsersRepository::class);

$app->Get("/" ,fn() => "<h1>Hello World! With Daf</h1>");

$app->Get("/Api/Accounts", fn(Response $res, UsersRepository $usersRepo) => $res->Ok($usersRepo->Map(fn(User $u)=> $u->GetSerialize())));

$app->Get("/Api/Accounts/:id", function(int $id, Response $res, UsersRepository $usersRepo){
    /** @var User $user */
    $user = $usersRepo->Find(fn(User $u)=> $u->Id === $id);
    if(is_null($user))
        return $res->NotFound("User not found");

    $res->Ok($user->GetSerialize());
});

$app->Post("/Api/Accounts", function(Response $res, #[Validate] UserCreateModel $user, UsersRepository $usersRepo){
    if($usersRepo->Any(fn($u) => $u->Username === $user->Username))
        return $res->BadRequest("Username already exists");

    $usersRepo->Add($user);
    $usersRepo->SaveData();
    
    /** @var User $user */
    $user = $usersRepo->Last();
    $res->Created($user->GetSerialize());
});

$app->Put("/Api/Accounts/:id", function(int $id, Response $res, #[Validate] User $user, UsersRepository $usersRepo){
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

$app->Delete("/Api/Accounts/:id", function(int $id, Response $res, UsersRepository $usersRepo){
    if(!$usersRepo->Any(fn(User $u)=> $u->Id === $id))
        return $res->NotFound("User not found");

    $usersRepo->Remove(fn(User $u)=> $u->Id === $id);
    $usersRepo->SaveData();
    $res->NoContent();
});

$app->Run();