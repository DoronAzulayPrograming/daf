<?php
namespace ControllersApi;
require_once __DIR__ . '/vendor/autoloader.php';

use ControllersApi\Controllers\AccountsController;
use ControllersApi\Repositories\UsersRepository;
use DafCore\Application;

$app = new Application('ControllersApi');

$app->Services->AddSingleton(UsersRepository::class);

$app->Router->Get("/" ,fn() => "<h1>Hello World! With Daf</h1>");

$app->Router->AddController(AccountsController::class);

$app->Run();