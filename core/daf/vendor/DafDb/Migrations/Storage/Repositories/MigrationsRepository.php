<?php
namespace DafDb\Migrations\Storage\Repositories;

use DafDb\Migrations\Mapper\AttributeToBuilderMapper;
use DafDb\Repository;
use DafDb\Migrations\Storage\Models\MigrationData;

#[\DafDb\Attributes\Table('daf_migrations', MigrationData::class)]
class MigrationsRepository extends Repository {

    public function GetLastBatchNumber(): int
    {
        $this->Execute("SELECT MAX(batch) AS max_batch FROM daf_migrations");
        $row = $this->RowToArray()->Fetch();
        
        $max = $row && $row['max_batch'] ? (int)$row['max_batch'] : 0;
        return $max;
    }
}