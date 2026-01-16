<?php
namespace DafDb\Migrations\Storage\Models;

class MigrationData {

    #[\DafDb\Attributes\PrimaryKey]
    #[\DafDb\Attributes\AutoIncrement]
    public int $Id;
    public string $Name;
    public int $Batch;
    public string $AppliedAt;
    public string $Snapshot;

    public function __construct(array $data){
        if(isset($data['Id']))
            $this->Id = $data['Id'];
        
        $this->Name = $data['Name'];
        $this->Batch = $data['Batch'];
        $this->AppliedAt = $data['AppliedAt'];
        $this->Snapshot = $data['Snapshot'];
    }
}