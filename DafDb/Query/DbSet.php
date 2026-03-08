<?php
namespace DafDb\Query;

use DafDb\Context\Context;
use DafDb\Attributes\Table;
use DafDb\Helpers\TableInfo;

/**
 * Class DbSet
 * 
 * This class is a base class for all DbSets.
 * It extends the Queryable class and provides implementations for all methods.
 * For working with database queries.
 *   
 * @package DafDb
 */
class DbSet extends Queryable 
{
    public function __construct(Context $context)
    {
        $called_class = get_called_class();
        $arr = explode("\\", $called_class);
        $baseModelClassName = str_replace("DbSet",'',end($arr));

        $ref = new \ReflectionClass($called_class);

        $table_attr = $ref->getAttributes(Table::class);
        if (empty($table_attr))
            throw new \Exception("Table attribute is missing");
        
        $attr_args = $table_attr[0]->getArguments();

        $tableName = $attr_args['Name'] ?? $attr_args['name'] ?? $attr_args[0] ?? $baseModelClassName;
        $tableModelClass = $attr_args['Model'] ?? $attr_args['model'] ?? $attr_args[1] ?? null;

        if (empty($tableName))
            throw new \Exception("Table name is missing");

        if (empty($tableModelClass))
            throw new \Exception("Model class is missing");

        $table = Context::getModelMetadata($tableName, $tableModelClass);

        parent::__construct($context, $table);
    }


    public function GetTableInfo(): TableInfo { return $this->info; }
    public function Execute(SqlCommand $cmd): \PDOStatement{ return $this->context->Execute($cmd); }
    public function GetLastInsertedId(): bool|string { return $this->context->GetConnection()->lastInsertId(); }


    public function Add(object|array $data): object
    {
        $entity = $this->ensureEntity($data);

        $this->context->Tracker()->Enqueue($this->GetTableInfo(), 'add', $entity);
        return $entity;
    }
    public function Update(object|array $data, callable $func = null): void {
        $this->context->Tracker()->Enqueue($this->GetTableInfo(), 'update', $data, $func);
    }
    public function Remove(callable|array|object $objOrfunc)
    {
        if (is_callable($objOrfunc)) {
            $this->context->Tracker()->Enqueue($this->GetTableInfo(), 'remove', null, $objOrfunc);
        }
        else if (is_array($objOrfunc) || is_object($objOrfunc)){
            $this->context->Tracker()->Enqueue($this->GetTableInfo(), 'remove', $objOrfunc);
        }
    }
    public function Clear() : void {
        $this->context->Tracker()->Enqueue($this->GetTableInfo(), 'clear');
    }



    private function ensureEntity(object|array $data): object
    {   
        if ($data instanceof $this->info->ModelClass) return $data;

        $class = $this->info->ModelClass;
        return new $class($data);
    }
}