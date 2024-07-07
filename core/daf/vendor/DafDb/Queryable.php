<?php
namespace DafDb;

use DafGlobals\ICollection;
use DafGlobals\ReadOnlyCollection;

abstract class Queryable implements IQueryable, \IteratorAggregate, \JsonSerializable
{
    protected string $tableName;
    protected string $modelClass;
    protected Context $context;
    protected \PDO $connection;
    protected \PDOStatement $stmt;

    private $global_data = [
        'select' => '',
        'where' => '',
        'join' => '',
        'orderBy' => '',
        'limit' => '',
        'count' => false,
        'params' => [],
        'skip' => null,
        'take' => null
    ];

    private array $tableInfo = [
        'fields' => [],
        'primaryKeys' => [],
        'foreignKeys' => []
    ];

    private array $includes = [];
    private bool $rowToArray = false;

    public function __construct(Context $context)
    {
        try {
            $this->context = $context;
            $this->connection = $this->context->GetConnection();
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    private function Reset()
    {
        $this->global_data = [
            'select' => '',
            'where' => '',
            'join' => '',
            'orderBy' => '',
            'limit' => '',
            'count' => false,
            'params' => [],
            'skip' => null,
            'take' => null
        ];
        $this->includes = [];
        $this->rowToArray = false;
    }
    function Execute(string $query, ...$params)
    {
        $result = $this->stmt = $this->connection->prepare($query);
        foreach ($params as $key => $value) {
            $this->stmt->bindValue($key, $value);
        }
        $result = $this->stmt->execute();
        if ($result === false) {
            $this->context->Error("Error executing query: ", $this->stmt->errorInfo());
        }
    }
    function Fetch() : mixed
    {
        $result = [];
        $this->FetchAll(callback: function($item) use (&$result){
            $result[] = $item;
            return false;
        });
        
        if (empty($result))
            return null;

        return $result[0];
    }
    function FetchAll(callable $callback = null) : array {
        $list = [];
        $toArray = $this->rowToArray;

        $currentId = null;
        $data = [];
        
        while($row = $this->stmt->fetch()){
            $tempId = $this->getUniqId($row, $this->tableName, $this->modelClass);
            if($currentId !== $tempId){
                $currentId = $tempId;

                if($this->global_data['skip'] !== null && $this->global_data['skip'] > 0){
                    $this->global_data['skip']--;
                    continue;
                } 

                if(!empty($data)){

                    $data = $this->getByMode($data, $this->modelClass, $toArray);

                    if($callback !== null){
                        $data = $callback($data);
                        if($data === false){
                            return $list;
                        }
                    }

                    $list[] = $data;

                }
                
                $data = $this->getPropsFromRowByTable($row, $this->tableName);
            }    

            foreach ($this->includes as $inc) {
                $incClass = $inc['model'];
                $props = $this->getPropsFromRowByTable($row, $inc['table']);

                foreach ($inc['then'] as $thenInc){
                    $thenClass = $thenInc['model'];
                    $thenProps = $this->getPropsFromRowByTable($row, $thenInc['table']);

                    if($thenInc['isMany']){
                        $props[$thenInc['field']][] = $this->getByMode($thenProps, $thenClass, $toArray);
                    } else if (!isset($props[$thenInc['field']])){
                        $props[$thenInc['field']] = $this->getByMode($thenProps, $thenClass, $toArray);
                    }
                }

                if($inc['isMany']){
                    $data[$inc['field']][] = $this->getByMode($props, $incClass, $toArray);
                } else if (!isset($data[$inc['field']])){
                    $data[$inc['field']] = $this->getByMode($props, $incClass, $toArray);
                }
            }
        }

        if($this->global_data['skip'] !== null && $this->global_data['skip'] > 0){
            $this->global_data['skip']--;
            return $list;
        }

        if(!empty($data)){

            $data = $this->getByMode($data, $this->modelClass, $toArray);
            if($callback !== null){
                $data = $callback($data);
                if($data === false){
                    return $list;
                }
            }
            $list[] = $data;
        }

        $this->Reset();
        return $list;
    }


    function Add(object|array $data)
    {
        if (!isset($this->tableName) || empty($this->tableName))
            $this->context->Error("Table name not set in class: ", get_class($this) . " in Add");

        if (is_object($data)) {
            $new_data = [];
            foreach ($this->tableInfo['fields'] as $field => $type) {
                //check if have geter method then use it
                $getter = "_Get" . ucfirst($field);
                if (method_exists($data, $getter)) {
                    $new_data[$field] = $data->$getter();
                    continue;
                }
                if (!isset($data->{$field}))
                    continue;

                $new_data[$field] = $data->{$field};
            }
            $data = $new_data;
        }
        $res = Sql::Insert($this->tableName, $data);

        $this->Execute($res['query'], ...$res['params']);
    }
    function Remove(callable|object $objOrfunc)
    {
        if (!isset($this->tableName) || empty($this->tableName))
            $this->context->Error("Table name not set in class: ", get_class($this) . " in Remove");

        if (is_callable($objOrfunc)) {
            $this->_removeQuery($objOrfunc);
        }
        else if (is_object($objOrfunc)){
            $this->_removeObject($objOrfunc);
        }
    }
    function Clear() : void {
        if($this->context->IsSqlite())
            $query = "DELETE FROM {$this->tableName};";
        else
            $query = "TRUNCATE TABLE {$this->tableName};";
        
        $this->Execute($query);
    }


    function Skip(int $length): self
    {
        $this->global_data['skip'] = $length;
        return $this;
    }
    function Take(int $length): self
    {   
        $this->_limit($length + ($this->global_data['skip'] ?? 0));
        return $this;
    }
    function Any(callable $func = null): bool
    {
        return $this->Count($func) > 0;
    }
    function Count(callable $func = null): int
    {
        if($func !== null)
            $this->Where($func);

        $this->global_data['count'] = true;
        list($query, $params) = $this->_prepareExecute();
        $this->Execute($query, ...$params);

        // Fetch the count
        $count = $this->stmt->fetchColumn();
        $this->Reset();
        return $count;
    }
    function Map(callable $callback): ICollection {
        list($query, $params) = $this->_prepareExecute();
        $this->Execute($query, ...$params);

        $list = $this->FetchAll(callback: $callback);
        return new ReadOnlyCollection($list);
    }
    function ForEach(callable $callback) : void
    {
        list($query, $params) = $this->_prepareExecute();
        $this->Execute($query, ...$params);

        $this->FetchAll(callback: $callback);
    }


    function FirstOrDefault(callable $func = null): mixed
    {
        if($func !== null)
            $this->Where($func);
        
        list($query, $params) = $this->_prepareExecute();
        $this->Execute($query, ...$params);

        return $this->Fetch();
    }
    function SingleOrDefault(callable $func = null): mixed
    {
        if($func !== null)
            $this->Where($func);
        list($query, $params) = $this->_prepareExecute();
        $this->Execute($query, ...$params);

        $count = 0;
        $res = $this->FetchAll(callback: function($item) use (&$count){
            if($count > 1)
                return false;
            $count++;
            return $item;
        });

        if (empty($res))
            return null;

        if (count($res) > 1)
            $this->context->Error("More than one result found in SingleOrDefault");

        return $res[0];
    }
    function Where(callable $func): self
    {
        $res = $this->_where($func);

        $where_prefix = ' ';
        if(strlen($this->global_data['where']) > 0)
            $where_prefix = ' AND ';

        $this->global_data['where'] .= $where_prefix . $res['query'];
        $this->global_data['params'] = array_merge($this->global_data['params'], $res['params']);
        return $this;
    }
    function OrderBy(callable $func): self
    {
        $this->_orderBy($func,'ASC');
        return $this;
    }
    function OrderByDescending(callable $func): self
    {
        $this->_orderBy($func,'DESC');
        return $this;
    }


    function Include(callable $func) : self {
        $this->global_data['loaded'][$this->tableName] = true;

        $field = $this->_parseField($func);

        $ref = new \ReflectionClass($this->modelClass);
        $prop = $ref->getProperty($field);
        $attr = $prop->getAttributes(\DafDb\Attributes\DbInclude::class)[0];
        $attr_args = $attr->getArguments();

        $table = $attr_args['Table'];

        $condition = $attr_args['Condition'];
        $propTypeName = $prop->getType()->getName();

        $isMany = false;
        if($propTypeName === 'array'){
            $isMany = true;
            $propModel = $attr_args['Model'];
            if($propModel === null){
                throw new \Exception("Model not set in DbInclude attribute");
            }
            $propTypeName = $propModel;
        }

        $columns = [];
        if($this->context->IsSqlite()){
            $columns_query = "PRAGMA table_info($table)";
            $this->Execute($columns_query);
            $columns = $this->stmt->fetchAll(\PDO::FETCH_COLUMN, 1);
        }else{
            $columns_query = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :database AND TABLE_NAME = :table";
            $this->Execute($columns_query, ...array(':database' => $this->context->database,':table' => $table));
            $columns = $this->stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
        }
        
        $select = implode(", ", array_map(fn($c) => "$table.$c AS `$table.$c`", $columns));
        $this->includes[] = ['then'=>[], 'model' => $propTypeName, 'isMany' => $isMany, 'field' => $field, 'select' => $select ,'table' => $table, 'condition' => $condition];
        
        return $this;
    }
    function ThenInclude(callable $func) : self {
        $field = $this->_parseField($func);
        $last_include = &$this->includes[count($this->includes) - 1];
        
        $ref = new \ReflectionClass($last_include['model']);
        $prop = $ref->getProperty($field);
        $attr = $prop->getAttributes(\DafDb\Attributes\DbInclude::class)[0];
        $attr_args = $attr->getArguments();

        $table = $attr_args['Table'];
        $condition = $attr_args['Condition'];
        $propTypeName = $prop->getType()->getName();

        $isMany = false;
        if($propTypeName === 'array'){
            $isMany = true;
            $propModel = $attr_args['Model'];
            if($propModel === null){
                throw new \Exception("Model not set in DbInclude attribute");
            }
            $propTypeName = $propModel;
        }


        $columns = [];
        if($this->context->IsSqlite()){
            $columns_query = "PRAGMA table_info($table)";
            $this->Execute($columns_query);
            $columns = $this->stmt->fetchAll(\PDO::FETCH_COLUMN, 1);
        }else{
            $columns_query = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = $table";
            $this->Execute($columns_query, ...array(':table' => $table));
            $columns = $this->stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
        }

        $select = implode(", ", array_map(fn($c) => "$table.$c AS `$table.$c`", $columns));

        $last_include['then'][] = ['model' => $propTypeName, 'isMany' => $isMany, 'field' => $field, 'select' => $select ,'table' => $table, 'condition' => $condition];
        
        return $this;
    }
    function BigTransaction(callable $callback) : void {
        try {
            $this->connection->beginTransaction();

            $callback($this);

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
    

    function ToArray(): array
    {
        if(!isset($this->tableName) || empty($this->tableName))
            $this->context->Error("Table name not set in class: ", get_class($this) . " in ToArray");

        list($query, $params) = $this->_prepareExecute();
        $this->Execute($query, ...$params);
        return $this->FetchAll();
    }
    function RowToArray() : self{
        $this->rowToArray = true;
        return $this;
    }
    function ToCollection(): ICollection
    {
        if(!isset($this->tableName) || empty($this->tableName))
            $this->context->Error("Table name not set in class: ", get_class($this) . " in ToCollection");

        list($query, $params) = $this->_prepareExecute();
        $this->Execute($query, ...$params);

        return new ReadOnlyCollection($this->FetchAll());
    }
    function QueryBuilder(): SqlQueriesBuilder
    {
        if (!isset($this->tableName) || empty($this->tableName))
            $this->context->Error("Table name not set in class: ", get_class($this) . " in QueryBuilder");

        $qb = new SqlQueriesBuilder($this->tableName);
        return $qb;
    }
    
    
    
    protected function CreateTable() {
 
        $query = "CREATE TABLE IF NOT EXISTS {$this->tableName} (";
        $fields = $this->tableInfo['fields'];
  
        if(count($this->tableInfo['primaryKeys']) > 1){
           foreach ($this->tableInfo['primaryKeys'] as $key) {
              $fields["`$key`"] = str_replace(' PRIMARY KEY', '', $fields[$key]);
           }
        }
  
        foreach ($fields as $field => $type) {
           $query .= "`$field` $type,";
        }
        $query = rtrim($query, ",");
  
        if(count($this->tableInfo['primaryKeys']) > 1){
           $pks = implode(",", array_map(fn($pk)=>"`$pk`", $this->tableInfo['primaryKeys']));
           $query .= ", PRIMARY KEY ($pks)";
        }
        
        if(count($this->tableInfo['foreignKeys']) > 0){
           $fks = implode(",", $this->tableInfo['foreignKeys']);
           $query .= ", $fks";
        }
  
        $query .= ");";
         
         $this->Execute($query);
     }
    protected function LoadTableInfoFromClass($class)
    {
       $isSqlite = $this->context->IsSqlite();
       //reflection for get array of properties as prop name as key prop value as value
       try {
          $reflection = new \ReflectionClass($class);
       } catch (\Throwable $th) {
          echo $th->getMessage();
       }
 
       $props = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
       
       foreach ($props as $prop) {
          $field_name = $prop->getName();
          $type = $prop->getType();
          $field_type = $type->getName();
          $field_type = match ($field_type) {
             'int' => 'INTEGER',
             'string' => 'TEXT',
             'float' => 'FLOAT',
             'bool' => 'BOOLEAN',
             default => null,
          };
 
          if (!$field_type)
             continue;
 
          // check the prop type is nullable
          $isNullable = $type && $type->allowsNull();
          if (!$isNullable) {
             $field_type .= ' NOT NULL';
          }
 
          $known_attributes = [
             Attributes\PrimaryKey::class,
             Attributes\ForeignKey::class,
             Attributes\Unique::class,
             Attributes\AutoIncrement::class,
             Attributes\DbIgnore::class,
          ];
 
          //get prop attributes
          $attributes = $prop->getAttributes();
          foreach ($attributes as $attr) {
             $attrName = $attr->getName();
 
             if (!in_array($attrName, $known_attributes))
                continue;
 
             if ($attrName === Attributes\DbIgnore::class){
                continue 2;
             }
             else if ($attrName === Attributes\PrimaryKey::class) {
                $field_type .= ' PRIMARY KEY';
                $this->tableInfo['primaryKeys'][] = $field_name;
             } 
             else if ($attrName === Attributes\ForeignKey::class) {
                $attrArgs = $attr->getArguments();
                $onDelete = '';
                if(isset($attrArgs['OnDelete'])){
                   $onDelete = 'ON DELETE ' . $attrArgs['OnDelete'];
                }
                $this->tableInfo['foreignKeys'][$field_name] = " FOREIGN KEY (`$field_name`) REFERENCES `{$attrArgs['Table']}`(`{$attrArgs['Column']}`) $onDelete";
             } 
             else if ($attrName === Attributes\AutoIncrement::class) {
                if ($isSqlite)
                   $field_type .= ' AUTOINCREMENT';
                else
                   $field_type .= ' AUTO_INCREMENT';
             } else if ($attrName === Attributes\Unique::class) {
                $field_type .= ' UNIQUE';
             }
          }
 
          $this->tableInfo['fields'][$field_name] = $field_type;
       }
    }



    private function getUniqId(array $row, string $table, string $modelClass)
    {
        $id = "";
        $primaryKeys = $this->tableInfo['primaryKeys'];
        foreach($primaryKeys as $primaryKey){
            if(isset($row[$table . '.' . $primaryKey]))
                $id .= $row[$table . '.' . $primaryKey] . ',';
            else if(isset($row[$primaryKey]))
                $id .= $row[$primaryKey] . ',';
        }
        return rtrim($id, ',');
    }
    private function getPropsFromRowByTable(array $row, string $table): array
    {
        $props = [];
        foreach($row as $key => $value){
            if(str_starts_with($key, $table. '.')){
                $newKey = str_replace($table . '.', '', $key);
                $props[$newKey] = $value;
            } else if (!str_contains($key, '.')){
                $props[$key] = $value;
            }
        }
        return $props;
    }
    private function getByMode(array $data, string $class, bool $toArray){
        if($toArray){
            return $data;
        } else {
            return new $class($data);
        }
    }
    private function getIncludeProps() : array {
        if (!isset($this->tableName) || empty($this->tableName))
            $this->context->Error("Table name not set in class: ", get_class($this) . " in OrderBy");
        
        $columns = [];
        if($this->context->IsSqlite()){
            $columns_query = "PRAGMA table_info({$this->tableName})";
            $this->Execute($columns_query);
            $columns = $this->stmt->fetchAll(\PDO::FETCH_COLUMN, 1);
        }else{
            $columns_query = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :database AND TABLE_NAME = :table";
            $this->Execute($columns_query, ...array(':database' => $this->context->database, ':table' => $this->tableName));
            $columns = $this->stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
        }
        
        $select = implode(", ", array_map(fn($c) => "{$this->tableName}.$c AS `{$this->tableName}.$c`", $columns));
        $query = $select;
        $join = '';
        
        $loaded = [$this->tableName => true];
        foreach ($this->includes as $include) {
            if(isset($loaded[$include['table']])) continue;
            $loaded[$include['table']] = true;

            $query .= ',' . $include['select'];
            $join .= ' JOIN ' . $include['table'] . ' ON ' . $include['condition'];
            if(!empty($include['then'])){
                foreach ($include['then'] as $thenInclude) {
                    if(isset($loaded[$thenInclude['table']])) continue;
                    $loaded[$thenInclude['table']] = true;
    
                    $query .= ',' . $thenInclude['select'];
                    $join .= ' JOIN ' . $thenInclude['table'] . ' ON ' . $thenInclude['condition'];
                }
            }
        }
        return ['select'=>$query, 'join'=>$join];
    }


    private function _removeObject(object $obj)
    {
        $query = "DELETE FROM {$this->tableName} WHERE ";
        $primaryKeys = $this->tableInfo['primaryKeys'];
        $params = [];
        foreach ($primaryKeys as $field) {
            $query .= "`$field` = :$field AND ";
            $params[":$field"] = $obj->$field;
        }
        $query = rtrim($query, "AND ");
        $this->Execute($query, ...$params);
    }
    private function _removeQuery(callable $func)
    {
        $res = Sql::Where($func);
        $res['query'] = 'DELETE FROM ' . $this->tableName . ' ' . $res['query'] . ';';

        $this->Execute($res['query'], ...$res['params']);
    }
    private function _limit(int $limit): self
    {
        $this->global_data['limit'] = ' LIMIT ' . $limit;
        return $this;
    }
    private function _orderBy(callable $func, string $order): self
    {
        if (!isset($this->tableName) || empty($this->tableName))
            $this->context->Error("Table name not set in class: ", get_class($this) . " in OrderBy");

        $column = $this->_parseField($func);
        $column_prefix = !empty(($this->includes)) ? $this->tableName . '.' : '';

        $prefix = '';
        if(strlen($this->global_data['orderBy']) > 0)
            $prefix = ',';

        $this->global_data['orderBy'] .= $prefix.' ORDER BY `' . $column_prefix . $column . '` ' . $order;
        return $this;
    }
    private function _where(callable $func)
    {
        if (!isset($this->tableName) || empty($this->tableName))
            $this->context->Error("Table name not set in class: ", get_class($this) . " in Where");

       $conditionsSql = Sql::ParseWhere($func);
       $query = "";
 
       $params = [];
       $column_prefix = !empty(($this->includes)) ? $this->tableName . '.' : '';

       foreach($conditionsSql as $condition){
          $field = substr($condition['field'], 1, -1).count($params);
          $params[':'.$field] = $condition['value'];
          $query .= " " . $condition['whereOp'] ." `" . ($column_prefix.substr($condition['field'], 1, -1)) . '` ' . $condition['operator'] . " :$field"; 
       }
       //$query = "WHERE " . trim($query);
       $query = trim($query);
 
       return ['query'=>$query, 'params'=>$params];
    }
    private function _parseField(callable $func) : string
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
       $paramName1 = $reflectedFunc->getParameters()[0]->getName();
 
       $condition = trim($code);

       // Use regex to extract the field, operator, and value
       preg_match('/\$'.$paramName1.'\->(\w+)\s*/', $condition, $matches);
       
       $field = $matches[1];
 
       return $field;
    }
    

    private function _prepareExecute() : array {

        if (!isset($this->tableName) || empty($this->tableName))
            $this->context->Error("Table name not set in class: ", get_class($this));

        $select_prefix = '';
        $select_suffix = '';

        if(!empty($this->global_data['where'])){
            $this->global_data['where'] = ' WHERE ' . trim($this->global_data['where']);
        }

        if($this->global_data['count']){
            $select_prefix = 'COUNT(';
            $select_suffix = ')';
            $this->global_data['where'] = str_replace($this->tableName.'.', '', $this->global_data['where']);
            $this->global_data['orderBy'] = str_replace($this->tableName.'.', '', $this->global_data['orderBy']);
        }
        if(!$this->global_data['count'] && !empty($this->includes)){
            $res = $this->getIncludeProps();

            $this->global_data['select'] = $res['select'];
            $this->global_data['join'] = $res['join'];
        } else {
            $this->global_data['select'] = "*";
        }

        $query = "SELECT $select_prefix" . $this->global_data['select'] . $select_suffix . " FROM " . $this->tableName . $this->global_data['join'] . $this->global_data['where'] . $this->global_data['orderBy'] . $this->global_data['limit'] .';';
        //echo $query . "\n";
        $params = $this->global_data['params'];
        return [$query, $params];
    }
    function getIterator(): \Traversable
    {
        list($query, $params) = $this->_prepareExecute();
        $this->Execute($query, ...$params);

        return new \ArrayIterator($this->FetchAll());
    }
    function __debugInfo(){
        list($query, $params) = $this->_prepareExecute();
        $this->Execute($query, ...$params);

        return $this->FetchAll();
    }
    function __toString(){
        return json_encode($this->__debugInfo());
    }
    function jsonSerialize() {
        return $this->__debugInfo();
    }
    function __destruct()
    {
        if (isset($this->context) && isset($this->connection))
            $this->context->ReleaseConnection($this->connection);
    }
}


class SqlQueriesBuilder
{
    protected array $result = [
        'query' => '',
        'params' => []
    ];

    public function __construct(protected string $tableName) { }

    function Delete(): self
    {
        $this->result['query'] = 'DELETE FROM ' . $this->tableName;
        return $this;
    }
    function Select(string | array $columns = '*'): self
    {
        if(is_array($columns))
            $columns = implode(',', $columns);

        $this->result['query'] = 'SELECT ' . $columns . ' FROM ' . $this->tableName;
        return $this;
    }
    function Where(callable $func): self
    {
        $res = Sql::Where($func);
        $this->result['query'] .= ' ' . $res['query'];

        $this->result['params'] = array_merge($this->result['params'], $res['params']);

        return $this;
    }
    function Limit(int $limit): self
    {
        $this->result['query'] .= ' LIMIT ' . $limit;
        return $this;
    }
    function OrderBy(callable $func): self
    {
        $this->_orderBy($func, 'ASC');
        return $this;
    }
    function OrderByDescending(callable $func): self
    {
        $this->_orderBy($func, 'DESC');
        return $this;
    }
    function Join(string $table, callable $func) :self{
        $res = $this->_parseWhere($func);
        $query = ' JOIN ' . $table . ' ON ' . $this->tableName. '.' .$res['field'] . $res['operator'] . $table . '.' .$res['value'];
        $this->result['query'] .= $query;
        
        return $this;
    }
    function Build(): array
    {
        if(!isset($this->result['params'])) $this->result['params'] = [];
        $this->result[0] = $this->result['query'];
        $this->result[1] = $this->result['params'];
        return $this->result;
    }


    private function _parseWhere(callable $func) : array
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
       $paramName1 = $reflectedFunc->getParameters()[0]->getName();
       $paramName2 = $reflectedFunc->getParameters()[1]->getName();
 
       $condition = trim($code);

       // Use regex to extract the field, operator, and value
       preg_match('/\$'.$paramName1.'\->(\w+)\s*(===|==|!=|<>|<=|>=|<|>)\s*\$'.$paramName2.'\->(\w+)/', $condition, $matches);

       $field = $matches[1];
       $operator = $matches[2] === '===' || $matches[2] === '==' ? '=' : $matches[2]; // Convert '===' to '=' for SQL
       $value = $matches[3];
 
       return ['field'=>$field, 'operator'=>$operator, 'value'=>$value];
    }
    private function _parseField(callable $func) : string
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
       $paramName1 = $reflectedFunc->getParameters()[0]->getName();
 
       $condition = trim($code);

       // Use regex to extract the field, operator, and value
       preg_match('/\$'.$paramName1.'\->(\w+)\s*/', $condition, $matches);
       
       $field = $matches[1];
 
       return $field;
    }
 
    private function _orderBy(callable $func, string $order): self
    {
        $column = $this->_parseField($func);
        $prefix = '';
        if(isset($this->result['query']) && str_contains($this->result['query'], 'ORDER BY'))
            $prefix = ',';

        $this->result['query'] .= $prefix.' ORDER BY `' . $column . '` ' . $order;
        return $this;
    }
}