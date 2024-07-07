<?php
namespace DafDb;


/**
 * Class Sql
 * 
 * This class provides helper methods for working with SQL queries.
 * 
 * @package DafDb
 */
class Sql
{
   static function CreateTable(string $table, array $fields)
   {
      $primaryKeys = [];

      foreach ($fields as $key => $value) {
         if(str_contains($value, 'PRIMARY KEY')){
            $primaryKeys[] = $key;
         }
      }
      
      if(count($primaryKeys) > 1){
         foreach ($primaryKeys as $key) {
            $fields[$key] = str_replace(' PRIMARY KEY', '', $value);
         }
      }

      $query = "CREATE TABLE IF NOT EXISTS $table (";
      foreach ($fields as $field => $type) {
         $query .= "$field $type,";
      }
      $query = rtrim($query, ",");
      
      if (count($primaryKeys) > 1) {
         $pks = implode(",", $primaryKeys);
         $query .= ", PRIMARY KEY ($pks)";
      }
      $query .= ");";

      return $query;
   }

   static function Insert(string $table, array $data)
   {
      $query = "INSERT INTO $table (";
      $params = [];
      foreach ($data as $key => $value) {
         $query .= "`$key`,";
         $params[":$key"] = $value;
      }
      $query = rtrim($query, ",");
      $query .= ") VALUES (";
      foreach ($data as $key => $value) {
         $query .= ":$key,";
      }
      $query = rtrim($query, ",");
      $query .= ");";

      return ['query'=>$query, 'params'=>$params];
   }

   static function Where(callable $func)
   {
      $conditionsSql = self::ParseWhere($func);
      $query = "";

      $params = [];
      foreach($conditionsSql as $condition){
         //$query .= ' '.implode(' ', $condition); 
         $field = substr($condition['field'], 1, -1).count($params);
         $params[':'.$field] = $condition['value'];
         $query .= " " . $condition['whereOp'] ." " . $condition['field'] . ' ' . $condition['operator'] . " :$field"; 
      }
      $query = "WHERE " . trim($query);

      return ['query'=>$query, 'params'=>$params];
   }

   static function ParseWhere(callable $func) : array
   {
      // Create a reflection function from the closure
      $reflectedFunc = new \ReflectionFunction($func);

      // Get the closure's code
      $startLine = $reflectedFunc->getStartLine();
      $endLine = $reflectedFunc->getEndLine();
      $length = $endLine - $startLine;
      $length = $length > 0 ? $length + 1 : 1;
      
      $source = array_slice(file($reflectedFunc->getFileName()), $startLine - 1, $length);
      
      $markup = implode("", $source);
      $code = substr($markup, strpos($markup, '=>') + 2);
      $paramName = $reflectedFunc->getParameters()[0]->getName();

      // Split the code by logical operators
      $conditions = preg_split('/(\s*&&\s*|\s*\|\|\s*)/', $code, -1, PREG_SPLIT_DELIM_CAPTURE);
      $conditionsSql = [];


      // Get the static variables used in the closure
      $staticVars = $reflectedFunc->getStaticVariables();
      $whereOp = '';
      foreach ($conditions as $condition) {
         $condition = trim($condition);

         // Skip logical operators
         if ($condition === '&&' || $condition === '||') {
            $whereOp = $condition === '&&' ? 'AND' : 'OR';
            continue;
         }

         // Use regex to extract the field, operator, and value
         //preg_match('/\$'.$paramName.'\->(\w+)\s*(===|==|!=|<>|<=|>=|<|>)\s*([\$\w\->\[\]\'"]+)/', $condition, $matches);
         preg_match('/\$'.$paramName.'\->(\w+)\s*(===|==|!=|<>|<=|>=|<|>)\s*([\$\w\->\[\]\'"]+)|([\$\w\->\[\]\'"]+)\s*(===|==|!=|<>|<=|>=|<|>)\s*\$'.$paramName.'\->(\w+)/', $condition, $matches);

         if(count($matches) === 7){
            $matches[1] = $matches[6];
            $matches[2] = $matches[5];
            $matches[3] = $matches[4];
            unset($matches[4], $matches[5], $matches[6]);
         }

         if(empty($matches)){
            preg_match('/(\w+)\((.*?)\)/', $condition, $matches);

            if(!empty($matches)){
               $func = $matches[1];
               $func_args_str = $matches[2];
               preg_match('/\$'.$paramName.'\->(\w+)\s*,\s*(.*)/', $func_args_str, $matches);
               //field allready set in the sec match ($matches[1]) 
               //set value
               if($func === 'str_starts_with'){
                  $matches[3] = self::FormatValue($matches[2]).'%';
               }else if($func === 'str_ends_with'){
                  $matches[3] = '%'.self::FormatValue($matches[2]);
               }else if($func === 'str_contains'){
                  $matches[3] = '%'.self::FormatValue($matches[2]).'%';
               }
               //set operator
               $matches[2] = "LIKE";
            }
         }
         
         $field = $matches[1];
         $operator = $matches[2] === '===' || $matches[2] === '==' ? '=' : $matches[2]; // Convert '===' to '=' for SQL
         $value = $matches[3];
         
         if($value[0] === '$'){
            $wrapper = self::GetObject($value);
            if($wrapper && isset($staticVars[$wrapper['object']])){
               $value = $staticVars[$wrapper['object']]->{$wrapper['field']};
            } else {
               $wrapper = self::GetArray($value);
               if($wrapper && isset($staticVars[$wrapper['object']])){
                  $value = $staticVars[$wrapper['object']][$wrapper['field']];
               } else if(isset($staticVars[substr($value, 1)])){
                  $value = $staticVars[substr($value, 1)];
               }
            }
         } else $value = self::FormatValue($value);

         // Add the condition to the SQL query
         $field = '`'.$field.'`';
         //$value = self::FormatValue($value);
         $conditionsSql[] = ['whereOp'=>$whereOp, 'field'=>$field, 'operator'=>$operator, 'value'=>$value];
      }

      return $conditionsSql;
   }

   static private function FormatValue($value)
   {
       if(is_string($value)){
   
           if(is_numeric($value)){
               // Check if the numeric string is an integer or float
               if(floor($value) != $value){
                   // It's a float, so return as float
                   return floatval($value);
               } else {
                   // It's an integer, so return as int
                   return intval($value);
               }
           } elseif(strtolower($value) === 'true') {
               // It's a boolean true, so return as bool
               return true;
           } elseif(strtolower($value) === 'false') {
               // It's a boolean false, so return as bool
               return false;
           }

           // Remove quotes from start and end of string
           $value = trim($value, '\'"');
       }
       // It's not a string, or it's a string that doesn't represent a number or boolean, so return as is
       return $value;
   }

   static function GetObject(string $query) {
      if(!str_contains($query, '->')) return null;
      $arr = explode('->', $query);
      $arr[0] = substr($arr[0], 1);
      return ['object'=>$arr[0], 'field'=>$arr[1]];
   }
   static function GetArray(string $query) {
      if(!str_contains($query, '[')) return null;
      $arr = explode('[', $query);
      $arr[1] = substr($arr[1], 0, -1);
      $arr[1] = str_replace("'", '', $arr[1]);
      $arr[1] = str_replace('"', '', $arr[1]);
      $arr[0] = substr($arr[0], 1);
      return ['object'=>$arr[0], 'field'=>$arr[1]];
   }
}