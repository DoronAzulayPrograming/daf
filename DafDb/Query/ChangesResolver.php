<?php 
namespace DafDb\Query;

use DafDb\Context\Context;


final class ChangesResolver
{
    
    /** @param ChangeEntry[] $entries */
    public function Commit(Context $context, array $entries): int
    {
        $affected = 0;

        $context->BigTransaction(function(Context $ctx) use (&$affected, $entries){
            $persister = new Persister($ctx->GetDbType());
            foreach ($entries as $entry) {
                switch ($entry->Operation) {
                    case 'add':
                        $cmd = $persister->BuildInsert($entry->TableInfo, $entry->Entity);
                        break;
                    case 'update':
                        $cmd = $persister->BuildUpdate($entry->TableInfo, $entry->Entity, $entry->Filter);
                        break;
                    case 'remove':
                        $cmd = $persister->BuildRemove($entry->TableInfo, $entry->Entity ?? $entry->Filter);
                        break;
                    case 'clear':
                        $cmd = $persister->BuildClear($entry->TableInfo);
                        break;
                    default:
                        throw new \InvalidArgumentException("Unknown operation: {$entry->Operation}");
                }

                $stmt = $ctx->Execute($cmd);

                if ($entry->Operation === 'add') {
                    // only single PK tables can use lastInsertId safely
                    $pks = $entry->TableInfo->GetPrimaryKeys();
                    if (count($pks) === 1) {
                        $pk = $pks[0];

                        $isAutoInc = $entry->TableInfo->IsAutoIncrement($pk);

                        if ($isAutoInc && property_exists($entry->Entity, $pk)) {
                            $current = $entry->Entity->$pk ?? null;
                            $isEmpty = $current === null || $current === 0 || $current === '0' || $current === '';

                            // only assign if entity didn't already have an id
                            if ($isEmpty) {
                                $id = $ctx->GetConnection()->lastInsertId();
                                if ($id !== false && $id !== '' && $id !== '0') {
                                    $entry->Entity->$pk = ctype_digit((string)$id) ? (int)$id : $id;
                                }
                            }
                        }
                    }
                }
                $affected += $stmt->rowCount();
            }
        });

        return $affected;
    }
}