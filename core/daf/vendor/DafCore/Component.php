<?php
namespace DafCore;

interface IComponent{
   /** Render the raw child content string as provided in markup.
    * @return string
    */
   public function RenderChildContent(): string;

   /** Return direct child components.
    * @return array
    */
   public function GetChildren(): array;

   /** Register namespaces for component discovery.
    * @param string|array $useing string|string[] of file path
    * @return void
    */
   public function Use(string|array $useing): void;

   /** Resolve a service from the DI container.
    * @param string $type dependency key
    * @return mixed dependency
    */
   public function Inject(string $type): mixed;

   /** Read a parameter (explicit or cascaded), optionally type-check.
    * @param string $name parameter name
    * @param string|null $type file path or null
    * @return mixed
    */
   public function Parameter(string $name, string $type = null): mixed;

   /** Read a parameter and fail if missing or null.
    * @param string $name parameter name
    * @param string|null $type file path or null
    * @return mixed
    */
   public function RequiredParameter(string $name, string $type = null): mixed;

   /** Provide a cascading value to descendants.
    * @param string $key
    * @param mixed $value
    * @param array|string $for
    * @return void
    */
   public function Cascade(string $key, mixed $value, array|string $for = 'all'):void;

   /** Filter children by component path.
    * @param string $type file path
    * @return array
    */
   public function GetChildrenOfType(string $type) : array;

   /** Render all children of a given type.
    * @param string $type file path
    * @return void
    */
   public function RenderChildrenOfType(string $type): void;

   /** Render attributes as an HTML string (escaped).
    * @return string
    */
   public function RenderAttributes(): string;

   /** Get a single attribute value.
    * @param string $name attribute name
    * @return string|null attribute value or null
    */
   public function GetAttribute(string $name): string|null;
   
   /** Get all attributes.
    * @return array attribute array
    */
   public function GetAttributes(): array;

   /** Replace or set multiple attributes.
    * @param array $attrs attribute array
    * @return void
    */
   public function SetAttributes(array $attrs): void;

   /** Merge attributes to the end.
    * @param array $attrs attribute array
    * @return void
    */
   public function AddAttributesToEnd(array $attrs): void;

   /** Merge attributes to the start.
    * @param array $attrs attribute array
    * @return void
    */
   public function AddAttributesToStart(array $attrs): void;

   /** Render this component and its nested components.
    * @return string
    */
   public function Render(): string;
}
class Component 
{
   private static array $Namespaces = [];
   private static int $NamespacesIncludesFoldersCount = 0;


   public string $Id;
   public array $Cascades = [];
   public array $Cascaded = []; // resolved from ancestors

   public function __construct(
      public string $Path,
      public array $Parameters = [],
      public string $ChildContent = "",
      public string $Markup = "",
   ) {
      $this->Id = uniqid();
      $this->FullPath = $this->_getFullPath();
      foreach ($this->Parameters as $key => $value) {
         if (!ctype_upper($key[0])) {
            $this->Attributes[$key] = $value;
            unset($this->Parameters[$key]);
         }
      }
   }
   public array $Attributes = [];
   public Component $Parent;
   public array $Children = [];
   public string $FullPath;

