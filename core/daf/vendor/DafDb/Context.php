<?php
namespace DafDb;

/**
 * class Context
 * extends Queryable
 * 
 * 
 * This class is a base class and need to inject for all repositories.
 * It implements the IRepository interface and provides implementations for all methods.
 * It also provides some helper methods for working with database queries.
 * 
 * @package DafDb
 */
class Context extends Queryable
{
    public string $database;
    protected string $dns;
    private ?string $username;
    private ?string $password;
    private array $options = [];
    private int $maxConnections;
    private array $connectionPool = [];

    public static $Show_errors = TRUE;


    function __construct(string $dns, ?string $username, ?string $password, int $maxConnections = 10)
    {
        $this->dns = $dns;
        $this->username = $username;
        $this->password = $password;
        $this->options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $this->maxConnections = $maxConnections;
        parent::__construct($this);
    }

    function Error($error, $obj = null)
    {
        if (self::$Show_errors) {
            if($obj === null)
                throw new \Exception($error);

            else if(is_array($obj) || is_object($obj))
                $obj = json_encode($obj, JSON_PRETTY_PRINT);

            throw new \Exception($error . ": " . $obj);
        }
    }
    function GetConnection(): \PDO
    {
        if (count($this->connectionPool) < $this->maxConnections) {
            $connection = new \PDO($this->dns, $this->username, $this->password, $this->options);

            if (!$connection) {
                $this->Error("Connection failed: ", $connection->errorInfo());
            } else{
                $this->connectionPool[] = $connection;
            }
        } else {
            $connection = array_shift($this->connectionPool);
        }
        return $connection;
    }
    // delete the connection from the pool
    function ReleaseConnection(\PDO $connection)
    {
        $key = array_search($connection, $this->connectionPool);
        if ($key !== false) {
            unset($this->connectionPool[$key]);
        }
    }
    function IsSqlite(): bool
    {
        return str_starts_with($this->dns, 'sqlite');
    }
   
    function Table(string $className) : self
    {
        if(isset($this->tableName) && $this->tableName === $className)
            return $this;

        $ref = new \ReflectionClass($className);
        $table_attr = $ref->getAttributes(Attributes\Table::class);
        if(empty($table_attr))
           throw new \Exception("Table attribute is missing");
  
        $attr_args = $table_attr[0]->getArguments();
        $model = $attr_args['model'];
        $name = isset($attr_args['name']) ? $attr_args['name'] : '';
  
        if (empty($name)) {
           $arr = explode("\\", $model);
           $name = end($arr) . "s";
        }

        if (empty($name))
           throw new \Exception("Table name is missing");
  
        if (empty($model))
           throw new \Exception("Model class is missing");

        $this->tableName = $name;
        $this->_model($model);

        return $this;
    }
    function CreateTable()
    {
        if (!isset($this->tableName) || empty($this->tableName))
            $this->context->Error("Table name not set in class: ", get_class($this) . " in CreateTable");
        if (!isset($this->tableName) || empty($this->tableName))
            $this->context->Error("Model name not set in class: ", get_class($this) . " in CreateTable");

        parent::CreateTable();
    }

    private function _model(string $name)
    {
        $this->modelClass = $name;
        $this->LoadTableInfoFromClass($this->modelClass);
    }
    // clear the connection pool
    function __destruct(){
        foreach($this->connectionPool as $connection){
            $connection = null;
        }
        $this->connectionPool = [];
    }
}


namespace DafDb\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Table
{
    public function __construct(public string $Name = "", public string $Model) {}
}
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ForeignKey
{
    public $Value;

    public function __construct(public string $Table, public string $Column, public ?string $OnDelete = null) {
        $this->Value = new \stdClass();
        $this->Value->OnDelete = $OnDelete;
        $this->Value->Table = $Table;
        $this->Value->Column = $Column;
    }
}
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class PrimaryKey { }

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Unique { }

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class AutoIncrement { }


#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DbInclude
{
    public function __construct(public string $Table, string $Condition, string $Model = null) { }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DbIgnore { }