<?php
namespace DafCore\Controllers;
use DafCore\ViewManager;

class Controller extends BaseController {  
    private $layout = "MainLayout";
    private ViewManager $viewManager;

    function SetLayout(string $layout){
        $this->layout = $layout;
    }
    
    function SetViewManager(ViewManager $viewManager){
        $this->viewManager = $viewManager;
    }
    
    protected function Status(int $status){
        $this->response->Status($status);
        return $this;
    }

    protected function RenderView($view, $params = []){
        return $this->viewManager->SetLayout($this->layout)->RenderView($view, $params);
    }
    
    protected function InternalError($msg = null, $view = "_500"){
        if(file_exists("App/Views/_Errors/$view.php")){
            $this->response->Status(400);
            return $this->viewManager->SetLayout('none')->RenderView($view, ["Msg"=>$msg]);
        }

        return $this->response->InternalError($msg);
    }
             
    protected function BadRequset($msg = null, $view = "_400"){
        if(file_exists("App/Views/_Errors/$view.php")){
            $this->response->Status(400);
            return $this->viewManager->SetLayout('none')->RenderView($view, ["Msg"=>$msg]);
        }

        return $this->response->BadRequest($msg);
    }

    protected function NotFound($msg = null, $view = "_404"){
        if(file_exists("App/Views/_Errors/$view.php")){
            $this->response->Status(404);
            return $this->viewManager->SetLayout('none')->RenderView($view, ["Msg"=>$msg]);
        }

        return $this->response->NotFound($msg);
    }

    protected function Redirect($location = ""){
        header('Location: /' . $location);
        exit();
    }
    
    protected function RedirectBack(){
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
}