<?php
namespace DafDb;

class SqlQueriesBuilder
{
    private Queryable $repo;
    private Context $context;
    private array $result = [
        'query' => '',
        'params' => [],
    ];
    private string $alias = 't0';

    public function __construct(Queryable $repo)
    {
        $this->repo = $repo;
        $this->context = $repo->GetContext();
    }

    public function SelectAll(): self
    {
        $meta = $this->repo->GetMetadata();
        $columns = $this->buildSelect($meta['columns'], $this->alias);
        $this->result['query'] = "SELECT {$columns} FROM {$this->repo->GetTableName()} {$this->alias}";
        return $this;
    }

    public function Select(array $columns): self
    {
        $projection = $this->buildSelect($columns, $this->alias);
        $this->result['query'] = "SELECT {$projection} FROM {$this->repo->GetTableName()} {$this->alias}";
        return $this;
    }

    public function Where(callable $func): self
    {
        $res = Sql::Where($func);
        $this->result['query'] .= ' ' . str_replace($this->repo->GetTableName() . '.', "{$this->alias}.", $res['query']);
        $this->result['params'] = array_merge($this->result['params'], $res['params']);
        return $this;
    }

    public function OrderBy(callable $func): self
    {
        return $this->applyOrder($func, 'ASC');
    }

    public function OrderByDescending(callable $func): self
    {
        return $this->applyOrder($func, 'DESC');
    }

    public function Limit(int $limit): self
    {
        $this->result['query'] .= " LIMIT {$limit}";
        return $this;
    }

    public function Build(): array
    {
        $built = [$this->result['query'], $this->result['params']];
        $this->result = [
            'query' => '',
            'params' => [],
        ];
        return $built;
    }

    private function buildSelect(array $columns, string $alias): string
    {
        if (empty($columns)) return "{$alias}.*";
        return implode(', ', array_map(fn($c) => "{$alias}.{$c} AS `{$alias}.{$c}`", $columns));
    }

    private function applyOrder(callable $func, string $dir): self
    {
        $column = $this->_parseField($func);
        $clause = " ORDER BY {$this->alias}.`{$column}` {$dir}";
        $this->result['query'] .= $clause;
        return $this;
    }
    private function _parseField(callable $func): string
    {
        $ref = new \ReflectionFunction($func);
        $start = $ref->getStartLine();
        $end = $ref->getEndLine();
        $source = array_slice(file($ref->getFileName()), $start - 1, $end - $start + 1);
        $code = implode('', $source);
        $code = substr($code, strpos($code, '=>') + 2);
        $param = $ref->getParameters()[0]->getName();
        preg_match('/\$' . $param . '\->(\w+)/', $code, $m);
        return $m[1];
    }
}
