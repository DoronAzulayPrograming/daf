<?php
namespace DafCore\Controllers;

class ApiController extends BaseController {
    protected function Ok($obj = null, array $headers = null){
        return $this->response->Ok($obj, $headers);
    } 
    public function Created($obj = null, array $headers = null){
        return $this->response->Created($obj, $headers);
    }         
    public function NoContent(){
        return $this->response->NoContent();
    }
            
    public function BadRequest($msg = null){
        return $this->response->BadRequest($msg);
    }

    public function NotFound($msg = null){
        return $this->response->NotFound($msg);
    }

    public function Forbidden($msg = null){
        return $this->response->Forbidden($msg);
    }

    public function Unauthorized($msg = null){
        return $this->response->Unauthorized($msg);
    }

    public function InternalError($msg = null){
        return $this->response->InternalError($msg);
    }
}