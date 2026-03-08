<?php
namespace DafDb\Context;

class SqliteContext extends Context
{
   const Type = "sqlite";

   public function __construct(string $database)
   {
      $dsn = self::Type.":$database";
      parent::__construct($dsn, null, null);
   }
   public function GetDbType(): string { return self::Type; }
}