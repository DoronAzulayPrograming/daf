<?php
namespace DafDb\Migrations\Storage\DbSet;

use DafDb\Query\DbSet;
use DafDb\Migrations\Storage\Models\MigrationData;
use DafDb\Query\SqlCommand;

#[\DafDb\Attributes\Table('daf_migrations', MigrationData::class)]
class MigrationsDbSet extends DbSet {

    public function GetLastBatchNumber(): int
    {
        $stmt = $this->context->Execute(new SqlCommand("SELECT MAX(batch) AS max_batch FROM daf_migrations"));
        $row = $stmt->fetch();
        
        $max = $row && $row['max_batch'] ? (int)$row['max_batch'] : 0;
        return $max;
    }
}