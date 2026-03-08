<?php
namespace DafCore\Views\Components;

class ComponentRegistry 
{
   private static array $Namespaces = [];
   private static int $NamespacesIncludesFoldersCount = 0;

   private static array $existsCache = [];   // path => bool
   private static array $classFileCache = []; // filePath => string|null
   private static array $fullPathCache = [];  // logical path => fullPath
   private static array $autoBindCache = [];  // className => metadata[]



   /** Register one or more folders for component path resolution. */
   public static function AddNamespaces(string|array $folders): void
   {
      if (is_array($folders)) {
         foreach ($folders as $f) self::AddNamespaces($f);
         return;
      }

      $folder = self::NormalizeFolderPath($folders);
      
      if ($folder === "") return;

      $key = "*" . (++self::$NamespacesIncludesFoldersCount);
      self::$Namespaces[$key] = $folder;
   }

   /** Resolve component path using include-folders (namespaces). */
   public static function ResolveFilePath(string $path): string
   {
      if (!is_win_os()) {
        $path = str_replace("\\", "/", $path);
        $path = rtrim($path, "\\/");
      }

      if (isset(self::$fullPathCache[$path])) {
         return self::$fullPathCache[$path];
      }

      // Direct alias mapping still supported if you use it elsewhere
      if (isset(self::$Namespaces[$path]) && self::$Namespaces[$path][0] !== "*") {
         return self::$fullPathCache[$path] = self::$Namespaces[$path];
      }

      // Search inside include folders only
      foreach (self::$Namespaces as $key => $folder) {
         if (!isset($key[0]) || $key[0] !== "*") continue;

         $classPath = $folder . DIRECTORY_SEPARATOR . $path;
         
         if (!is_win_os()) {
            $classPath = str_replace("\\", "/", $classPath);
            $classPath = rtrim($classPath, "\\/");
         }

         if (self::Exists($classPath . ".php")) {
               return self::$fullPathCache[$path] = $classPath;
         }
         else if (self::Exists($classPath . ".view.php")) {
               return self::$fullPathCache[$path] = $classPath;
         }
      }

      return self::$fullPathCache[$path] = $path;
   }

   public static function Exists(string $p): bool {
      if (isset(self::$existsCache[$p]))
         return self::$existsCache[$p];
      return self::$existsCache[$p] = file_exists($p);
   }

   public static function TryFindComponentClassFile(string $path): ?string
   {
      if (array_key_exists($path, self::$classFileCache)) {
         return self::$classFileCache[$path];
      }
      
      $classPath = $path . ".php";

      return self::$classFileCache[$path] = (self::Exists($classPath) ? $classPath : null);
   }

   /**
    * Return cached auto-bind metadata for a component class or create it once.
    * @param string $className
    * @param callable $factory fn(): array
    * @return array
    */
   public static function GetAutoBindCache(string $className, callable $factory): array
   {
      if (!array_key_exists($className, self::$autoBindCache)) {
         self::$autoBindCache[$className] = $factory();
      }

      return self::$autoBindCache[$className];
   }


   public static function NormalizeFolderPath(string $p): string
   {
      $p = self::resolveVendorPharAlias($p);
      $p = trim($p);
      if ($p === "") return "";

      // Keep phar:// prefix intact
      if (str_starts_with($p, "phar://")) {
         $rest = substr($p, 7); // after "phar://"

         if (!is_win_os()) {
            $rest = str_replace("\\", "/", $rest);
            $rest = rtrim($rest, "\\/");
         }
         return "phar://" . $rest;
      }

      // normal filesystem path
      if (!is_win_os()) {
         $p = str_replace("\\", "/", $p);
         $p = rtrim($p, "\\/");
      }

      return $p;
   }

   private static function resolveVendorPharAlias(string $p): string
   {
      $p = trim($p);
      if ($p === '') return '';

      // Accept Phar\vendor\...
      if (!str_starts_with($p, 'Phar\\vendor\\')) {
         return $p;
      }

      $normalized = str_replace('/', '\\', trim($p, "\\/"));
      $parts = explode('\\', $normalized);

      // Expect: Phar\vendor\ - <PharName>\ - <Rest...>
      if (count($parts) < 3) {
         return $p;
      }

      $pharName = $parts[2];
      if (!str_ends_with(strtolower($pharName), '.phar')) {
         $pharName .= '.phar';
      }

      $rest = array_slice($parts, 3);
      if(empty($rest)) $rest = '';
      else $rest = '\\' . implode('\\', $rest);

      // Keep backslashes; your existing normalizeFolderPath() already handles slash conversion per OS.
      return 'phar://vendor\\' . $pharName . $rest;
   }


}
