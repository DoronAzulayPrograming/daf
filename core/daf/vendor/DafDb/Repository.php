<?php
namespace DafDb;

/**
 * Interface IRepository
 * 
 * This interface extends IQueryable that defines the methods that a repository class must.
 * 
 * @package DafDb
 */
interface IRepository extends IQueryable
{ }

/**
 * Class Repository
 * 
 * This class is a base class for all repositories.
 * It implements the IRepository interface and provides implementations for all methods.
 * It also provides some helper methods for working with database queries.
 *   
 * @package DafDb
 */
class Repository extends Queryable implements IRepository
{
   public function __construct(Context $context)
   {
      parent::__construct($context);

      $ref = new \ReflectionClass(get_called_class());
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

      $this->tableName = $name;
      $this->modelClass = $model;

      if (empty($this->tableName))
         throw new \Exception("Table name is missing");

      if (empty($this->modelClass))
         throw new \Exception("Model class is missing");

      $this->LoadTableInfoFromClass($this->modelClass);
      $this->CreateTable();
   }
}