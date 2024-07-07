<?php
namespace App\Controllers;
use App\Middlewares\Auth;
use App\Middlewares\AllowAnonymous;
use DafCore\Controllers\Attributes\Layout;
use DafCore\Controllers\Controller;
use DafCore\Controllers\Attributes as CA;

#[Auth]
#[CA\Route]
class UsersController extends Controller {

    #[CA\HttpGet]
    function Index(){
        return $this->RenderView("Accounts/Users");
    }
}