<?php
namespace DafDb;

class SqliteContext extends Context {
    public function __construct(string $database) {
       $dsn = "sqlite:$database";
       parent::__construct($dsn, null, null, 1);
    }
 }