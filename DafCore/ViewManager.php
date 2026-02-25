<?php
namespace DafCore;

use DafGlobals\IO\Path;
use DafCore\Components\Routing\HostComponent;
use DafCore\Views\Components\SystemComponent;

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
   private array $onRender = [];
   private array $onAfterRender = [];
   private string $layout = "";

   public function __construct(){
      $this->layout = $this->resolveFullName("MainLayout");
   }

   function SetLayout(string $layout) : IViewManager {
      $this->layout = $this->resolveFullName($layout);
      return $this;
   }

   function GetLayout(): string{
      return $this->layout;
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

   private function isShortName(string $name):bool { return !str_contains($name,"\\"); }
   private function resolveFullName(string $shortName):string { return $this->isShortName($shortName) ? Application::$BaseFolder."\\Views\\_Layouts\\$shortName" : $shortName; }


   /**
    * Render a view inside the Host shell.
    * This keeps page rendering in the host/layout pipeline.
    * @return string
    */
   public function RenderView(string $view, array $params = []): string
   {
      $this->triggerOnRender();
   
      try{
         $viewComponent = new SystemComponent($view, $params);

         $base = Application::$BaseFolder;

         $hostPath = Path::Combine($base, "Views", "_Layouts", "Host");
         if(!file_exists("$hostPath.view.php")) return $viewComponent->Render();

         $hostPath = str_replace("/","\\", $hostPath);
         $hostComponent = new HostComponent(new SystemComponent($hostPath, ['StartWith' => 'RouterView']));
         $hostComponent->Cascade('PageCallback', fn()=>$viewComponent->Render());
         $hostComponent->Load();

         return $hostComponent->Render();
      }finally{
         $this->triggerOnAfterRender();
      }
   }
}