   /** Register one or more namespaces for component path resolution. */
   public static function AddNamespaces(string|array $useing)
   {
      if (is_array($useing)) {
         foreach ($useing as $u) {
            self::AddNamespaces($u);
         }
         return;
      }

      //is_win_os() === false && $useing = str_replace("\\", "/", $useing);

      $arr = explode("\\", $useing);
      // pop out element from the array
      $name = end($arr);
      if($name === "*"){
         $name = $name.(++self::$NamespacesIncludesFoldersCount);
         array_pop($arr);
         $useing = implode("\\", $arr);
      }
      self::$Namespaces[$name] = $useing;
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


   /** Provide a cascading value to descendants. */
   public function Cascade(string $key, mixed $value, array|string $for = 'all'):void { $this->Cascades[$key] = ['for' => $for, 'value' => $value]; }


   /** Return the raw child content string. */
   public function RenderChildContent(): string { return $this->ChildContent; }

   /** Return direct child components. */
   public function GetChildren(): array { return $this->Children; }

   /** Filter children by component path. */
   public function GetChildrenOfType(string $type) : array { return array_filter($this->Children, fn($c) => $c->FullPath === $type); }

   /** Render all children of a given type. */
   public function RenderChildrenOfType(string $type): void {
      $childs = $this->GetChildrenOfType($type);
      foreach ($childs as $c) echo $c->Render();
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


   /** Render this component and its nested components. */
   public function Render(): string
   {
      $componentPath = $this->FullPath . ".php";
      !is_win_os() && $componentPath = str_replace("\\", "/", $componentPath);


      if (!empty($this->ChildContent)) {
         $comps = CParser::MakeComponents($this->ChildContent);
         foreach ($comps as $item) {
            /** @var Component $c */
            $c = $item['component'];
            $c->Parent = $this;
            $c->Cascaded = $this->ResolveCascadeFor($c);
            $this->Children[$c->Id] = $c; 
         }
      }

      $renderScope = [];

      if (file_exists($componentPath)) {
         $_DAF_view = new ViewComponent($this);

         $_DAF_componentPath = $componentPath;
         [$strToRender, $renderScope] = (function () use ($_DAF_componentPath) {
            ob_start();
            include $_DAF_componentPath;
            $_DAF_out = ob_get_clean();
            $_DAF_scope = get_defined_vars();
            return [$_DAF_out, $_DAF_scope];
         })->call($_DAF_view);
            $renderScope = array_diff_key($renderScope, array_flip([
               '_DAF_componentPath','_DAF_out','_DAF_scope','_DAF_view'
            ]));
      } else {
         $strToRender = $this->Path;
      }

      foreach ($this->Children as $child) {
         $this->applyScopeToComponent($child, $renderScope);
      }

      $comps = CParser::MakeComponents($strToRender);
      if (!empty($comps)) {
         $out = '';
         $last = 0;

         foreach ($comps as $item) {
            /** @var Component $c */
            $c = $item['component'];

            $c->Parent = $this;
            $c->Cascaded = $this->ResolveCascadeFor($c);
            $this->applyScopeToComponent($c, $renderScope);

            $start = $item['start'];
            $end = $item['end']; // absolute end index

            $out .= substr($strToRender, $last, $start - $last);

            try {
               $out .= $c->Render();
            } catch (\Throwable $th) {
               echo $th->getMessage();
            }

            $last = $end;
         }

         $out .= substr($strToRender, $last);
         $strToRender = $out;
      }


      return $strToRender;
   }

   
   /** Resolve $var references inside component parameters using render scope. */
   private function applyScopeToComponent(Component $c, array $scope): void{
      foreach ($c->Parameters as $key => $val) {
         if (is_string($val) && str_starts_with($val, "$")) {
            $c->Parameters[$key] = $this->ResolveScopeVar($val, $scope);
         }
      }
   }

   /** Normalize PHP type strings to friendly aliases. */
   private function getValueType(string $type): string {
      $types = ['integer' => 'int', 'boolean' => 'bool'];
      if(isset($types[$type])) return $types[$type];
      else return $type;
   }

   /** Resolve component path using namespaces and wildcards. */
   private function _getFullPath () : string {
      if(isset(self::$Namespaces[$this->Path])) return self::$Namespaces[$this->Path];

      foreach (self::$Namespaces as $key => $value) {
         if($key[0] === "*"){
            $componentPath = $value . "\\" . $this->Path .".php";
            !is_win_os() && $componentPath = str_replace("\\", "/", $componentPath);
            
            if(file_exists($componentPath)) return $value . "\\" . $this->Path;
         }
      }
      return $this->Path;
   }

   /** Match cascade "for" rule against a child component path. */
   private function matches(Component $child, array|string $for): bool
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
   
   /** Build cascaded values for a specific child. */
   protected function ResolveCascadeFor(Component $child): array {
      $ctx = $this->Cascaded; // inherit from parent
      foreach ($this->Cascades as $key => $entry) {
         if ($this->matches($child, $entry['for'])) {
            $ctx[$key] = $entry['value'];
         }
      }
      return $ctx;
   }
}


class LayoutComponent extends Component
{
   public Component $Child;

   public function RenderChildContent(): string{
      $this->Child->Parent = $this;
      $this->Child->Cascaded = $this->ResolveCascadeFor($this->Child);
      return $this->Child->Render();
   }
}

class HostComponent extends LayoutComponent { }
function str_replace_first($search, $replace, $subject)
{
   /** Replace only the first occurrence of a substring. */
   $search = '/' . preg_quote($search, '/') . '/';
   $res = preg_replace($search, $replace, $subject, 1);
   return $res;
}