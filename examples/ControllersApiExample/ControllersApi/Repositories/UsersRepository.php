<?php
namespace ControllersApi\Repositories;
use ControllersApi\Models\User;
use DafDb\JsonRepository;

class UsersRepository extends JsonRepository {
    public function __construct(){
        parent::__construct('users.json', [
            'model' => User::class,
            'auto_increment' => 'Id'
        ]);
    }

}