<?php
namespace DafDb\Query;

use DafDb\Helpers\TableInfo;

/**
 * A node in the Include tree.
 * Root is implicit (t0).
 */
final class IncludeNode
{
    public string $Property;      // property name on parent model
    public string $Table;         // table name
    public string $Alias;         // SQL alias (t1,t2,...)
    public string $Condition;     // join condition (DSL)
    public string $ModelClass;    // target model class
    public bool $IsMany;          // collection navigation?
    public TableInfo $TableInfo;  // target table metadata
    public string $Select;        // select list for this table alias

    /** @var IncludeNode[] */
    public array $Children = [];

    public ?IncludeNode $Parent = null;

    public function __construct(
        string $property,
        string $table,
        string $alias,
        string $condition,
        string $modelClass,
        bool $isMany,
        TableInfo $tableInfo,
        string $select
    ) {
        $this->Property   = $property;
        $this->Table      = $table;
        $this->Alias      = $alias;
        $this->Condition  = $condition;
        $this->ModelClass = $modelClass;
        $this->IsMany     = $isMany;
        $this->TableInfo  = $tableInfo;
        $this->Select     = $select;
    }

    public function AddChild(IncludeNode $child): void
    {
        $child->Parent = $this;
        $this->Children[] = $child;
    }

    public function PathKey(): string
    {
        // Stable key for identity maps (root/prop/prop/prop)
        $parts = [];
        $cur = $this;
        while ($cur !== null) {
            $parts[] = $cur->Property;
            $cur = $cur->Parent;
        }
        $parts = array_reverse($parts);
        return implode('.', $parts);
    }
}
