<?php
namespace DafCore;

class SystemComponent 
{
   private static array $Namespaces = [];
   private static int $NamespacesIncludesFoldersCount = 0;

   public string $Id;
   public array $Cascades = [];
   public array $Cascaded = []; // resolved from ancestors
   public string $Path;
   public string $Namespace;
   public string $ChildContent;
   public function __construct(
      string $path,
      public array $Parameters = [],
      string $childContent = "",
   ) {
      $this->Path = $path;
      $this->Id = uniqid();
      $this->FullPath = $this->_getFullPath();
      $this->Namespace = $this->getNamespace($this->FullPath);

      foreach ($this->Parameters as $key => $value) {
         if (!ctype_upper($key[0])) {
            $this->Attributes[$key] = $value;
            unset($this->Parameters[$key]);
         }
      }

      $this->ChildContent = $childContent;
      
      $this->tryLoadComponentView($this->FullPath);
   }
   public array $Attributes = [];
   public array $Children = [];
   public string $FullPath;

   public Component $ViewComponent;
   private bool $childrenBuilt = false;
   private bool $inited = false;
   private static array $existsCache = [];   // path => bool
   private static array $classFileCache = []; // fullPath => string|null
   private static array $fullPathCache = [];  // logical path => fullPath

   
   private function getNamespace(string $path): string {
      if (str_starts_with($path, "vendor\\") || str_starts_with($path, "phar://vendor\\")) {
         $p = explode("\\", $path);
         unset($p[0]);
         return str_replace(".phar", "", implode("\\", $p));
      }
      return $path;
   }
   private static function normalizeFolderPath(string $p): string
   {
      $p = trim($p);
      if ($p === "") return "";

      // Keep phar:// prefix intact
      if (str_starts_with($p, "phar://")) {
         $rest = substr($p, 7);              // after "phar://"
         $rest = str_replace("/", "\\", $rest);
         $rest = rtrim($rest, "\\/");
         return "phar://" . $rest;
      }

      // normal filesystem path
      $p = str_replace("/", "\\", $p);
      $p = rtrim($p, "\\/");
      return $p;
   }

   /** Register one or more folders for component path resolution. */
   public static function AddNamespaces(string|array $folders)
   {
      if (is_array($folders)) {
         foreach ($folders as $f) self::AddNamespaces($f);
         return;
      }

      $folder = self::normalizeFolderPath($folders);
      if ($folder === "") return;

      $key = "*" . (++self::$NamespacesIncludesFoldersCount);
      self::$Namespaces[$key] = $folder;
   }



   /** Convenience wrapper for Component::AddNamespaces. */
   public function Use(string|array $useing): void{ self::AddNamespaces($useing); }

   /** Resolve a service from the DI container. */
   public function Inject(string $type): mixed{ return ServicesProvidor::$DI->getOne($type); }

   /** Read a parameter or cascaded value, optionally type-check. */
   public function Parameter(string $name, string $type = null): mixed {
      $val = null;
      if (array_key_exists($name, $this->Parameters)) $val = $this->Parameters[$name];
      else if (array_key_exists($name, $this->Cascaded)) $val = $this->Cascaded[$name];
      
      if (!is_null($val) && !is_null($type)) {
         if (self::getValueType(gettype($val)) !== $type)
            die("Parameter $name is not of type [ $type ] in component $this->Path");
      }
      return $val;
   }

   /** Read a required parameter and fail if missing or null. */
   public function RequiredParameter(string $name, string $type = null): mixed
   {
      $isInParameters = isset($this->Parameters[$name]);
      $isInCascaded = isset($this->Cascaded[$name]);

      if (!$isInParameters && !$isInCascaded)
         die("Required parameter $name is not set in component $this->Path");

      $val = $this->Parameter($name, $type);
      if (is_null($val))
         die("Required Parameter $name is not set in component $this->Path");
 
      return $val;
   }
   public function GetType(): string { return $this->Namespace; }

   /** Provide a cascading value to descendants. */
   public function Cascade(string $key, mixed $value, array|string $for = 'all'):void { $this->Cascades[$key] = ['for' => $for, 'value' => $value]; }


   /** Return the raw child content string. */
   //public function RenderChildContent(): string { return $this->ChildContent; }

   /** Return direct child components. */
   public function GetChildren(): array { $this->ensureChildrenBuilt(); return array_map(fn($c)=>$c['c'],$this->Children); }

