<?php
namespace DafDb\Context;

use DafDb\Query\DbSet;
use DafDb\Query\SqlCommand;
use DafDb\Helpers\TableInfo;
use DafDb\Helpers\ColumnInfo;
use DafDb\Helpers\IncludeInfo;
use DafDb\Query\ChangeTracker;
use DafDb\Query\ChangesResolver;
use DafDb\Helpers\ForeignKeyInfo;
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
abstract class Context
{
    public string $database;
    protected string $dns;
    private ?string $username;
    private ?string $password;
    private array $options = [];

    public static $Show_errors = TRUE;
    public static $Show_Queary = FALSE;

    private ChangeTracker $tracker;
    private IProviderSql $sqlProvider;
    protected \PDO|null $connection = null;
    protected \PDOStatement $stmt;

    
    private static array $modelMetadataCache = [];      // [class-string => TableInfo]
    private static array $modelMetadataInProgress = []; // [class-string => true]


    public function __construct(string $dns, ?string $username, ?string $password)
    {
        $this->dns = $dns;
        $this->username = $username;
        $this->password = $password;
        $this->options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $this->connection = $this->connect();
        $this->tracker = new ChangeTracker();
        $this->sqlProvider = $this->createProvider($this);
    }


    public function Table(string $dbSetClassName): DbSet
    {
        $obj = new $dbSetClassName($this);
        if (!$obj instanceof DbSet) {
            throw new \Exception("{$dbSetClassName} must extend " . DbSet::class);
        }
        return $obj;
    }

    public function GetStatement(): \PDOStatement { return $this->stmt; }
    public function GetConnection(): \PDO { return $this->connection; }

    public function Tracker(): ChangeTracker { return $this->tracker; }
    public function GetSqlProvider(): IProviderSql { return $this->sqlProvider; }

    public abstract function GetDbType(): string;
    public function IsDbType(string $dbType): bool { return $this->GetDbType() === $dbType; }
    
    public function Error(string $error, mixed $obj = null)
    {
        if($obj === null) throw new \Exception($error);

        else if(is_array($obj) || is_object($obj))
            $obj = json_encode($obj, JSON_PRETTY_PRINT);

        throw new \Exception($error . ": " . $obj);
    }

    
    public function SaveChanges(): int
    {
        if ($this->connection === null || $this->tracker->IsEmpty()) return 0;

        $changesResolver = new ChangesResolver();
        $changes = $this->tracker->Drain();
        return $changesResolver->Commit($this, $changes);
    }

