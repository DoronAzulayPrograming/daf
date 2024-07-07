<?php
namespace DafDb;

use DafGlobals\ICollection;

interface IQueryable
{

   /**
    * @param object|array $data The data to add to the collection
    * @return void
    */
   function Add(object|array $data);

   /**
    * @param callable $objOrfunc A function to test each element for a condition or an object to remove
    * @return void
    */
   function Remove(callable|object $objOrfunc);

    /**
     * Removes all items from the table in the database 
     */
    function Clear(): void;

   /**
    * @param int $length The number of items to skip
    * @return IQueryable original queryable with new query configuration
    */
   function Skip(int $length): self;

   /**
    * @param int $length The number of items to take
    * @return IQueryable original queryable with new query configuration
    */
   function Take(int $length): self;

   /**
    * @param callable $func A function to test each element for a condition
    * @return bool True if the collection is not empty
    */
   function Any(callable $func = null): bool;

   /**
    * @param callable $func A function to test each element for a condition
    * @return int The number of elements that satisfy the condition
    */
   function Count(callable $func = null): int;

   /**
    * @param callable $func A function to apply to each element
     * @return ICollection The queryable as an ICollection [ReadOnlyCollection]
    */
   function Map(callable $callback): ICollection;

   /**
    * @param callable $func A function to apply to each element
    */
   function ForEach(callable $callback) : void;

   /**
    * @param callable $func A function to apply to each element
    * @return mixed A elements that satisfy the condition
    */
   function FirstOrDefault(callable $func = null): mixed;

   /**
    * @param callable $func A function to apply to each element
    * @return mixed A elements that satisfy the condition
      * @throws \Exception If found more then one element
    */
   function SingleOrDefault(callable $func = null): mixed;

   /**
    * @param callable $func A function to apply to each element
    * @return IQueryable original queryable with new query configuration
    */
   function Where(callable $func): self;

   /**
    * @param callable $func A function that satisfy the condition
    * @return IQueryable original queryable with new query configuration
    */
   function OrderBy(callable $func): self;

   /**
    * @param callable $func A function that satisfy the condition in descending order
    * @return IQueryable original queryable with new query configuration
    */
   function OrderByDescending(callable $func): self;


   /**
    * @param callable $func A function that satisfy the object to include
    * @return IQueryable original queryable with new query configuration
    */
   function Include(callable $func) : self;

   /**
    * @param callable $func A function that satisfy the object to include with the previous include
    * @return IQueryable original queryable with new query configuration
    */
   function ThenInclude(callable $func) : self;

   /**
    * @param callable $callback A function to run in a transaction and commit all changes at the end
    * @return void
    * @throws \Exception If an error occurs during the transaction or if the transaction is not completed and rolled back
    */
   function BigTransaction(callable $callback) : void;


    /**
     * @return array The collection as an array
     */
   function ToArray(): array;

   /**
    * set the query to return each row as an array
    * @return IQueryable original queryable with new query configuration
    */
   function RowToArray() : self;


    /**
     * @return ICollection The queryable as an ICollection [ReadOnlyCollection]
     */
   function ToCollection(): ICollection;
}