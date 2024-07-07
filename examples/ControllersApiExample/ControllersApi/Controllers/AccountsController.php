<?php
namespace ControllersApi\Controllers;
use ControllersApi\Repositories\UsersRepository;
use DafCore\Controllers\ApiController;
use DafCore\Controllers\Attributes as CA;
use ControllersApi\Models\User;
use ControllersApi\Models\UserCreateModel;
use DafGlobals\Attributes\Validate;

#[CA\Route(Prefix:"Api")]
class AccountsController extends ApiController {
    
    public function __construct(
        public UsersRepository $usersRepo
    ){}
    
    #[CA\HttpGet]
    function Index(){
        return $this->Ok($this->usersRepo);
    }

    #[CA\HttpGet(":id")]
    function Get(int $id){
        $user = $this->usersRepo->Find(fn(User $u)=> $u->Id === $id);
        if(is_null($user))
            return $this->NotFound("User not found");
    
        return $this->Ok($user);
    }

    #[CA\HttpPost]
    function Create(#[Validate] UserCreateModel $user){
        if($this->usersRepo->Any(fn($u) => $u->Username === $user->Username))
            return $this->BadRequest("Username already exists");

        $this->usersRepo->Add($user);
        $this->usersRepo->SaveData();
        return $this->Ok($this->usersRepo->Last());
    }

    #[CA\HttpPut(":id")]
    function Update(int $id, #[Validate] UserCreateModel $user){
        if($user->Id !== $id)
            return $this->BadRequest("Id in body is different from id in url");

        /** @var User $userInDb */
        $userInDb = $this->usersRepo->Find(fn(User $u)=> $u->Id === $id);
        if(is_null($userInDb))
            return $this->NotFound("User not found");

        $userInDb->Username = $user->Username;
        $userInDb->Password = $user->Password;
        $this->usersRepo->SaveData();
        return $this->NoContent();
    }

    #[CA\HttpDelete(":id")]
    function Delete(int $id){
        if(!$this->usersRepo->Any(fn(User $u)=> $u->Id === $id))
            return $this->NotFound("User not found");

        $this->usersRepo->Remove(fn(User $u)=> $u->Id === $id);
        $this->usersRepo->SaveData();
        return $this->NoContent();
    }
}
