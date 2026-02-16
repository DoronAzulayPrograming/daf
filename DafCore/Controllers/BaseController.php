<?php
namespace DafCore\Controllers;
use DafCore\IResponse;

abstract class BaseController {
    protected IResponse $response;

    function SetResponse(IResponse $response){
        $this->response = $response;
    }
}
