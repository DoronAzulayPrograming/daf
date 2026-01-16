<?php
namespace DafDb;

use DafGlobals\Collections\ICollection;
use DafGlobals\Collections\ReadOnlyCollection;

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
        'columns' => [],
        'columnTypes' => [],
        'primaryKeys' => [],
        'foreignKeys' => [],
        'includes' => []
    ];

    private array $includes = [];
    private bool $rowToArray = false;


    private bool $expectAliasedResult = false;
    private int $includeAliasCounter = 0;

    /** @var array<string,array> */
    private static array $modelMetadataCache = [];
    /** @var array<string,bool> */
    private static array $modelMetadataInProgress = [];


    public function __construct(Context $context)
    {   
        try {
            $this->context = $context;
            $this->connection = $this->context->GetConnection();
        } catch (\Throwable $th) {
            //throw $th;
        }
    }


    public function GetTableName(): string { return $this->tableName; }
    public function GetContext(): Context { return $this->context; }
    public function GetStatement(): \PDOStatement{
        return $this->stmt;
    }

    public function Reset()
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

        $this->includeAliasCounter = 0;
        $this->expectAliasedResult = false;
    }
    public function Execute(string $query, ...$params)
    {
        if(Context::$Show_Queary){
            var_dump($query);
            var_dump($params);
        }

        $result = $this->stmt = $this->connection->prepare($query);
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $this->stmt->bindValue($key, $value ? 1 : 0, \PDO::PARAM_INT);
            } elseif (is_int($value)) {
                $this->stmt->bindValue($key, $value, \PDO::PARAM_INT);
            } elseif (is_null($value)) {
                $this->stmt->bindValue($key, null, \PDO::PARAM_NULL);
            } else {
                $this->stmt->bindValue($key, $value, \PDO::PARAM_STR);
            }
        }
        
        // foreach ($params as $key => $value) {
        //     $this->stmt->bindValue($key, $value);
        // }
        $result = $this->stmt->execute();
        if ($result === false) {
            $this->context->Error("Error executing query: ", $this->stmt->errorInfo());
        }
    }
    public function Fetch() : mixed
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
    public function FetchAll(callable $callback = null): array {
        $list = [];
        $toArray = $this->rowToArray;
    
        $currentId = null;
        $data = [];
        
        $useAlias = $this->expectAliasedResult;

        while ($row = $this->stmt->fetch()) {
            $tempId = $useAlias
                    ? $this->getUniqIdByAlias($row, 't0')
                    : $this->getUniqIdByTable($row, $this->tableName);

            if ($currentId !== $tempId) {
                $currentId = $tempId;
    
                if ($this->global_data['skip'] !== null && $this->global_data['skip'] > 0) {
                    $this->global_data['skip']--;
                    continue;
                }
    
                if (!empty($data)) {
                    $data = $this->getByMode($data, $this->modelClass, $toArray);
    
                    if ($callback !== null) {
                        $data = $callback($data);
                        if ($data === false) {
                            return $list;
                        }
                    }
    
                    $list[] = $data;
                }
    
                $data = $useAlias
                    ? $this->getPropsFromRowByAlias($row, 't0')
                    : $this->getPropsFromRowByTable($row, $this->tableName);

            }
    
            foreach ($this->includes as $inc) {
                $incClass = $inc['model'];

                $props = $useAlias
                    ? $this->getPropsFromRowByAlias($row, $inc['alias'])
                    : $this->getPropsFromRowByTable($row, $inc['table']);
    
                // ✅ Skip if all fields in the include are null
                if (count(array_filter($props, fn($v) => $v !== null)) === 0) {
                    continue;
                }
    
                foreach ($inc['then'] as $thenInc) {
                    $thenClass = $thenInc['model'];
                    $thenProps = $useAlias
                        ? $this->getPropsFromRowByAlias($row, $thenInc['alias'])
                        : $this->getPropsFromRowByTable($row, $thenInc['table']);
    
                    // ✅ Skip if all fields in the then-include are null
                    if (count(array_filter($thenProps, fn($v) => $v !== null)) === 0) {
                        continue;
                    }
    
                    if ($thenInc['isMany']) {
                        $props[$thenInc['field']][] = $this->getByMode($thenProps, $thenClass, $toArray);
                    } else if (!isset($props[$thenInc['field']])) {
                        $props[$thenInc['field']] = $this->getByMode($thenProps, $thenClass, $toArray);
                    }
                }
    
                if ($inc['isMany']) {
                    $data[$inc['field']][] = $this->getByMode($props, $incClass, $toArray);
                } else if (!isset($data[$inc['field']])) {
                    $data[$inc['field']] = $this->getByMode($props, $incClass, $toArray);
                }
            }
        }
    
        if ($this->global_data['skip'] !== null && $this->global_data['skip'] > 0) {
            $this->global_data['skip']--;
            return $list;
        }
    
        if (!empty($data)) {
            $data = $this->getByMode($data, $this->modelClass, $toArray);
            if ($callback !== null) {
                $data = $callback($data);
                if ($data === false) {
                    return $list;
                }
            }
            $list[] = $data;
        }
    
        $this->Reset();
        return $list;
    }
    

    public function Update(object|array $data, callable $func = null): void {
        if (!isset($this->tableName) || empty($this->tableName))
            $this->context->Error("Table name not set in class: ", get_class($this) . " in Update");


        if (!$this->context->IsSavingChanges()) {
            $this->context->Tracker()->Enqueue($this, 'update', $data, $func);
            return;
        }

        $data = $this->_normalizeData($data);

        $whereClauses = [];
        $params = [];
        $t_data = [];
        foreach ($data as $key => $value) {
            if (in_array($key,  $this->tableInfo['primaryKeys'])) {
                $whereClauses[] = "`$key` = :pk_$key";
                $params[":pk_$key"] = $value;
            }else{
                $t_data[$key] = $value;
            }
        }
        $data = $t_data;

        $resUpdate = Sql::Update($this->tableName, $data);
        $query = $resUpdate['query'];

        if($func !== null){
            $res = Sql::Where($func);
            $query .= ' ' . $res['query'];
            $query .= ";";
            $params = array_merge($resUpdate['params'], $res['params']);
        }else{
            if (empty($whereClauses)) {
                throw new \Exception("Update failed: No primary key(s) provided.");
            }
            $query .= " WHERE " . implode(' AND ', $whereClauses);
            $query .= ";";
            $params = array_merge($params, $resUpdate['params']);
        }

        $this->Execute($query, ...$params);
    }
    public function Add(object|array $data): object
    {
        if (!isset($this->tableName) || empty($this->tableName))
            $this->context->Error("Table name not set in class: ", get_class($this) . " in Add");

        $entity = $this->ensureEntity($data);

        if (!$this->context->IsSavingChanges()) {
            $this->context->Tracker()->Enqueue($this, 'add', $entity);
            return $entity;
        }

        $values = $this->_normalizeData($entity);
        $res = Sql::Insert($this->tableName, $values);
        $this->Execute($res['query'], ...$res['params']);

        $pk = $this->tableInfo['primaryKeys'][0] ?? null;
        if ($pk && property_exists($entity, $pk) && empty($entity->$pk)) {
            $entity->$pk = $this->connection->lastInsertId();
        }

        return $entity;
    }
    public function Remove(callable|array|object $objOrfunc)
    {
        if (!isset($this->tableName) || empty($this->tableName))
            $this->context->Error("Table name not set in class: ", get_class($this) . " in Remove");

        if (!$this->context->IsSavingChanges()) {

            if (is_callable($objOrfunc)) {
                $this->context->Tracker()->Enqueue($this, 'remove', null, $objOrfunc);
            }
            else if (is_array($objOrfunc) || is_object($objOrfunc)){
                $this->context->Tracker()->Enqueue($this, 'remove', $objOrfunc);
            }

            return;
        }

        if (is_callable($objOrfunc)) {
            $this->_removeQuery($objOrfunc);
        }
        else if (is_array($objOrfunc) || is_object($objOrfunc)){
            $this->_removeObjectOrArray($objOrfunc);
        }
    }
    public function Clear() : void {
        if($this->context->IsSqlite())
            $query = "DELETE FROM {$this->tableName};";
        else
            $query = "TRUNCATE TABLE {$this->tableName};";
        
        $this->Execute($query);
    }


    public function Skip(int $length): self
    {
        $this->global_data['skip'] = $length;
        return $this;
    }
    public function Take(int $length): self
    {   
        $this->_limit($length + ($this->global_data['skip'] ?? 0));
        return $this;
    }
    public function Any(callable $func = null): bool
    {
        return $this->Count($func) > 0;
    }
    public function Count(callable $func = null): int
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
    public function Map(callable $callback): ICollection {
        list($query, $params) = $this->_prepareExecute();
        $this->Execute($query, ...$params);

        $list = $this->FetchAll(callback: $callback);
        return new ReadOnlyCollection($list);
    }
    public function ForEach(callable $callback) : void
    {
        list($query, $params) = $this->_prepareExecute();
        $this->Execute($query, ...$params);

        $this->FetchAll(callback: $callback);
    }


    public function FirstOrDefault(callable $func = null): mixed
    {
        if($func !== null)
            $this->Where($func);
        
        list($query, $params) = $this->_prepareExecute();
        $this->Execute($query, ...$params);

        return $this->Fetch();
    }
    public function SingleOrDefault(callable $func = null): mixed
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
    public function Where(callable $func): self
    {
        $res = $this->_where($func);

        $where_prefix = ' ';
        if(strlen($this->global_data['where']) > 0)
            $where_prefix = ' AND ';

        $this->global_data['where'] .= $where_prefix . $res['query'];
        $this->global_data['params'] = array_merge($this->global_data['params'], $res['params']);
        return $this;
    }
    public function OrderBy(callable $func): self
    {
        $this->_orderBy($func,'ASC');
        return $this;
    }
    public function OrderByDescending(callable $func): self
    {
        $this->_orderBy($func,'DESC');
        return $this;
    }


    public function Include(callable $func): self
    {
        $field = $this->_parseField($func);
        $modelMeta = $this->getModelMetadata($this->modelClass);

        if (!isset($modelMeta['includes'][$field])) {
            throw new \Exception("DbInclude metadata not found for {$this->modelClass}::{$field}");
        }

        $meta = $modelMeta['includes'][$field];
        $columns = $this->getModelMetadata($meta['model'])['columns'];

        $alias = $this->nextIncludeAlias();
        $select = $this->buildSelectFromColumns($columns, $alias);

        $condition = $this->aliasCondition($meta['condition'], $meta['table'], $alias, $this->tableName, 't0');

        $this->includes[] = [
            'alias' => $alias,
            'table' => $meta['table'],
            'field' => $field,
            'model' => $meta['model'],
            'isMany' => $meta['isMany'],
            'select' => $select,
            'condition' => $condition,
            'then' => [],
        ];

        return $this;
    }
    public function ThenInclude(callable $func): self
    {
        if (empty($this->includes)) {
            throw new \Exception("ThenInclude called before Include");
        }

        $field = $this->_parseField($func);
        $parent = &$this->includes[count($this->includes) - 1];
        $parentMeta = $this->getModelMetadata($parent['model']);

        if (!isset($parentMeta['includes'][$field])) {
            throw new \Exception("DbInclude metadata not found for {$parent['model']}::{$field}");
        }

        $meta = $parentMeta['includes'][$field];
        $columns = $this->getModelMetadata($meta['model'])['columns'];

        $alias = $this->nextIncludeAlias();
        $select = $this->buildSelectFromColumns($columns, $alias);

        $condition = $this->aliasCondition($meta['condition'], $meta['table'], $alias, $parent['table'], $parent['alias']);

        $parent['then'][] = [
            'alias' => $alias,
            'table' => $meta['table'],
            'field' => $field,
            'model' => $meta['model'],
            'isMany' => $meta['isMany'],
            'select' => $select,
            'condition' => $condition,
        ];

        return $this;
    }
    public function BigTransaction(callable $callback) : void {
        try {
            $this->connection->beginTransaction();

            $callback($this);

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }


    public function ToArray(): array
    {
        if(!isset($this->tableName) || empty($this->tableName))
            $this->context->Error("Table name not set in class: ", get_class($this) . " in ToArray");

        list($query, $params) = $this->_prepareExecute();
        $this->Execute($query, ...$params);
        return $this->FetchAll();
    }
    public function RowToArray(bool $value = true) : self{
        $this->rowToArray = $value;
        return $this;
    }
    public function ToCollection(): ICollection
    {
        if(!isset($this->tableName) || empty($this->tableName))
            $this->context->Error("Table name not set in class: ", get_class($this) . " in ToCollection");

        list($query, $params) = $this->_prepareExecute();
        $this->Execute($query, ...$params);

        return new ReadOnlyCollection($this->FetchAll());
    }
    public function QueryBuilder(): SqlQueriesBuilder
    {
        if (!isset($this->tableName) || empty($this->tableName))
            $this->context->Error("Table name not set in class: ", get_class($this) . " in QueryBuilder");

        $qb = new SqlQueriesBuilder($this);
        return $qb;
    }
    

    public function GetMetadata() { return $this->getModelMetadata($this->modelClass); }
    public function EnsureTableCreated(): void
    {
        $mapper = new \DafDb\Migrations\Mapper\AttributeToBuilderMapper();
        $t = $mapper->BuildTable($this->tableName, $this->modelClass);

        $provider = $this->context->GetSqlProvider();
        foreach ($provider->CompileCreateTable($t) as $sql) {
            $this->context->Execute($sql);
        }
    }


    // allow Context to clear or set tableInfo without reflection
    protected function ClearTableInfo(): void
    {
        $this->tableInfo = [
            'columns' => [],
            'primaryKeys' => [],
            'foreignKeys' => [],
            'includes' => []
        ];
    }
    protected function SetTableInfo(array $columns, array $primaryKeys = [], array $foreignKeys = [], array $includes = []): void
    {
        $this->tableInfo = [
            'columns' => $columns,
            'primaryKeys' => $primaryKeys,
            'foreignKeys' => $foreignKeys,
            'includes' => $includes
        ];
    }

    protected function LoadTableInfoFromClass($class)
    {
        $this->tableInfo = $this->getModelMetadata($class);
    }
    private function getModelMetadata(string $modelClass): array
    {
        if (isset(self::$modelMetadataCache[$modelClass])) {
            return self::$modelMetadataCache[$modelClass];
        }
        if (isset(self::$modelMetadataInProgress[$modelClass])) {
            // prevent circular recursion (User -> UserRole -> User)
            return ['columns' => [], 'includes' => []];
        }
        self::$modelMetadataInProgress[$modelClass] = true;

        $meta = [
            'columns' => [],
            'columnTypes' => [],
            'primaryKeys' => [],
            'foreignKeys' => [],
            'includes' => [],
        ];

        $known_attributes = [
            Attributes\PrimaryKey::class,
            Attributes\ForeignKey::class,
            Attributes\Unique::class,
            Attributes\AutoIncrement::class,
            Attributes\DbIgnore::class,
            Attributes\DbInclude::class
        ];

        $ref = new \ReflectionClass($modelClass);
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            /** @var \ReflectionProperty $prop */
            $name = $prop->getName();

            $type = $prop->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;
            $isBuiltIn = $type instanceof \ReflectionNamedType && $type->isBuiltin();
            $isDate = $typeName && is_a($typeName, \DafGlobals\Dates\IDate::class, true);

            $attributes = $prop->getAttributes();
            foreach ($attributes as $attr) {
                $attrName = $attr->getName();

                if (!in_array($attrName, $known_attributes)) continue;

                if ($attrName === Attributes\DbIgnore::class) {
                    continue 2;
                } 

                if ($attrName === Attributes\PrimaryKey::class) {
                    $meta['primaryKeys'][] = $name;
                } 

                if ($attrName === Attributes\ForeignKey::class) {
                    $attrInstane = $attr->newInstance();
                    $fk_tableName = $attrInstane->Value->Table;
                    $fk_tableColumn = $attrInstane->Value->Column;

                    $onDelete = '';
                    if (isset($attrInstane->Value->OnDelete)) {
                        $onDelete = 'ON DELETE ' . $attrInstane->Value->OnDelete;
                    }

                    $meta['foreignKeys'][$name] =
                        " FOREIGN KEY (`$name`) REFERENCES `{$fk_tableName}`(`{$fk_tableColumn}`) $onDelete";
                } 

                if ($attrName === Attributes\DbInclude::class) {
                    $attrInstane = $attr->newInstance();
                    $table = $attrInstane->Table;
                    $condition = $attrInstane->Condition;
                    
                    if (!$table || !$condition || !$type instanceof \ReflectionNamedType) {
                        continue 2;
                    }

                    $propType = $type->getName();
                    $isMany = in_array($propType, [
                        'array',
                        \DafGlobals\Collections\ICollection::class,
                        \DafGlobals\Collections\Collection::class,
                    ], true);

                    $targetModel = $propType;
                    if ($isMany) {
                        $targetModel = $attrInstane->Model;
                        if ($targetModel === null) {
                            throw new \Exception("DbInclude on {$modelClass}::{$name} requires model argument");
                        }
                    }

                    $meta['includes'][$name] = [
                        'table' => $table,
                        'condition' => $condition,
                        'model' => $targetModel,
                        'isMany' => $isMany,
                    ];

                    // recurse so nested includes are cached too
                    $this->getModelMetadata($targetModel);
                    continue 2;
                } 
            }

            // scalar column
            if ($isBuiltIn || $isDate) {
                $meta['columns'][] = $name;
                $meta['columnTypes'][$name] = $typeName;
            }
        }

        self::$modelMetadataCache[$modelClass] = $meta;
        unset(self::$modelMetadataInProgress[$modelClass]);
        return $meta;
    }

    private function getIncludeProps(): array
    {
        $rootAlias = 't0';

        $columns = $this->getModelMetadata($this->modelClass)['columns'];
        $select = $this->buildSelectFromColumns($columns, $rootAlias);

        $join = '';
        $order = $this->buildOrderByPrimaryKey($rootAlias);

        foreach ($this->includes as $include) {
            $select .= ',' . $include['select'];
            $join .= " LEFT JOIN {$include['table']} {$include['alias']} ON {$include['condition']}";
            foreach ($include['then'] as $then) {
                $select .= ',' . $then['select'];
                $join .= " LEFT JOIN {$then['table']} {$then['alias']} ON {$then['condition']}";
            }
        }

        return ['select' => $select, 'join' => $join, 'order' => $order];
    }
    private function getUniqIdByTable(array $row, string $table)
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
    private function getUniqIdByAlias(array $row, string $alias): string
    {
        $id = '';
        foreach ($this->tableInfo['primaryKeys'] as $pk) {
            $key = "{$alias}.{$pk}";
            if (isset($row[$key])) $id .= $row[$key] . ',';
        }
        return rtrim($id, ',');
    }
    private function getByMode(array $data, string $class, bool $toArray){
        if($toArray){
            return $data;
        } else {
            return new $class($data);
        }
    }

    private function hydrateValue(string $field, mixed $value): mixed
    {
        if ($value === null) return null;

        $type = $this->tableInfo['columnTypes'][$field] ?? null;
        if ($type && is_a($type, \DafGlobals\Dates\IDate::class, true)) {
            return $type::FromString((string)$value);
        }
        return $value;
    }
    private function getPropsFromRowByTable(array $row, string $table): array
    {
        $props = [];
        foreach ($row as $key => $value) {
            if (str_starts_with($key, $table . '.')) {
                $field = substr($key, strlen($table) + 1);
                $props[$field] = $this->hydrateValue($field, $value);
            } elseif (!str_contains($key, '.')) {
                $props[$key] = $this->hydrateValue($key, $value);
            }
        }
        return $props;
    }
    private function getPropsFromRowByAlias(array $row, string $alias): array
    {
        $props = [];
        $prefix = $alias . '.';
        $len = strlen($prefix);

        foreach ($row as $key => $value) {
            if (strncmp($key, $prefix, $len) === 0) {
                $field = substr($key, $len);
                $props[$field] = $this->hydrateValue($field, $value);
            }
        }
        return $props;
    }


    private function buildSelectFromColumns(array $columns, string $alias): string
    {
        if (empty($columns)) return "{$alias}.*";
        return implode(', ', array_map(fn($c) => "{$alias}.{$c} AS `{$alias}.{$c}`", $columns));
    }
    private function aliasCondition(string $condition, string $table, string $alias, ?string $parentTable = null, ?string $parentAlias = null): string {
        $condition = preg_replace('/\b' . preg_quote($table, '/') . '\b/', $alias, $condition);
        $condition = preg_replace('/\b' . preg_quote($this->tableName, '/') . '\b/', 't0', $condition);

        if ($parentTable && $parentAlias) {
            $condition = preg_replace('/\b' . preg_quote($parentTable, '/') . '\b/', $parentAlias, $condition);
        }

        return $condition;
    }
    private function nextIncludeAlias(): string
    {
        return 't' . (++$this->includeAliasCounter);
    }
    private function buildOrderByPrimaryKey(string $alias): string
    {
        $pks = $this->tableInfo['primaryKeys'] ?? [];
        if (empty($pks)) return '';
        $parts = array_map(fn($pk) => "{$alias}.{$pk}", $pks);
        return ' ORDER BY ' . implode(', ', $parts);
    }
    private function ensureEntity(object|array $data): object
    {
        if ($data instanceof $this->modelClass) return $data;

        $class = $this->modelClass;
        return new $class($data);
    }


    // normalize data for Add,Update functions
    private function _normalizeData(object|array $data): array
    {
        if (is_array($data)) return $data;

        $getValue = function($value){
            if ($value instanceof IDate) return (string)$value;
            else return $value;
        };

        $out = [];
        foreach ($this->tableInfo['columns'] as $_ => $field) {
            $getter = "_Get" . ucfirst($field);
            if (method_exists($data, $getter)) {
                $out[$field] = $getValue($data->$getter());
            } elseif (isset($data->$field)) {
                $out[$field] = $getValue($data->$field);
            }
        }
        return $out;
    }
    private function _removeObjectOrArray(object|array $row): void
    {
        $primaryKeys = $this->tableInfo['primaryKeys'] ?? [];
        if (empty($primaryKeys)) {
            throw new \Exception("Remove failed: No primary keys defined for {$this->tableName}");
        }

        $where = [];
        $params = [];

        foreach ($primaryKeys as $field) {

            // Get value from object or array
            if (is_array($row)) {
                if (!array_key_exists($field, $row)) {
                    throw new \Exception("Remove failed: Missing primary key '{$field}' in array for {$this->tableName}");
                }
                $value = $row[$field];
            } else {
                // Optional: support your getter convention _Get<Field>()
                $getter = "_Get" . ucfirst($field);
                if (method_exists($row, $getter)) {
                    $value = $row->$getter();
                } elseif (property_exists($row, $field)) {
                    $value = $row->$field;
                } else {
                    throw new \Exception("Remove failed: Missing primary key '{$field}' in object for {$this->tableName}");
                }
            }

            $where[] = "`{$field}` = :pk_{$field}";
            $params[":pk_{$field}"] = $value;
        }

        $query = "DELETE FROM {$this->tableName} WHERE " . implode(' AND ', $where) . ";";
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
        if (!isset($this->tableName) || empty($this->tableName)) {
            $this->context->Error("Table name not set in class: ", get_class($this) . " in Where");
        }
    
        $conditionsSql = Sql::ParseWhere($func);
        $query = "";
        $params = [];
    
        $useAlias = $this->expectAliasedResult || !empty($this->includes);

        foreach ($conditionsSql as $condition) {
            $rawField = substr($condition['field'], 1, -1);
            $paramKey = $rawField . count($params);
            $params[":$paramKey"] = $condition['value'];

            $qualifiedColumn = $useAlias
                ? "`t0`.`{$rawField}`"
                : (!empty($this->includes)
                    ? "`{$this->tableName}`.`{$rawField}`"
                    : "`{$rawField}`");

            $query .= " " . $condition['whereOp'] . " {$qualifiedColumn} {$condition['operator']} :$paramKey";
        }

        // foreach ($conditionsSql as $condition) {
        //     $rawField = substr($condition['field'], 1, -1); // remove backticks
    
        //     // Generate unique param name
        //     $paramKey = $rawField . count($params);
        //     $params[":$paramKey"] = $condition['value'];
    
        //     // Build qualified column (if includes exist, qualify with table name)
        //     if (!empty($this->includes)) {
        //         // use `Table`.`Column` format
        //         $qualifiedColumn = "`{$this->tableName}`.`{$rawField}`";
        //     } else {
        //         $qualifiedColumn = "`{$rawField}`";
        //     }
    
        //     $query .= " " . $condition['whereOp'] . " {$qualifiedColumn} {$condition['operator']} :$paramKey";
        // }
    
        $query = trim($query);
    
        return ['query' => $query, 'params' => $params];
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

        if (!empty($this->global_data['where'])) {
            $this->global_data['where'] = ' WHERE ' . trim($this->global_data['where']);
        }

        if ($this->global_data['count']) {
            $this->includes = [];
            $this->global_data['orderBy'] = '';

            $select_prefix = 'COUNT(';
            $select_suffix = ')';
            $this->global_data['where'] = str_replace($this->tableName.'.', '', $this->global_data['where']);
            $this->global_data['orderBy'] = str_replace($this->tableName.'.', '', $this->global_data['orderBy']);
        }

        if (!$this->global_data['count']) {
            $rootMeta = $this->getModelMetadata($this->modelClass);
            $this->global_data['select'] = $this->buildSelectFromColumns($rootMeta['columns'], 't0');
            $from = $this->tableName . ' t0';
            $this->expectAliasedResult = true;
        } else {
            $this->global_data['select'] = '*';
            $from = $this->tableName;
            $this->expectAliasedResult = false;
        }

        if (!empty($this->includes)) {
            $res = $this->getIncludeProps();
            $this->global_data['select'] .= ',' . $res['select'];
            $this->global_data['join'] = $res['join'];
            $this->global_data['orderBy'] = $res['order'];
        } else {
            $this->global_data['join'] = '';
        }

        $query = "SELECT $select_prefix" . $this->global_data['select'] . $select_suffix .
            " FROM " . $from .
            $this->global_data['join'] .
            $this->global_data['where'] .
            $this->global_data['orderBy'] .
            $this->global_data['limit'] . ';';

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