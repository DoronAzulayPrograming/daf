<?php
namespace DafCore;

interface IViewManager
{
   function GetLayout(): string;
   function SetLayout(string $layout): self;
   function RenderView(string $view, array $params = []): string;

   function OnRender(callable $callback): void;
   function OnAfterRender(callable $callback): void;
}

class ViewManager implements IViewManager
{
   private array $onAfterRender = [];
   private array $onRender = [];
   private string $layout = "MainLayout";

   function GetLayout(): string{
      return $this->layout;
   }

   function SetLayout(string $layout): IViewManager
   {
      $this->layout = $layout;
      return $this;
   }

   function OnRender(callable $callback): void{
      $this->onRender[] = $callback;
   }
   function OnAfterRender(callable $callback): void{
      $this->onAfterRender[] = $callback;
   }
   private function triggerOnRender(): void{
      foreach($this->onRender as $callback){
         $callback();
      }
   }
   private function triggerOnAfterRender(): void{
      foreach($this->onAfterRender as $callback){
         $callback();
      }
   }

   public function RenderView(string $view, array $params = []): string
   {
      $base = Application::$BaseFolder;

      // Global Using
      $globalUsing = "$base/Views/_GlobalUsing";
      if (file_exists("$globalUsing.php")) {
         (new Component($globalUsing))->Render();
      }

      $this->triggerOnRender();

      $content = '';
      // Load main view
      $viewPath = "$base/Views/$view";
      $viewComponent = new Component(file_exists("$viewPath.php") ? $viewPath : $view, $params);

      // Load layout
      $layoutPath = "$base/Views/_Layouts/" . $this->layout;
      if (file_exists("$layoutPath.php")) {
         $layoutComponent = new LayoutComponent($layoutPath);
         $layoutComponent->Child = $viewComponent;
      }

      // Apply host layout if exists
      $hostPath = "$base/Views/_Layouts/_Host";
      if (file_exists("$hostPath.php") && isset($layoutComponent)) {
         $hostComponent = new HostComponent($hostPath);
         $hostComponent->Child = $layoutComponent;
         $content = $hostComponent->Render();
      }
      else if (isset($layoutComponent)) $content = $layoutComponent->Render();
      else $content = $viewComponent->Render();

      $this->triggerOnAfterRender();

      return $content;
   }
}
