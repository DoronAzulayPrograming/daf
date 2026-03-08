<?php 
namespace DafDb\Query;

use DafDb\Sql;
use DafGlobals\Dates\IDate;
use DafDb\Helpers\TableInfo;
use DafDb\Context\SqliteContext;

final class Persister
{
    public function __construct(private string $dbType){}

    public function BuildInsert(TableInfo $info, object|array $entity): SqlCommand{
        $values = $this->_normalizeData($info, $entity);

        // If single PK is AutoIncrement and value is not provided -> omit it from INSERT
        $pks = $info->GetPrimaryKeys();
        if (count($pks) === 1 && $info->IsAutoIncrement($pks[0])) {
            $pk = $pks[0];
            $val = $values[$pk] ?? null;

            $isMissing = !array_key_exists($pk, $values);
            $isEmpty = $val === null || $val === 0 || $val === '0' || $val === '';

            if ($isMissing || $isEmpty) {
                unset($values[$pk]); // let DB generate it
            }
        }

        $res = Sql::Insert($info->Name, $values);
        return new SqlCommand($res['query'], $res['params']);
    }
    public function BuildUpdate(TableInfo $info, object|array $entity, ?callable $filter = null): SqlCommand{
        $data = $this->_normalizeData($info, $entity);

        $whereClauses = [];
        $params = [];
        $t_data = [];
        foreach ($data as $key => $value) {
            if (in_array($key,  $info->GetPrimaryKeys())) {
                $whereClauses[] = "`$key` = :pk_$key";
                $params[":pk_$key"] = $value;
            }else{
                $t_data[$key] = $value;
            }
        }
        $data = $t_data;

        if (empty($data)) {
            throw new \Exception("Update failed: no non-PK columns provided for {$info->Name}");
        }

        $resUpdate = Sql::Update($info->Name, $data);

        if ($filter !== null) {
            $resWhere = Sql::Where($filter);
            $query  = $resUpdate['query'] . ' WHERE ' . $resWhere['query'] . ';';
            $params = array_merge($resUpdate['params'], $resWhere['params']);
        } else {
            $query  = $resUpdate['query'] . " WHERE " . implode(' AND ', $whereClauses) . ';';
            $params = array_merge($params, $resUpdate['params']);
        }

        return new SqlCommand($query, $params);
    }
    public function BuildRemove(TableInfo $info, object|array|callable $entityOrFilter): SqlCommand{
       
        if (is_callable($entityOrFilter)) {
            return $this->_removeQuery($info, $entityOrFilter);
        }
        else if (is_array($entityOrFilter) || is_object($entityOrFilter)){
            return $this->_removeObjectOrArray($info, $entityOrFilter);
        }

        throw new \InvalidArgumentException("BuildRemove expects object|array|callable");
    }
    public function BuildClear(TableInfo $info): SqlCommand{
        if($this->dbType === SqliteContext::Type)
            $query = "DELETE FROM {$info->Name};";
        else
            $query = "TRUNCATE TABLE {$info->Name};";
        
        return new SqlCommand($query);
    }


    // normalize data for Add,Update functions
    private function _normalizeData(TableInfo $info, object|array $data): array
    {
        if (is_array($data)) return $data;

        $getValue = function($value){
            if ($value instanceof IDate) return (string)$value;
            else return $value;
        };

        $out = [];
        foreach ($info->GetColumnNames() as $field) {
            $getter = "_Get" . ucfirst($field);
            if (method_exists($data, $getter)) {
                $out[$field] = $getValue($data->$getter());
            } elseif (property_exists($data, $field)) {
                try { $out[$field] = $getValue($data->$field); } catch (\Throwable) {}
            }
        }
        return $out;
    }


    private function _removeQuery(TableInfo $info, callable $func): SqlCommand
    {
        $res = Sql::Where($func);
        $res['query'] = 'DELETE FROM ' . $info->Name . ' WHERE ' . $res['query'] . ';';

        return new SqlCommand($res['query'], $res['params']);
    }
    private function _removeObjectOrArray(TableInfo $info, object|array $row): SqlCommand
    {
        $primaryKeys = $info->GetPrimaryKeys();
        if (empty($primaryKeys)) {
            throw new \Exception("Remove failed: No primary keys defined for {$info->Name}");
        }

        $where = [];
        $params = [];

        foreach ($primaryKeys as $field) {

            // Get value from object or array
            if (is_array($row)) {
                if (!array_key_exists($field, $row)) {
                    throw new \Exception("Remove failed: Missing primary key '{$field}' in array for {$info->Name}");
                }
                $value = $row[$field];
            } else {
                // Optional: support your getter convention _Get<Field>()
                $getter = "_Get" . ucfirst($field);
                if (method_exists($row, $getter)) {
                    $value = $row->$getter();
                } elseif (property_exists($row, $field)) {
                    $value = $row->$field;
                } else {
                    throw new \Exception("Remove failed: Missing primary key '{$field}' in object for {$info->Name}");
                }
            }

            $where[] = "`{$field}` = :pk_{$field}";
            $params[":pk_{$field}"] = $value;
        }

        $query = "DELETE FROM {$info->Name} WHERE " . implode(' AND ', $where) . ";";
        return new SqlCommand($query, $params);
    }
}