<?php
namespace DafDb\Query;

use DafDb\Context\Context;
use DafDb\Helpers\TableInfo;

abstract class BaseQueryable
{
    protected TableInfo $info;
    protected Context $context;
    protected QueryState $queryState;

    public function __construct(Context $context, TableInfo $info)
    {
        $this->info = $info;
        $this->context = $context;
        $this->queryState = new QueryState();
    }

    protected abstract function FetchAll(\PDOStatement $stmt, callable $callback = null): array;

    protected function getByMode(array $data, string $class)
    {
        if ($this->queryState->RowToArray) return $data;
        return new $class($data);
    }

    // ---------------- Row helpers ----------------

    protected function getUniqIdByTable(array $row, string $table)
    {
        $id = "";
        $primaryKeys = $this->info->GetPrimaryKeys();
        foreach ($primaryKeys as $primaryKey) {
            $key = $table . '.' . $primaryKey;
            if (isset($row[$key])) $id .= $row[$key] . ',';
            else if (isset($row[$primaryKey])) $id .= $row[$primaryKey] . ',';
        }
        return rtrim($id, ',');
    }

    protected function getUniqIdByAliasForTable(array $row, string $alias, TableInfo $info): string
    {
        $id = '';
        foreach ($info->GetPrimaryKeys() as $pk) {
            $key = "{$alias}.{$pk}";
            if (isset($row[$key]) && $row[$key] !== null) $id .= $row[$key] . ',';
            else return ''; // if any PK is null => treat as no entity
        }
        return rtrim($id, ',');
    }

    private function hydrateValueFor(TableInfo $info, string $field, mixed $value): mixed
    {
        if ($value === null) return null;

        $type = $info->HasColumn($field) ? ($info->GetColumns()[$field]->PhpTypeName ?? null) : null;
        if ($type && is_a($type, \DafGlobals\Dates\IDate::class, true)) {
            return $type::FromString((string)$value);
        }
        return $value;
    }

    protected function getPropsFromRowByTable(array $row, TableInfo $info): array
    {
        $props = [];
        foreach ($row as $key => $value) {
            if (str_starts_with($key, $info->Name . '.')) {
                $field = substr($key, strlen($info->Name) + 1);
                $props[$field] = $this->hydrateValueFor($info, $field, $value);
            } elseif (!str_contains($key, '.')) {
                $props[$key] = $this->hydrateValueFor($info, $key, $value);
            }
        }
        return $props;
    }

    protected function getPropsFromRowByAlias(array $row, string $alias, TableInfo $info): array
    {
        $props = [];
        $prefix = $alias . '.';
        $len = strlen($prefix);

        foreach ($row as $key => $value) {
            if (strncmp($key, $prefix, $len) === 0) {
                $field = substr($key, $len);
                $props[$field] = $this->hydrateValueFor($info, $field, $value);
            }
        }
        return $props;
    }

    // ---------------- SQL builder helpers ----------------

    protected function buildSelectFromColumns(array $columns, string $alias): string
    {
        if (empty($columns)) return "{$alias}.*";
        return implode(', ', array_map(fn($c) => "{$alias}.{$c} AS `{$alias}.{$c}`", $columns));
    }

    private function addPrimaryKeyOrderBy(string $alias): void
    {
        $pks = $this->info->GetPrimaryKeys();
        if (empty($pks)) return;

        foreach ($pks as $pk) {
            $this->queryState->OrderBy("`{$alias}`.`{$pk}`", "ASC");
        }
    }

    private function compileCondition(string $condition, array $tableAliasMap): string
    {
        $cond = trim($condition);
        if ($cond === '') return $cond;

        // Replace Table.Column with `alias`.`Column`
        $pattern = '/`?([A-Za-z_][A-Za-z0-9_]*)`?\s*\.\s*`?([A-Za-z_][A-Za-z0-9_]*)`?/';

        $cond = preg_replace_callback($pattern, function ($m) use ($tableAliasMap) {
            $table = $m[1];
            $col   = $m[2];

            if (!isset($tableAliasMap[$table])) {
                throw new \Exception(
                    "Include condition references unknown table '{$table}'. Known: " .
                    implode(', ', array_keys($tableAliasMap))
                );
            }

            $alias = $tableAliasMap[$table];
            return "`{$alias}`.`{$col}`";
        }, $cond);

        return $cond;
    }

