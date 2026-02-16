<?php
namespace DafCore\Controllers;
use DafCore\IViewManager;

class Controller extends BaseController {  
    private $layout = "MainLayout";
    private IViewManager $viewManager;

    function SetLayout(string $layout) : static{
        $this->layout = $layout;
        return $this;
    }
    
    function SetViewManager(IViewManager $viewManager){
        $this->viewManager = $viewManager;
    }
    
    protected function Status(int $status): static{
        $this->response->Status($status);
        return $this;
    }

    protected function RenderView($view, $params = []){
        return $this->viewManager->SetLayout($this->layout)->RenderView($view, $params);
    }
    
    protected function Ok(string $view, array $params = []){
        return $this->Status(200)->RenderView($view, $params);
    }
    protected function InternalError(string $view, array $params = []){
        return $this->Status(500)->RenderView($view, $params);
    }
             
    protected function BadRequset(string $view, array $params = []): string {
        return $this->Status(400)->RenderView($view, $params);
    }

    protected function NotFound(string $view, array $params = []): string {
        return $this->Status(404)->RenderView($view, $params);
    }

    protected function Redirect($location = ""){
        header('Location: ' . $location);
        exit();
    }
    
    protected function RedirectBack(){
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
}