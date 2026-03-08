<?php
namespace DafCore\Views\Components;

use DafCore\Application;
use DafCore\Component;


class SystemComponent 
{
   public string $Id;
   public string $Name;
   public string $FilePath;
   public string $Namespace;
   public string $SourceText;
   public string $ChildContent;

   public array $GlobalParameters = [];
   public array $Parameters = [];
   public array $Cascades = [];
   public array $Cascaded = []; // resolved from ancestors

   public array $Children = [];
   public array $Attributes = [];
   public array $ScopesFromRender = [];

   public Component $ViewComponent;
   public ?SystemComponent $Parent = null;

   private bool $inited = false;
   private bool $childrenBuilt = false;


   public function __construct(string $path, array $parameters = [], string $childContent = "", private string $markup = "") {
      $this->Id = uniqid();

      if(str_starts_with($path, "Phar\\vendor\\")) $path = ComponentRegistry::NormalizeFolderPath($path); 
      
      $this->SourceText = $path;
      
      $this->Name = $this->baseNamespace($path);
      $this->FilePath = ComponentRegistry::ResolveFilePath($path);
      $this->Namespace = $this->resolveNamespace($this->FilePath);
     
      foreach ($parameters as $key => $value) {
         if ($key[0] === "_"){
            $reKey = substr($key,1);
            $this->Parameters[$reKey] = $value;
            $this->GlobalParameters[$reKey] = $value;
         }
         else if (!ctype_upper($key[0])) 
            $this->Attributes[$key] = $value;
         else $this->Parameters[$key] = $value;
      }

      $this->ChildContent = $childContent;
      
      $this->loadComponentView($this->FilePath);
   }

   public function GetMarkup(): string{
      if($this->markup !== "") return $this->markup;

      $parameters = implode(" ", array_map(fn($p, $k)=> "$k='".(string)$p."'", $this->Parameters));
      $markup = "<{$this->Name} $parameters";

      if($this->ChildContent === "") return $markup . " />";

      return $markup . " >" . $this->ChildContent . "</{$this->Name}>";
   }

   public static function RenderMarkup(string $strToRender): string{
      $cacheKey = sha1($strToRender);
      $comps = ComponentsParser::MakeComponentsCachedKey($cacheKey, $strToRender);

      if (!empty($comps)) {
         $out = '';
         $last = 0;

         foreach ($comps as $item) {
            /** @var SystemComponent $c */
            $c = $item['component'];

            $start = $item['start'];
            $end = $item['end']; // absolute end index

            $out .= substr($strToRender, $last, $start - $last);

            $out .= $c->Render();

            $last = $end;
         }

         $out .= substr($strToRender, $last);
         $strToRender = $out;
      }
      return $strToRender;
   }

   
   public function GetType(): string { return ltrim($this->Namespace ."\\". $this->Name, "\\"); }

   public function GetComponentTemplatePath(): string { return $this->FilePath . ".view.php"; }
   public function TryGetComponentTemplatePath(): ?string { 
      $normalized = str_replace("\\", "/", trim($this->FilePath));
      if (!str_starts_with($normalized, "phar://") && !str_starts_with($normalized, "vendor/") && !str_starts_with($normalized, Application::$BaseFolder."/")) {
         return null;
      }

      return $this->FilePath . ".view.php"; 
   }


   /** Return direct child components. */
   public function GetChildren(): array { $this->ensureChildrenBuilt(); return array_map(fn($c)=>$c['c'],$this->Children); }

   /** Filter children by component path. */
   public function GetChildrenOfType(string $type): array {
      $out = [];
      foreach ($this->GetChildren() as $c) {
         /** @var SystemComponent $c */
         if ($c->GetType() === $type) $out[] = $c;
      }
      return $out;
   }

   /** Merge or append attribute values by position. */
   public function AddAttributes(array $attrs, string $pos = "end"): void{
      foreach ($attrs as $key => $value) {
         if (!isset($this->Attributes[$key]))
            $this->Attributes[$key] = $value;
         else {
            if ($pos === "start")
               $this->Attributes[$key] = "$value " . $this->Attributes[$key];
            else if ($pos === "end")
               $this->Attributes[$key] = $this->Attributes[$key] . " $value";
         }
      }
   }

   /** Render this component and its nested components. */
   public function Render(): string{ $this->loadOnce(); return $this->ViewComponent->Render(); }

   /**
    * Try reading a parameter from explicit parameters or cascaded values.
    * Returns false only when key does not exist in both sources.
    */
   public function TryGetParameter(mixed &$ref, string $name, ?string $type = null): bool
   {
      $found = false;
      $val = null;

      if (array_key_exists($name, $this->Parameters)) {
         $val = $this->Parameters[$name];
         $found = true;
      } else if (array_key_exists($name, $this->Cascaded)) {
         $val = $this->Cascaded[$name];
         $found = true;
      }

      if (!$found) return false;

      if (!is_null($val) && !is_null($type)) {
         $valType = gettype($val);
         $valType = match($valType){
            'integer' => 'int',
            'boolean' => 'bool',
            default => $valType
         };
         if ($valType !== $type) {
            die("Parameter $name is not of type [ $type ] in component {$this->GetType()}");
         }
      }

      $ref = $val;
      return true;
   }

