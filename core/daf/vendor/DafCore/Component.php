<?php
namespace DafCore;

class Component
{

   private static int $NamespacesIncludesFoldersCount = 0;
   private static array $Namespaces = [];
   static function AddNamespaces(string|array $useing)
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


   public string $Id;

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
            $this->AdditionalParameters[$key] = $value;
            unset($this->Parameters[$key]);
         }
      }
   }
   public array $AdditionalParameters = [];
   public Component $Parent;
   public array $Children = [];
   public string $FullPath;

   function Use(string|array $useing)
   {
      self::AddNamespaces($useing);
   }
   function Inject(string $type): mixed
   {
      return ServicesProvidor::$DI->getOne($type);
   }
   function Store(string $key, mixed $var, array | string $for = 'all')
   {
      $this->Parameters[$key] = ['for'=> $for , 'value'=> $var];
   }

   function Parameter(string $name, string $type = null): mixed
   {
      if (!isset($this->Parameters[$name]))
         return null;

      $var_name = $this->IsVar($this->Parameters[$name]);

      if (is_null($var_name))
         $val = $this->Parameters[$name];
      else $val = $this->_getProp($var_name, $this->FullPath, $this->Id);
      
      if (!is_null($val) && !is_null($type)) {
         if (self::getValueType(gettype($val)) !== $type)
            die("Parameter $name is not of type [ $type ] in component $this->Path");
      }

      return $val;
   }
   function RequiredParameter(string $name, string $type = null): mixed
   {
      if (!isset($this->Parameters[$name]))
         die("Required parameter $name is not set in component $this->Path");

      $val = $this->Parameter($name, $type);
      if (is_null($val))
         die("Required Parameter $name is not set in component $this->Path");
 
      return $val;
   }

   function GetAdditionalProps()
   {
      $props = "";
      foreach ($this->AdditionalParameters as $key => $value)
         $props .= "$key='$value'";
      return $props;
   }
   function SetAdditionalProps(array $props, string $pos = "start")
   {
      foreach ($props as $key => $value) {
         if (!isset($this->AdditionalParameters[$key]))
            $this->AdditionalParameters[$key] = $value;
         else {
            if ($pos === "start")
               $this->AdditionalParameters[$key] = "$value " . $this->AdditionalParameters[$key] ?? "";
            else if ($pos === "end")
               $this->AdditionalParameters[$key] = $this->AdditionalParameters[$key] . " $value";
         }
      }
   }

   function GetChildrenOfType(string $type) : array
   {
      return array_filter($this->Children, fn($c) => $c->FullPath === $type);
   }
   function RenderChildrenOfType(string $type)
   {
      $childs = $this->GetChildrenOfType($type);
      foreach ($childs as $c)
         echo $c->render();
   }

   protected function ParamsToRender(): array {
      return []; 
   }

   function Render(): string
   {
      $componentPath = $this->FullPath . ".php";
      !is_win_os() && $componentPath = str_replace("\\", "/", $componentPath);

      $params_to_render = $this->ParamsToRender();
      if (!empty($params_to_render)) {
         foreach ($params_to_render as $key => $value) {
            $$key = $value;
         }
      }


      if (!empty($this->ChildContent)) {
         $comps = CParser::MakeComponents($this->ChildContent);
         foreach ($comps as $c) {
            /** @var Component $c */
            $c->Parent = $this;
            $this->Children[$c->Id] = $c;
         }
      }


      $strToRender = "";
      if (file_exists($componentPath)) {
         ob_start();
         include $componentPath;
         $strToRender = ob_get_clean();
      } else {
         $strToRender = $this->Path;
      }

      $comps = CParser::MakeComponents($strToRender);
      foreach ($comps as $c) {
         /** @var Component $c */
         $c->Parent = $this;
         try {
            $strToRender = str_replace_first($c->Markup, $c->Render(), $strToRender);
         } catch (\Throwable $th) {
            echo $th->getMessage();
         }
      }

      return $strToRender;
   }


   private function getValueType(string $type): string {
      $types = ['integer' => 'int', 'boolean' => 'bool'];
      if(isset($types[$type])) return $types[$type];
      else return $type;
   }
   function _getProp(string $name, string $c_name, string $c_id): mixed
   {
      if (isset($this->Parameters[$name])) {
         if (!isset($this->Children[$c_id])) {
            $arr = $this->Parameters[$name];

            if(is_string($arr) && (($var_name = $this->IsVar($arr)) !== null)){
               $val = $this->Parent->Parameter($var_name);
               return $val;
            }
            
            if(is_array($arr)){
               if (is_array($arr['for'])) {
                  if (in_array($c_name, $arr['for'])) {
                     return $arr['value'];
                  }
               } else {
                  if ($arr['for'] === 'all' || $arr['for'] === $c_name) {
                     return $arr['value'];
                  }
               }
            }
         }
      }

      if (strpos($name, "->") !== false) {
         $arr = explode("->", $name);
         $n_name = $arr[0];
         $prop = $arr[1];
         $val = $this->_getProp($n_name, $c_name, $c_id);
         if (!is_null($val) && is_object($val) && isset($val->$prop))
            return $val->$prop;
      } else if (strpos($name, "[") !== false) {
         $arr = explode("[", $name);
         $n_name = $arr[0];
         $prop = substr(trim($arr[1]), 0, -1);
         $val = $this->_getProp($n_name, $c_name, $c_id);
         if (!is_null($val) && is_array($val) && isset($val[$prop]))
            return $val[$prop];
      }

      if (!isset($this->Parent))
         return null;

      return $this->Parent->_getProp($name, $c_name, $c_id);
   }
   private function IsVar(mixed $value): mixed
   {
      if(!is_string($value)) return null;
      
      $pattern = '/\{([\w\[\]\->]+)\}/';
      preg_match_all($pattern, $value, $matches);
      return isset($matches[1][0]) && !empty($matches[1][0]) ? $matches[1][0] : null;
   }

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
}

class LayoutComponent extends Component {
   protected function ParamsToRender(): array {
      return $this->Parameters; 
   }
}

function str_replace_first($search, $replace, $subject)
{
   $search = '/' . preg_quote($search, '/') . '/';
   $res = preg_replace($search, $replace, $subject, 1);
   return $res;
}