<?php
namespace DafCore\Controllers;
use DafCore\IResponse;

abstract class BaseController {
    protected IResponse $response;

    public function SetResponse(IResponse $response): void{
        $this->response = $response;
    }

    protected function Redirect(string $location = "", int $statusCode = 302): string{
        return $this->response->Redirect($location, $statusCode);
    }
    protected function RedirectBack(int $statusCode = 302): string{
        return $this->response->RedirectBack($statusCode);
    }
}
