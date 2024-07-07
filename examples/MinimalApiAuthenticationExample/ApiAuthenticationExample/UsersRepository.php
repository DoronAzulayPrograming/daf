<?php
namespace ApiAuthenticationExample;
use DafDb\JsonRepository;

/**
 * Class UsersRepository
 *
 * This class extends JsonRepository and provides a repository specifically for User objects.
 * It behaves like an array of integers to User objects.
 *
 * @var JsonRepository<int, User>
 */
class UsersRepository extends JsonRepository {
    public function __construct(){
        parent::__construct('users.json', [
            'model' => User::class,
            'auto_increment' => 'Id'
        ]);
    }
}