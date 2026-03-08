<?php
namespace DafDb\Context;

class MysqlContext extends Context
{
   const Type = "mysql";

   public function __construct(string $database, string $username, string $password, string $host = "127.0.0.1", int $port = 3306, $charset = "utf8mb4")
   {
      $this->database = $database;
      $dsn = "mysql:host=$host;dbname=$database;port=$port;charset=$charset";
      parent::__construct($dsn, $username, $password);
   }
   
   public function GetDbType(): string { return self::Type; }
}