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
   public static string $layout = "MainLayout";

   function GetLayout(): string{
      return self::$layout;
   }

   function SetLayout(string $layout): IViewManager
   {
      self::$layout = $layout;
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
      if (file_exists(Application::$BaseFolder . "/Views/_GlobalUsing.php")) {
         ((new Component(Application::$BaseFolder . "/Views/_GlobalUsing"))->Render());
      }

      $this->triggerOnRender();
      $view_path = Application::$BaseFolder . "/Views/$view";
      if (file_exists($view_path.".php")) {
         $viewContent = (new Component($view_path, $params))->Render();
      } else $viewContent = ((new Component($view, $params))->Render());

      $layoutContent = '';
      if (file_exists(Application::$BaseFolder . "/Views/_Layouts/" . self::$layout . ".php")) {
         $layout = self::$layout;
         $layoutContent = ((new LayoutComponent(Application::$BaseFolder . "/Views/_Layouts/$layout", ['Body' => $viewContent]))->Render());
      }

      $host_file_path = Application::$BaseFolder . "/Views/_Layouts/_Host";
      if (file_exists($host_file_path . ".php") && !empty($layoutContent)) {
         $host = ((new LayoutComponent($host_file_path, ['Layout' => $layoutContent]))->Render());
         $layoutContent = $host;
      }

      $this->triggerOnAfterRender();
      if (empty($layoutContent))
         return $viewContent;
      return $layoutContent;
   }
}
