<?php
namespace DafDb;

final class ChangeEntry {
    public mixed $Filter;

    public function __construct(
        public Queryable $Repository,
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

    public function Enqueue(Queryable $repo, string $op, object|array|null $entity = null, ?callable $filter = null): void
    {
        $this->entries[] = new ChangeEntry($repo, $op, $entity, $filter);
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