    /**
     * Builds SELECT + JOIN parts from QueryState include tree.
     * Returns ['select' => string, 'join' => string]
     */
    private function getIncludeSqlParts(): array
    {
        $rootAlias = 't0';

        // root columns
        $select = $this->buildSelectFromColumns($this->info->GetColumnNames(), $rootAlias);

        // stable ordering for Skip/Take entity-based logic
        //$this->addPrimaryKeyOrderBy($rootAlias);

        $join = '';

        $rootTable = $this->info->Name;

        // recursive walk
        $walk = function (IncludeNode $node, array $aliasMap) use (&$walk, &$select, &$join, $rootTable) {
            $select .= ',' . $node->Select;

            // add this table to alias map
            $aliasMap[$node->Table] = $node->Alias;

            // compile join ON using all known tables in chain (root + ancestors + self)
            $on = $this->compileCondition($node->Condition, $aliasMap);
            $join .= " LEFT JOIN {$node->Table} {$node->Alias} ON {$on}";

            foreach ($node->Children as $child) {
                $walk($child, $aliasMap);
            }
        };

        $baseMap = [$rootTable => 't0'];

        foreach ($this->queryState->Includes() as $inc) {
            $walk($inc, $baseMap);
        }

        return ['select' => $select, 'join' => $join];
    }

    // ---------------- Where / Order parsing ----------------

    // protected function _where2(callable $func)
    // {
    //     $conditionsSql = \DafDb\Sql::ParseWhere($func);
    //     $query = "";
    //     $params = [];

    //     $useAlias = $this->queryState->ExpectAliasedResult || $this->queryState->HasIncludes();

    //     foreach ($conditionsSql as $condition) {
    //         $rawField = substr($condition['field'], 1, -1);

    //         $paramKey = $rawField . count($params);
    //         $params[":$paramKey"] = $condition['value'];

    //         $qualifiedColumn = $useAlias ? "`t0`.`{$rawField}`" : "`{$rawField}`";

    //         $query .= " " . $condition['whereOp'] . " {$qualifiedColumn} {$condition['operator']} :$paramKey";
    //     }

    //     $query = trim($query);
    //     return ['query' => $query, 'params' => $params];
    // }

    protected function _where(callable $func, array $externals = [])
    {
        $useAlias = $this->queryState->ExpectAliasedResult || $this->queryState->HasIncludes();
        $alias = $useAlias ? 't0' : null;

        $res = WhereParser::Parse($func, $alias, $externals);

        return ['query' => $res['query'], 'params' => $res['params']];
    }


    protected function _orderBy(callable $func, string $order): self
    {
        $column = $this->_parseField($func);
        $expr = "`t0`.`{$column}`";
        $this->queryState->OrderBy($expr, $order);
        return $this;
    }

    protected function _parseField(callable $func): string
    {
        $reflectedFunc = new \ReflectionFunction($func);

        $startLine = $reflectedFunc->getStartLine();
        $endLine = $reflectedFunc->getEndLine();
        $length = $endLine - $startLine;
        $length = $length > 0 ? $length + 1 : 1;

        $source = array_slice(file($reflectedFunc->getFileName()), $startLine - 1, $length);

        $markup = implode("", $source);
        $code = substr($markup, strpos($markup, '=>') + 2);
        $paramName1 = $reflectedFunc->getParameters()[0]->getName();

        $condition = trim($code);

        if (!preg_match('/\$' . $paramName1 . '\->(\w+)\s*/', $condition, $matches)) {
            throw new \Exception("OrderBy/Field parse failed. Use fn(\$x)=>\$x->Field");
        }
        return $matches[1];
    }

    // ---------------- Execute ----------------

    protected function _prepareExecute(): SqlCommand
    {
        $select_prefix = '';
        $select_suffix = '';

        if (!empty($this->queryState->Where)) {
            $this->queryState->Where = ' WHERE ' . trim($this->queryState->Where);
        }

        if ($this->queryState->Count) {
            $this->queryState->ClearIncludes();
            $this->queryState->ClearOrderBy();

            $this->queryState->Select = '*';
            $select_prefix = 'COUNT(';
            $select_suffix = ')';
        } else {
            $this->queryState->Select = $this->buildSelectFromColumns($this->info->GetColumnNames(), 't0');
        }

        $from = $this->info->Name . ' t0';

        // If includes exist -> build include SQL
        if ($this->queryState->HasIncludes()) {
            $this->queryState->ExpectAliasedResult = true;
            $res = $this->getIncludeSqlParts();
            $this->queryState->Select = $res['select'];
            $this->queryState->Join   = $res['join'];
        } else {
            // still safe to alias root columns if you want consistency
            $this->queryState->ExpectAliasedResult = true;
            $this->queryState->Join = '';
        }

        $query = "SELECT $select_prefix" . $this->queryState->Select . $select_suffix .
            " FROM " . $from .
            $this->queryState->Join .
            $this->queryState->Where .
            $this->queryState->OrderByToString() .
            $this->queryState->Limit . ';';

        $params = $this->queryState->Params;
        return new SqlCommand($query, $params);
    }

    function __debugInfo(): array
    {
        $cmd = $this->_prepareExecute();
        $stmt = $this->context->Execute($cmd);
        
        return $this->FetchAll($stmt);
    }

    function __toString(): string
    {
        $json = json_encode($this->__debugInfo());
        return is_string($json) ? $json : "";
    }

    function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->__debugInfo());
    }

    function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }
}
