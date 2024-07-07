<?php
namespace DafCore;

abstract class Outlet
{
   public function __construct(protected array $content = []){}

   public function AddContent(string $keyOrContent, string $content = null)
   {
      if (isset($content))
         $this->content[$keyOrContent] = $content;
      else
         $this->content[] = $keyOrContent;
   }

   public function RenderOutlet()
   {
      foreach ($this->content as $value) {
         echo $value;
      }
   }
}