<?php
namespace DafDb;

use DafDb\Migrations\Providers\IProviderSql;
use DafDb\Migrations\Providers\MysqlProviderSql;
use DafDb\Migrations\Providers\SqliteProviderSql;

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

    public bool $UseMigrations = false;
    public static $Show_errors = TRUE;
    public static $Show_Queary = FALSE;

    private ChangeTracker $tracker;
    private IProviderSql $sqlProvider;
    private bool $isSavingChanges = false;


    public function __construct(string $dns, ?string $username, ?string $password, int $maxConnections = 10)
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
        $this->tracker = new ChangeTracker();
        $this->sqlProvider = $this->createProvider($this);
        parent::__construct($this);
    }


    public function Tracker(): ChangeTracker { return $this->tracker; }
    public function IsSavingChanges(): bool { return $this->isSavingChanges; }
    public function GetSqlProvider(): IProviderSql { return $this->sqlProvider; }
    public function IsSqlite(): bool { return str_starts_with($this->dns, 'sqlite'); }
    
    public function Error($error, $obj = null)
    {
        if (self::$Show_errors) {
            if($obj === null) throw new \Exception($error);

            else if(is_array($obj) || is_object($obj))
                $obj = json_encode($obj, JSON_PRETTY_PRINT);

            throw new \Exception($error . ": " . $obj);
        }
    }
    public function GetConnection(): \PDO
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
    public function ReleaseConnection(\PDO $connection)
    {
        $key = array_search($connection, $this->connectionPool);
        if ($key !== false) {
            unset($this->connectionPool[$key]);
        }
    }
   

    
    public function SaveChanges(): void
    {
        if ($this->tracker->IsEmpty()) return;

        $entries = $this->tracker->Drain();
        $this->isSavingChanges = true;
        try {
            $this->BigTransaction(function() use ($entries) {
                foreach ($entries as $entry) {
                    switch ($entry->Operation) {
                        case 'add':
                            $entry->Repository->Add($entry->Entity);
                            break;
                        case 'update':
                            $entry->Repository->Update($entry->Entity, $entry->Filter);
                            break;
                        case 'remove':
                            $entry->Repository->Remove($entry->Entity ?? $entry->Filter);
                            break;
                    }
                }
            });
        } finally {
            $this->isSavingChanges = false;
        }
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
        $model = $attr_args['Model'] ?? $attr_args['model'] ?? $attr_args[1];
        $name = $attr_args['Name'] ?? $attr_args['name'] ?? $attr_args[0] ?? '';
  
        if (empty($name)) {
           $arr = explode("\\", $model);
           $name = end($arr) . "s";
        }

        if (empty($name))
           throw new \Exception("Table name is missing");
  
        if (empty($model))
           throw new \Exception("Model class is missing");

        $this->tableName = $name;
        $this->modelClass = $model;
        $this->LoadTableInfoFromClass($this->modelClass);

        return $this;
    }
    
    public function UseTable(string $tableName, ?string $modelClass = null): self
    {
        $this->tableName = $tableName;

        if ($modelClass !== null) {
            $this->modelClass = $modelClass;
            $this->LoadTableInfoFromClass($modelClass);
        } else {
            // dynamic mode: no reflection
            $this->modelClass = \stdClass::class;
            $this->ClearTableInfo();
        }

        return $this;
    }

    public function WithSchema(array $fields, array $primaryKeys = [], array $foreignKeys = [], array $includes = []): self
    {
        $this->SetTableInfo($fields, $primaryKeys, $foreignKeys, $includes); // implement in Queryable
        return $this;
    }


    // clear the connection pool
    function __destruct(){
        foreach($this->connectionPool as $connection){
            $connection = null;
        }
        $this->connectionPool = [];
    }
    private function createProvider(Context $context): IProviderSql{
            $Provider = $context->IsSqlite()
            ? new SqliteProviderSql()
            : new MysqlProviderSql();

        return $Provider;
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
    public function __construct(public string $Table, public string $Condition, public string | null $Model = null) { }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DbIgnore { }

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MaxLength {
    public function __construct(public int $Value) {}
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DefaultValue
{
    public function __construct(public mixed $Value) {
        if ($Value === null) {
            throw new \InvalidArgumentException("DefaultValue attribute requires Value.");
        }
    }
}
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DefaultValueSql
{
    public function __construct(public string $Sql) {
        if ($Sql === null) {
            throw new \InvalidArgumentException("DefaultValue attribute requires Sql Value.");
        }
    }
}