<?php
namespace DafDb\Query;

use DafGlobals\Collections\ICollection;
use DafGlobals\Collections\ReadOnlyCollection;

abstract class Queryable extends BaseQueryable implements \IteratorAggregate, \JsonSerializable
{
    protected function Fetch(\PDOStatement $stmt) : mixed
    {
        $result = [];
        $this->FetchAll($stmt, callback: function($item) use (&$result){
            $result[] = $item;
            return FetchCallbackValue::STOP;
        });

        if (empty($result)) return null;
        return $result[0];
    }

    protected function FetchAll(\PDOStatement $stmt, callable $callback = null): array
    {
        $list = [];

        $data = null;            // current root entity
        $currentId = null;
        $skipEntityId = null;

        $useAlias = $this->queryState->ExpectAliasedResult;

        // Identity maps per ROOT entity
        // $idMap[instanceKey][entityId] = &entityRef
        $idMap = [];

        // Helpers (array/object)
        $get = function (&$obj, string $key) {
            if (is_array($obj)) return $obj[$key] ?? null;
            return $obj->$key ?? null;
        };
        $set = function (&$obj, string $key, $value): void {
            if (is_array($obj)) $obj[$key] = $value;
            else $obj->$key = $value;
        };
        $ensureMany = function (&$obj, string $key) use ($get, $set): void {
            $v = $get($obj, $key);
            if ($v === null) $set($obj, $key, []);
        };
        $appendMany = function (&$obj, string $key, $value) use ($get, $set, $ensureMany): void {
            $ensureMany($obj, $key);
            $arr = $get($obj, $key);
            $arr[] = $value;
            $set($obj, $key, $arr);
        };

        $flushCurrent = function () use (&$data, &$list, $callback) {
            if ($data === null) return null;

            if ($callback !== null) {
                $cb = $callback($data);
                if ($cb === FetchCallbackValue::STOP) {
                    $list[] = $data;
                    return FetchCallbackValue::STOP;
                }
                if ($cb !== null) $data = $cb;
            }

            $list[] = $data;
            return null;
        };

        // Recursively attach include nodes to a parent entity
        $attachNode = function (
            IncludeNode $node,
            array $row,
            &$parentEntity,
            string $parentInstanceKey
        ) use (&$attachNode, &$idMap, $useAlias, $get, $set, $appendMany) {

            // read included entity id
            $entityId = $useAlias
                ? $this->getUniqIdByAliasForTable($row, $node->Alias, $node->TableInfo)
                : $this->getUniqIdByTable($row, $node->Table);

            if ($entityId === '') {
                return; // no joined row
            }

            // instanceKey isolates identity map per parent instance
            // so children don't dedupe across different parents
            $instanceKey = $parentInstanceKey . '>' . $node->PathKey();

            if (!isset($idMap[$instanceKey])) $idMap[$instanceKey] = [];

            $entity = null;

            if ($node->IsMany) {
                if (isset($idMap[$instanceKey][$entityId])) {
                    $entity = $idMap[$instanceKey][$entityId];
                } else {
                    $props = $useAlias
                        ? $this->getPropsFromRowByAlias($row, $node->Alias, $node->TableInfo)
                        : $this->getPropsFromRowByTable($row, $node->TableInfo);

                    $entity = $this->getByMode($props, $node->ModelClass);

                    // attach once
                    $appendMany($parentEntity, $node->Property, $entity);
                    $idMap[$instanceKey][$entityId] = $entity;
                }
            } else {
                $existing = $get($parentEntity, $node->Property);
                if ($existing !== null) {
                    $entity = $existing;
                } else {
                    $props = $useAlias
                        ? $this->getPropsFromRowByAlias($row, $node->Alias, $node->TableInfo)
                        : $this->getPropsFromRowByTable($row, $node->TableInfo);

                    $entity = $this->getByMode($props, $node->ModelClass);
                    $set($parentEntity, $node->Property, $entity);
                }
            }

            if ($entity === null) return;

            // recurse children, with parent instance key including this entity id
            $childParentKey = $instanceKey . '@' . $entityId;

            foreach ($node->Children as $child) {
                $attachNode($child, $row, $entity, $childParentKey);
            }
        };

        try {
            while ($row = $stmt->fetch()) {

                // Root id
                $tempId = $useAlias
                    ? $this->getUniqIdByAliasForTable($row, 't0', $this->info)
                    : $this->getUniqIdByTable($row, $this->info->Name);

                // Skip logic (entity-level)
                if ($skipEntityId !== null) {
                    if ($tempId === $skipEntityId) continue;
                    $skipEntityId = null;
                }

                // New root entity
                if ($currentId !== $tempId) {

                    // flush previous root
                    if ($currentId !== null) {
                        $stop = $flushCurrent();
                        if ($stop === FetchCallbackValue::STOP) return $list;
                    }

                    $currentId = $tempId;

                    // Apply Skip(n) on root entities
                    if ($this->queryState->Skip !== null && $this->queryState->Skip > 0) {
                        $this->queryState->Skip--;
                        $skipEntityId = $tempId;

                        // reset state for skipped entity
                        $data = null;
                        $idMap = [];
                        continue;
                    }

                    // Create root entity
                    $rootProps = $useAlias
                        ? $this->getPropsFromRowByAlias($row, 't0', $this->info)
                        : $this->getPropsFromRowByTable($row, $this->info);

                    $data = $this->getByMode($rootProps, $this->info->ModelClass);

                    // reset identity maps per root entity
                    $idMap = [];
                }

                if ($data === null) continue;

                // Apply include tree
                $rootInstanceKey = 'root@' . $currentId;

                foreach ($this->queryState->Includes() as $inc) {
                    $attachNode($inc, $row, $data, $rootInstanceKey);
                }
            }

            // flush last
            if ($data !== null) {
                $stop = $flushCurrent();
                if ($stop === FetchCallbackValue::STOP) return $list;
            }

        } finally {
            $this->queryState = new QueryState();
        }

        return $list;
    }