    public function Execute(SqlCommand $cmd): \PDOStatement
    {
        if(self::$Show_Queary){
            var_dump($cmd->Query);
            var_dump($cmd->Parameters);
        }

        $this->stmt = $this->connection->prepare($cmd->Query);
        if (!$this->stmt) {
            $this->Error("Error preparing query", $this->connection->errorInfo());
        }

        foreach ($cmd->Parameters as $key => $value) {
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

        
        $result = $this->stmt->execute();
        if ($result === false) $this->Error("Error executing query: ", $this->stmt->errorInfo());

        return $this->stmt;
    }

    public function BigTransaction(callable $callback): void
    {
        try {
            $this->connection->beginTransaction();

            $callback($this);

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }


    private function connect(): \PDO
    {
        $connection = new \PDO($this->dns, $this->username, $this->password, $this->options);

        return $connection;
    }
    private function createProvider(Context $context): IProviderSql{
            $Provider = $context->IsDbType(SqliteContext::Type)
            ? new SqliteProviderSql()
            : new MysqlProviderSql();

        return $Provider;
    }

    public static function getModelMetadata(string $tableName, string $modelClass): TableInfo
    {
        $cacheKey = $modelClass . '|' . $tableName;

        if (isset(self::$modelMetadataCache[$cacheKey])) {
            return self::$modelMetadataCache[$cacheKey];
        }

        if (isset(self::$modelMetadataInProgress[$cacheKey])) {
            // prevent circular recursion (User -> UserRole -> User)
            // Return a minimal TableInfo (no columns/includes) so caller can continue safely.
            return new TableInfo($tableName, $modelClass);
        }

        self::$modelMetadataInProgress[$cacheKey] = true;

        $table = new TableInfo($tableName, $modelClass);

        $known_attributes = [
            \DafDb\Attributes\PrimaryKey::class,
            \DafDb\Attributes\ForeignKey::class,
            \DafDb\Attributes\Unique::class,
            \DafDb\Attributes\AutoIncrement::class,
            \DafDb\Attributes\DbIgnore::class,
            \DafDb\Attributes\DbInclude::class,
        ];

        $ref = new \ReflectionClass($modelClass);

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            /** @var \ReflectionProperty $prop */
            $name = $prop->getName();

            $type = $prop->getType();


            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;
            $isBuiltIn = $type instanceof \ReflectionNamedType && $type->isBuiltin();
            $isDate = $typeName && is_a($typeName, \DafGlobals\Dates\IDate::class, true);
            $isAutoIncrement = false;

            $attributes = $prop->getAttributes();

            foreach ($attributes as $attr) {
                $attrName = $attr->getName();
                if (!in_array($attrName, $known_attributes, true)) {
                    continue;
                }

                if ($attrName === \DafDb\Attributes\AutoIncrement::class) {
                    $isAutoIncrement = true;
                }

                if ($attrName === \DafDb\Attributes\DbIgnore::class) {
                    // ignore this prop completely
                    continue 2;
                }

                if ($attrName === \DafDb\Attributes\PrimaryKey::class) {
                    $table->AddPrimaryKey($name);
                    // do not "continue 2" because PK can also be a scalar column (we still want it in columns)
                }

                if ($attrName === \DafDb\Attributes\ForeignKey::class) {
                    $attrInstance = $attr->newInstance();
                    $fkTableName = $attrInstance->Table;
                    $fkTableCol = $attrInstance->Column;

                    $onDelete = null;
                    if (isset($attrInstance->OnDelete)) {
                        $onDelete = $attrInstance->OnDelete;
                    }

                    $table->AddForeignKey(new ForeignKeyInfo(
                        column: $name,
                        refTable: $fkTableName,
                        refColumn: $fkTableCol,
                        onDelete: $onDelete
                    ));

                    // FK is still a scalar column, so don't continue 2
                }

                if ($attrName === \DafDb\Attributes\DbInclude::class) {
                    $attrInstance = $attr->newInstance();
                    $incTable = $attrInstance->Table;
                    $condition = $attrInstance->Condition;


                    $named = self::namedTypeOrNull($type);
                    if (!$incTable || !$condition || !$named) continue 2;

                    $propType = $named->getName();

                    $isMany = in_array($propType, [
                        'array',
                        \DafGlobals\Collections\ICollection::class,
                        \DafGlobals\Collections\Collection::class,
                    ], true);

                    $targetModel = $propType;
                    if ($isMany) {
                        $targetModel = $attrInstance->Model;
                        if ($targetModel === null) {
                            throw new \Exception("DbInclude on {$modelClass}::{$name} requires model argument");
                        }
                    }

                    $table->AddInclude(new IncludeInfo(
                        property: $name,
                        table: $incTable,
                        condition: $condition,
                        modelClass: $targetModel,
                        isMany: $isMany
                    ));

                    // recurse so nested includes are cached too
                    self::getModelMetadata($incTable, $targetModel);

                    // Include props are NOT scalar columns
                    continue 2;
                }
            }

            // scalar column
            if ($isBuiltIn || $isDate) {
                $isAutoIncrement = $isAutoIncrement && in_array($name, $table->GetPrimaryKeys()); 

                $table->AddColumn(new ColumnInfo(
                    name: $name,
                    phpTypeName: $typeName,
                    isBuiltIn: $isBuiltIn,
                    isDate: $isDate,
                    isAutoIncrement: $isAutoIncrement
                ));
            }
        }

        self::$modelMetadataCache[$cacheKey] = $table;
        unset(self::$modelMetadataInProgress[$cacheKey]);

        return $table;
    }
    private static function namedTypeOrNull(?\ReflectionType $type): ?\ReflectionNamedType {
        if ($type instanceof \ReflectionNamedType) return $type;
        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $t) {
                if ($t instanceof \ReflectionNamedType && $t->getName() !== 'null') return $t;
            }
        }
        return null;
    }

    function __destruct(){ $this->connection = null; }
}