   /**
    * Auto-bind public typed uppercase properties from component class hierarchy.
    * Uses cached reflection metadata per concrete component class.
    */
   public function AutoBindDeclaredParameters(Component $view): void
   {
      $className = get_class($view);

      $meta = ComponentRegistry::GetAutoBindCache($className, function () use ($className) {
         $class = new \ReflectionClass($className);
         $out = [];

         foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) continue;

            $declaringClassName = $property->getDeclaringClass()->getName();
            if (!is_a($declaringClassName, Component::class, true) || $declaringClassName === Component::class) continue;

            $name = $property->getName();
            if ($name === '' || !ctype_upper($name[0])) continue;

            $type = $property->getType();
            if ($type === null) continue;

            $hasDefault = $property->hasDefaultValue();
            $out[] = [
               'name' => $name,
               'allowsNull' => $type->allowsNull(),
               'hasDefault' => $hasDefault,
               'defaultValue' => $hasDefault ? $property->getDefaultValue() : null,
            ];
         }

         return $out;
      });

      foreach ($meta as $entry) {
         $name = $entry['name'];
         $allowsNull = (bool)$entry['allowsNull'];
         $hasDefault = (bool)$entry['hasDefault'];

         if ($hasDefault) {
            $value = null;
            if (!$this->TryGetParameter($value, $name)) {
               $value = $entry['defaultValue'];
            } else if (!$allowsNull && $value === null) {
               $value = $entry['defaultValue'];
            }
            $view->$name = $value;
            continue;
         }

         $value = $allowsNull
            ? $view->Parameter($name)
            : $view->RequiredParameter($name);

         $view->$name = $value;
      }
   }

   public function RenderTemplateWithView(Component $view, string $templatePath): array
   {
      $_DAF_componentPath = $templatePath;
      $_DAF_gps = [];
      foreach ($this->GlobalParameters as $key => $value) {
         $_DAF_gps[$key] = $value;
      }

      return (function () use ($_DAF_componentPath, $_DAF_gps) {
         ob_start();
         foreach ($_DAF_gps as $key => $value) {
            $$key = $value;
         }
         include $_DAF_componentPath;
         $_DAF_out = ob_get_clean();
         $_DAF_scope = get_defined_vars();
         return [$_DAF_out, $_DAF_scope];
      })->call($view);
   }

   /** Build cascaded values for a specific child. */
   public function ResolveCascadeFor(SystemComponent $child): array {
      $ctx = $this->Cascaded; // inherit from parent
      foreach ($this->Cascades as $key => $entry) {
         if ($this->matches($child, $entry['for'])) {
            $ctx[$key] = $entry['value'];
         }
      }
      return $ctx;
   }

   /** Resolve $var references inside component parameters using render scope. */
   public function ApplyScopeToComponent(SystemComponent $c, array $scope): void{
      foreach ($c->Parameters as $key => $val) {
         if (is_string($val) && str_starts_with($val, "$")) {
            $c->Parameters[$key] = $this->resolveScopeVar($val, $scope);
         }
      }
   }

   public function EnsureChildrenBuilt(): void
   {
      if ($this->childrenBuilt) return;
      $this->childrenBuilt = true;

      if ($this->ChildContent === '') {
         $this->Children = [];
         return;
      }

      $cacheKey = $this->FilePath . ':Child:' . sha1($this->ChildContent);
      $items = ComponentsParser::MakeComponentsCachedKey($cacheKey, $this->ChildContent);

      $children = [];
      foreach ($items as $item) {
         /** @var SystemComponent $c */
         $c = $item['component'];
         $c->Parent = $this;

         if (!empty($this->Cascades)) {
            $c->Cascaded = $this->ResolveCascadeFor($c);
         } else {
            $c->Cascaded = $this->Cascaded;
         }

         $c->loadOnce();

         $children[] = [
               'start' => (int)$item['start'],
               'end'   => (int)$item['end'],
               'c'     => $c,
         ];
      }

      // Ensure correct order (important if parser ever returns unsorted)
      //usort($children, fn($a, $b) => $a['start'] <=> $b['start']);

      $this->Children = $children;
   }



   public function loadOnce(): void
   {
      if ($this->inited) return;
      $this->inited = true;
      
      if (method_exists($this->ViewComponent, 'Load'))
         $this->ViewComponent->Load();
   }
   private function loadComponentView(string $path){
      $classPath = ComponentRegistry::TryFindComponentClassFile($path);
      
      if (!$classPath) {
         $this->ViewComponent = new Component($this);
         return;
      }

      require_once $classPath;

      $class = $this->GetType();

      $view = new $class($this);
      if (!($view instanceof Component)) {
         throw new \Exception("Component class [$class] must extend \\DafCore\\Component.");
      }

      $this->ViewComponent = $view;
   }


   private function baseNamespace(string $path): string {
      $parts = explode("\\", $path);
      return end($parts);
   }
   private function resolveNamespace(string $path): string
   {
      $normalized = str_replace("\\", "/", trim($path));

      // Handle both:
      // vendor/DafCore/Components/...
      // phar://vendor/DafCore.phar/Components/...
      if (str_starts_with($normalized, "phar://")) {
         $normalized = substr($normalized, 7); // remove "phar://"
      }

      if (str_starts_with($normalized, "vendor/")) {
         $parts = explode("/", $normalized);
         array_shift($parts); // remove "vendor"

         // DafCore.phar -> DafCore
         if (!empty($parts)) {
            $parts[0] = str_replace(".phar", "", $parts[0]);
         }

         array_pop($parts); // remove class name
         return implode("\\", $parts);
      }

      // Fallback for non-vendor paths
      $parts = explode("/", $normalized);
      array_pop($parts);
      return implode("\\", $parts);
   }



   /** Match cascade "for" rule against a child component path. */
   private function matches(SystemComponent $child, array|string $for): bool
   {
      if ($for === 'all') {
         return true;
      }

      if (is_array($for)) {
         return in_array($child->GetType(), $for, true);
      }

      return $for === $child->GetType();
   }
   /** Resolve "$var" style expressions against the render scope. */
   private function resolveScopeVar(string $expr, array $scope): mixed
   {
      if ($expr === "" || $expr[0] !== "$") return $expr;
      $expr = substr($expr, 1);

      $len = strlen($expr);
      $i = 0;
      $base = "";
      while ($i < $len) {
         $ch = $expr[$i];
         if ($ch === "[" || ($ch === "-" && ($i + 1 < $len) && $expr[$i + 1] === ">")) break;
         $base .= $ch;
         $i++;
      }
      if ($base === "" || !array_key_exists($base, $scope)) return null;
      $value = $scope[$base];
      $rest = substr($expr, $i);

      while ($rest !== "") {
         if (str_starts_with($rest, "->")) {
            $rest = substr($rest, 2);
            $prop = "";
            $j = 0;
            $restLen = strlen($rest);
            while ($j < $restLen) {
               $ch = $rest[$j];
               if ($ch === "[" || ($ch === "-" && ($j + 1 < $restLen) && $rest[$j + 1] === ">")) break;
               $prop .= $ch;
               $j++;
            }
            $value = (is_object($value) && isset($value->$prop)) ? $value->$prop : null;
            $rest = substr($rest, $j);
            continue;
         }

         if (str_starts_with($rest, "[")) {
            $end = strpos($rest, "]");
            if ($end === false) break;
            $key = substr($rest, 1, $end - 1);
            $key = trim($key, "'\"");
            $value = (is_array($value) && array_key_exists($key, $value)) ? $value[$key] : null;
            $rest = substr($rest, $end + 1);
            continue;
         }

         break;
      }

      return $value;
   }



   private function daf_first_php_block(string $src): string
   {
      $start = strpos($src, '<?php');
      if ($start === false) return '';

      $end = strpos($src, '?>', $start);
      if ($end === false) {
         // no close tag: treat rest of file as PHP
         return substr($src, $start);
      }

      return substr($src, $start, ($end - $start) + 2);
   }
   public function daf_extract_uses(string $phpSource): array
   {
      $phpTop = $this->daf_first_php_block($phpSource);
      if ($phpTop === '') return [];

      $tokens = token_get_all($phpTop);

      $uses = [];
      $n = count($tokens);

      for ($i = 0; $i < $n; $i++) {
         $t = $tokens[$i];

         if (!is_array($t) || $t[0] !== T_USE) continue;

         $i++;
         $full = '';
         $alias = null;

         while ($i < $n) {
               $tk = $tokens[$i];

               if (is_string($tk) && $tk === ';') break;

               if (is_array($tk) && defined('T_AS') && $tk[0] === T_AS) {
                  $i++;
                  while ($i < $n && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) $i++;
                  $aliasTok = $tokens[$i] ?? null;
                  if (is_array($aliasTok) && $aliasTok[0] === T_STRING) $alias = $aliasTok[1];
                  $i++;
                  continue;
               }

               if (is_array($tk)) {
                  $id = $tk[0];
                  if ($id === T_STRING || $id === T_NS_SEPARATOR || (defined('T_NAME_QUALIFIED') && $id === T_NAME_QUALIFIED)) {
                     $full .= $tk[1];
                  }
               } else {
                  if ($tk === '\\') $full .= '\\';
               }

               $i++;
         }

         $full = trim($full, " \t\n\r\\");
         if ($full !== '') {
               $short = $alias ?: basename(str_replace('\\', '/', $full));
               $uses[$short] = $full;
         }
      }

      return $uses;
   }
}