   /** Filter children by component path. */
   public function GetChildrenOfType(string $type): array {
      if(str_ends_with($type, "Component")) $type = substr($type,0,strlen($type) - 9);
      $out = [];
      foreach ($this->GetChildren() as $c) {
         /** @var SystemComponent $c */
         if ($c->Namespace === $type) $out[] = $c;
      }
      return $out;
   }

   /** Render all children of a given type. */
   public function RenderChildrenOfType(string $type): string {
      $out = "";
      $childs = $this->GetChildrenOfType($type);
      foreach ($childs as $c) $out .= $c->Render();
      return $out;
   }

   /** Render attributes as an HTML string (escaped). */
   public function RenderAttributes(): string {
      $attrs = "";
      foreach ($this->Attributes as $key => $value) {
         if ($value === null || $value === false)
            continue;
         if ($value === true) {
            $attrs .= $key . " ";
         } else {
            $safe = htmlspecialchars((string) $value, ENT_QUOTES);
            $attrs .= "$key='$safe' ";
         }
      }
      return $attrs;
   }

   /** Get a single attribute value. */
   public function GetAttribute(string $name): string|null {
      return $this->Attributes[$name] ?? null;
   }

   /** Get all attributes. */
   public function GetAttributes(): array { return $this->Attributes; }

   /** Replace or set multiple attributes. */
   public function SetAttributes(array $attrs): void {
      foreach ($attrs as $key => $value) {
         $this->Attributes[$key] = $value;
      }
   }

   /** Merge attributes to the end. */
   public function AddAttributesToEnd(array $attrs): void { $this->AddAttributes($attrs); }

   /** Merge attributes to the start. */
   public function AddAttributesToStart(array $attrs): void { $this->AddAttributes($attrs, 'start'); }

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



   public function LoadOnce(): void
   {
      if ($this->inited)
         return;
      $this->inited = true;

      if (method_exists($this->ViewComponent, 'OnLoad'))
         $this->ViewComponent->OnLoad();
   }
   /** Render this component and its nested components. */
   public function Render(): string{ $this->LoadOnce(); return $this->ViewComponent->Render(); }


   public function RenderTemplateWithView(Component $view, string $templatePath): array
   {
      $_DAF_componentPath = $templatePath;

      return (function () use ($_DAF_componentPath) {
         ob_start();
         include $_DAF_componentPath;
         $_DAF_out = ob_get_clean();
         $_DAF_scope = get_defined_vars();
         return [$_DAF_out, $_DAF_scope];
      })->call($view);
   }

