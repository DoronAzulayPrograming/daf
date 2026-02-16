<?php
namespace DafDb\Query;

use DafDb\Context\Context;
use DafDb\Helpers\TableInfo;

final class QueryState {
    public string $Select = '';
    public string $Where = '';
    public string $Join = '';
    public string $Limit = '';
    public bool $Count = false;
    public ?int $Skip = null;
    public ?int $Take = null;
    public array $Params = [];
    public bool $RowToArray = false;

    /** Include alias counter (t1,t2,...) */
    public int $IncludeAliasCounter = 0;

    /** When includes exist we always select with aliases */
    public bool $ExpectAliasedResult = false;

    /** @var IncludeNode[] */
    private array $includes = [];

    /** EF-like cursor: last include node in the active chain */
    private ?IncludeNode $includeCursor = null;

    // ---------------- OrderBy ----------------
    private array $order = [];

    public function ClearOrderBy() :void {$this->order = [];}
    public function HasOrderBy(): bool {return count($this->order) > 0; }
    public function OrderBy(string $column, string $dir): void{ $this->order[$column] = $dir;}
    public function OrderByToString(): string
    {
        if (empty($this->order)) return '';

        $parts = [];
        foreach ($this->order as $column => $dir) {
            $parts[] = "$column $dir";
        }
        return " ORDER BY " . implode(', ', $parts);
    }

    // ---------------- Includes (EF style) ----------------

    /** @return IncludeNode[] */
    public function Includes(): array { return $this->includes; }

    public function HasIncludes(): bool { return !empty($this->includes); }

    public function ClearIncludes(): void
    {
        $this->includes = [];
        $this->includeCursor = null;
        $this->IncludeAliasCounter = 0;
    }

    private function nextAlias(): string
    {
        return 't' . (++$this->IncludeAliasCounter);
    }

    private function buildSelectFromColumns(array $columns, string $alias): string
    {
        if (empty($columns)) return "{$alias}.*";
        return implode(', ', array_map(fn($c) => "{$alias}.{$c} AS `{$alias}.{$c}`", $columns));
    }

    /**
     * Adds a top-level Include (resets ThenInclude chain cursor).
     */
    public function AddInclude(Context $context, TableInfo $rootInfo, string $property): IncludeNode
    {
        $includeInfo = $rootInfo->GetInclude($property);
        if ($includeInfo === null) {
            throw new \Exception("DbInclude metadata not found for {$rootInfo->ModelClass}::{$property}");
        }

        $tableInfo = Context::getModelMetadata($includeInfo->Table, $includeInfo->ModelClass);
        $alias = $this->nextAlias();
        $select = $this->buildSelectFromColumns($tableInfo->GetColumnNames(), $alias);

        $node = new IncludeNode(
            property: $property,
            table: $includeInfo->Table,
            alias: $alias,
            condition: $includeInfo->Condition,
            modelClass: $includeInfo->ModelClass,
            isMany: $includeInfo->IsMany,
            tableInfo: $tableInfo,
            select: $select
        );

        $this->includes[] = $node;
        $this->includeCursor = $node;

        // Once includes exist, we must alias select columns
        $this->ExpectAliasedResult = true;

        return $node;
    }

    /**
     * Adds a ThenInclude to the current include chain cursor.
     * Cursor moves to the new child (so you can chain ThenInclude->ThenInclude).
     */
    public function AddThenInclude(Context $context, TableInfo $rootInfo, string $property): IncludeNode
    {
        if ($this->includeCursor === null) {
            throw new \Exception("ThenInclude called before Include");
        }

        $parent = $this->includeCursor;

        // parent model metadata to find DbInclude definition
        $parentInfo = $parent->TableInfo;
        $thenIncludeInfo = $parentInfo->GetInclude($property);

        if ($thenIncludeInfo === null) {
            throw new \Exception("DbInclude metadata not found for {$parent->ModelClass}::{$property}");
        }

        $tableInfo = Context::getModelMetadata($thenIncludeInfo->Table, $thenIncludeInfo->ModelClass);
        $alias = $this->nextAlias();
        $select = $this->buildSelectFromColumns($tableInfo->GetColumnNames(), $alias);

        $node = new IncludeNode(
            property: $property,
            table: $thenIncludeInfo->Table,
            alias: $alias,
            condition: $thenIncludeInfo->Condition,
            modelClass: $thenIncludeInfo->ModelClass,
            isMany: $thenIncludeInfo->IsMany,
            tableInfo: $tableInfo,
            select: $select
        );

        $parent->AddChild($node);
        $this->includeCursor = $node;

        $this->ExpectAliasedResult = true;
        return $node;
    }

    /**
     * EF-like behavior: if user calls Include() again,
     * chain cursor should jump to that new Include root node.
     */
    public function ResetIncludeCursor(): void
    {
        $this->includeCursor = null;
    }
}
