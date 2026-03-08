<?php
namespace DafCore\Controllers;
use DafCore\IViewManager;
use DafCore\Response;

class Controller extends BaseController
{
    private $layout = "MainLayout";
    private IViewManager $viewManager;

    public function SetLayout(string $layout) : static{
        $this->layout = $layout;
        $this->viewManager->SetLayout($layout);

        return $this;
    }

    public function SetViewManager(IViewManager $viewManager): void{
        $this->viewManager = $viewManager;
    }

    protected function Status(int $status): static{
        $this->response->Status($status);
        return $this;
    }

    protected function RenderView($view, $params = []): string{
        return $this->viewManager->RenderView($view, $params);
    }
    
    protected function Ok(string $view, ...$params): string{
        return $this->Status(Response::HTTP_OK)->RenderView($view, $params);
    }
    protected function InternalError(string $view, ...$params): string{
        return $this->Status(Response::HTTP_INTERNAL_ERROR)->RenderView($view, $params);
    }
             
    protected function BadRequest(string $view, ...$params): string {
        return $this->Status(Response::HTTP_BAD_REQUEST)->RenderView($view, $params);
    }

    protected function NotFound(string $view, ...$params): string {
        return $this->Status(Response::HTTP_NOT_FOUND)->RenderView($view, $params);
    }


    protected function Created(string $view, ...$params): string{
        return $this->Status(Response::HTTP_CREATED)->RenderView($view, $params);
    }         
    protected function NoContent(string $view, ...$params): string{
        return $this->Status(Response::HTTP_NO_CONTENT)->RenderView($view, $params);
    }
    protected function Forbidden(string $view, ...$params): string{
        return $this->Status(Response::HTTP_FORBIDDEN)->RenderView($view, $params);
    }
    protected function Unauthorized(string $view, ...$params): string{
        return $this->Status(Response::HTTP_UNAUTHORIZED)->RenderView($view, $params);
    }

}