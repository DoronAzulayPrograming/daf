<?php
namespace DafCore\Controllers;

class ApiController extends BaseController {

    protected function Ok($obj = null, ?array $headers = null): string{
        return $this->response->Ok($obj, $headers);
    } 
    protected function Created($obj = null, ?array $headers = null): string{
        return $this->response->Created($obj, $headers);
    }         
    protected function NoContent(): string{
        return $this->response->NoContent();
    }
            
    protected function BadRequest(?string $msg = null): string{
        return $this->response->BadRequest($msg);
    }

    protected function NotFound(?string $msg = null): string{
        return $this->response->NotFound($msg);
    }

    protected function Forbidden(?string $msg = null): string{
        return $this->response->Forbidden($msg);
    }

    protected function Unauthorized(?string $msg = null): string{
        return $this->response->Unauthorized($msg);
    }

    protected function InternalError(?string $msg = null): string{
        return $this->response->InternalError($msg);
    }
}