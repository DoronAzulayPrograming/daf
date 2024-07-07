<?php

namespace DafDb;
class MySqlContext extends Context {
    public function __construct(string $database, string $username, string $password, string $host = "127.0.0.1", int $port = 3306, $charset="utf8mb4", int $maxConnections = 10) {
      $this->database = $database;
      $dsn = "mysql:host=$host;dbname=$database;port=$port;charset=$charset";
      parent::__construct($dsn, $username, $password, $maxConnections);
      
      try {
        // check if the database exists
        $con = $this->GetConnection();
        $this->ReleaseConnection($con);
      } catch (\Throwable $th) {
        // if the database does not exist, create it
        $this->dns = "mysql:host=$host;port=$port;charset=$charset";
        $con = $this->GetConnection();
        $con->exec("CREATE DATABASE IF NOT EXISTS $database");
        $this->ReleaseConnection($con);

        $this->dns = "mysql:host=$host;dbname=$database;port=$port;charset=$charset";
        $this->connection = $this->GetConnection();
      }
    }
 }