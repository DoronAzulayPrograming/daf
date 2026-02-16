<?php
namespace DafDb\Query;

use DafDb\Helpers\TableInfo;

final class ChangeEntry {
    public mixed $Filter;

    public function __construct(
        public TableInfo $TableInfo,
        public string $Operation,         // 'add','update','remove'
        public object|array|null $Entity, // entity for add/update/delete-by-entity
        ?callable $Filter = null   // optional lambda for update/remove
    ) {
        $this->Filter = $Filter;
    }
}

final class ChangeTracker
{
    /** @var ChangeEntry[] */
    private array $entries = [];

    public function Enqueue(TableInfo $tableInfo, string $op, object|array|null $entity = null, ?callable $filter = null): void
    {
        $this->entries[] = new ChangeEntry($tableInfo, $op, $entity, $filter);
    }

    /** @return ChangeEntry[] */
    public function Drain(): array
    {
        $entries = $this->entries;
        $this->entries = [];
        return $entries;
    }

    public function IsEmpty(): bool
    {
        return empty($this->entries);
    }
}