    // ---------------- Public Query API ----------------

    public function FirstOrDefault(callable $func = null): mixed
    {
        if($func !== null) $this->Where($func);

        $cmd = $this->_prepareExecute();
        $stmt = $this->context->Execute($cmd);

        return $this->Fetch($stmt);
    }

    public function SingleOrDefault(callable $func = null): mixed
    {
        if($func !== null) $this->Where($func);

        $cmd = $this->_prepareExecute();
        $stmt = $this->context->Execute($cmd);

        $count = 0;
        $res = $this->FetchAll($stmt, callback: function($item) use (&$count){
            if($count > 0) return FetchCallbackValue::STOP;
            $count++;
            return $item;
        });

        if (empty($res)) return null;

        if (count($res) > 1) $this->context->Error("More than one result found in SingleOrDefault");
        return $res[0];
    }

    public function Where(callable $func, array $externals = []): self
    {
        $res = $this->_where($func, $externals);

        $where_prefix = ' ';
        if (strlen($this->queryState->Where) > 0) $where_prefix = ' AND ';

        $this->queryState->Where .= $where_prefix . $res['query'];
        $this->queryState->Params = array_merge($this->queryState->Params, $res['params']);
        return $this;
    }


    public function OrderBy(callable $func): self
    {
        $this->_orderBy($func,'ASC');
        return $this;
    }

    public function OrderByDescending(callable $func): self
    {
        $this->_orderBy($func,'DESC');
        return $this;
    }

    public function Skip(int $length): self
    {
        $this->queryState->Skip = $length;
        return $this;
    }

    public function Take(int $length): self
    {
        $limit = $length + ($this->queryState->Skip ?? 0);
        $this->queryState->Limit = ' LIMIT ' . $limit;
        return $this;
    }

    public function Any(callable $func = null): bool
    {
        return $this->Count($func) > 0;
    }

    public function Count(callable $func = null): int
    {
        if($func !== null) $this->Where($func);

        $this->queryState->Count = true;

        $cmd = $this->_prepareExecute();
        $stmt = $this->context->Execute($cmd);

        $count = $stmt->fetchColumn();
        $this->queryState = new QueryState();
        return (int)$count;
    }

    public function Map(callable $callback): ICollection {
        $cmd = $this->_prepareExecute();
        $stmt = $this->context->Execute($cmd);

        $list = $this->FetchAll($stmt, callback: $callback);
        return new ReadOnlyCollection($list);
    }

    public function ForEach(callable $callback) : void
    {
        $cmd = $this->_prepareExecute();
        $stmt = $this->context->Execute($cmd);

        $this->FetchAll($stmt, callback: $callback);
    }

    // ---------------- EF-style Include API ----------------

    public function Include(callable $func): self
    {
        // EF: Include starts a new chain
        $this->queryState->ResetIncludeCursor();

        $field = $this->_parseField($func);
        $this->queryState->AddInclude($this->context, $this->info, $field);

        return $this;
    }

    public function ThenInclude(callable $func): self
    {
        $field = $this->_parseField($func);
        $this->queryState->AddThenInclude($this->context, $this->info, $field);

        return $this;
    }

    public function RowToArray(bool $value = true) : self {
        $this->queryState->RowToArray = $value;
        return $this;
    }

    public function ToArray(): array
    {
        $cmd = $this->_prepareExecute();
        $stmt = $this->context->Execute($cmd);

        return $this->FetchAll($stmt);
    }

    public function ToCollection(): ICollection
    {
        return new ReadOnlyCollection($this->ToArray());
    }
}

final class FetchCallbackValue
{
    public const STOP = "__DAFDB_STOP__";
}