   public function GetComponentTemplatePath(): string
   {
      $p = $this->FullPath . ".php";
      !is_win_os() && $p = str_replace("\\", "/", $p);
      return $p;
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
   public function applyScopeToComponent(SystemComponent $c, array $scope): void{
      foreach ($c->Parameters as $key => $val) {
         if (is_string($val) && str_starts_with($val, "$")) {
            $c->Parameters[$key] = $this->ResolveScopeVar($val, $scope);
         }
      }
   }


   public function RenderChildContent(): string
   {
      return $this->RenderBuiltChildren();
   }

   public function RenderBuiltChildren(): string
   {
      $this->ensureChildrenBuilt();

      if (empty($this->Children)) {
         return $this->ChildContent;
      }

      $parts = [];
      $last = 0;

      foreach ($this->Children as $it) {
         /** @var SystemComponent $child */
         $child = $it['c'];
         $start = $it['start'];
         $end   = $it['end'];

         // Only add text chunk if needed
         if ($start > $last) {
               $parts[] = substr($this->ChildContent, $last, $start - $last);
         }

         // resolve cascades NOW (after parent OnLoad/template ran)
         if (!empty($this->Cascades)) {
            $child->Cascaded = $this->ResolveCascadeFor($child);
         } else {
            $child->Cascaded = $this->Cascaded;
         }

         $parts[] = $child->Render();
         $last = $end;
      }

      // tail text
      $tailLen = strlen($this->ChildContent) - $last;
      if ($tailLen > 0) {
         $parts[] = substr($this->ChildContent, $last, $tailLen);
      }

      return implode('', $parts);
   }

   private function ensureChildrenBuilt(): void
   {
      if ($this->childrenBuilt) return;
      $this->childrenBuilt = true;

      if ($this->ChildContent === '') {
         $this->Children = [];
         return;
      }

      $cacheKey = $this->FullPath . ':Child:' . strlen($this->ChildContent);
      $items = CParser::MakeComponentsCachedKey($cacheKey, $this->ChildContent);

      $children = [];
      foreach ($items as $item) {
         /** @var SystemComponent $c */
         $c = $item['component'];
         $c->LoadOnce();

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

   private function tryLoadComponentView(string $path){
      $classPath = $this->tryFindComponentClassFile($path);

      if ($classPath) {
         require_once $classPath;

         $class = $this->getComponentClassName();
         // echo  "<pre>";
         // print_r(get_declared_classes());
         // echo $path . "<br>";
         // echo  "</pre>";
         $this->ViewComponent = new $class($this); // must extend ViewComponent
      }

      if (!isset($this->ViewComponent)) {
         $this->ViewComponent = new Component($this);
      }
   }
   private function getComponentClassName(): string
   {
      if (str_starts_with($this->FullPath, "vendor\\") || str_starts_with($this->FullPath, "phar://vendor\\")) {
         $p = explode("\\", $this->FullPath);
         unset($p[0]);
         $pStr = str_replace(".phar", "", implode("\\", $p));
         return $pStr. "Component";
      }
      
      return $this->FullPath."Component";
   }
   private function tryFindComponentClassFile(string $path): ?string
   {
      if (array_key_exists($path, self::$classFileCache)) {
         return self::$classFileCache[$path];
      }

      $componentPath = $path . ".php";
      !is_win_os() && $componentPath = str_replace("\\", "/", $componentPath);

      $dir  = dirname($componentPath);
      $base = pathinfo($componentPath, PATHINFO_FILENAME);
      $candidate = $dir . DIRECTORY_SEPARATOR . $base . 'Component.php';

      return self::$classFileCache[$path] = (self::Exists($candidate) ? $candidate : null);
   }


   /** Normalize PHP type strings to friendly aliases. */
   private function getValueType(string $type): string {
      return match($type){
         'integer' => 'int',
         'boolean' => 'bool',
         default => $type
      };
   }

   /** Resolve component path using include-folders (namespaces). */
   private function _getFullPath(): string
   {
      if (isset(self::$fullPathCache[$this->Path])) {
         return self::$fullPathCache[$this->Path];
      }

      // Direct alias mapping still supported if you use it elsewhere
      if (isset(self::$Namespaces[$this->Path]) && self::$Namespaces[$this->Path][0] !== "*") {
         return self::$fullPathCache[$this->Path] = self::$Namespaces[$this->Path];
      }

      // Search inside include folders only
      foreach (self::$Namespaces as $key => $folder) {
         if (!isset($key[0]) || $key[0] !== "*") continue;

         $templatePath  = $folder . "\\" . $this->Path . ".php";
         $componentPath = $folder . "\\" . $this->Path . "Component.php";

         if (!is_win_os()) {
               $templatePath  = str_replace("\\", "/", $templatePath);
               $componentPath = str_replace("\\", "/", $componentPath);
         }

         if (self::Exists($templatePath) || self::Exists($componentPath)) {
               return self::$fullPathCache[$this->Path] = $folder . "\\" . $this->Path;
         }
      }

      // fallback: treat as already-full path
      return self::$fullPathCache[$this->Path] = $this->Path;
   }



   public static function Exists(string $p): bool {
      if (isset(self::$existsCache[$p]))
         return self::$existsCache[$p];
      return self::$existsCache[$p] = file_exists($p);
   }
   /** Match cascade "for" rule against a child component path. */
   private function matches(SystemComponent $child, array|string $for): bool
   {
      if ($for === 'all') {
         return true;
      }

      if (is_array($for)) {
         return in_array($child->FullPath, $for, true) || in_array($child->Path, $for, true);
      }

      return $for === $child->FullPath || $for === $child->Path;
   }

   /** Resolve "$var" style expressions against the render scope. */
   private function ResolveScopeVar(string $expr, array $scope): mixed
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


class LayoutComponent extends SystemComponent
{
   public SystemComponent $Child;

   public function RenderChildContent(): string{
      $this->Child->Cascaded = $this->ResolveCascadeFor($this->Child);
      return $this->Child->Render();
   }
}

class HostComponent extends LayoutComponent { }