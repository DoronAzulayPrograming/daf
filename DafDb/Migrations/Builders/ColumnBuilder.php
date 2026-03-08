<?php
namespace DafDb\Migrations\Builders;

use DafDb\Migrations\Services\SqlExpression;
use DafDb\Migrations\Services\ISqlExpression;


final class ColumnBuilder
{
    public bool $Nullable = true;
    public bool $AutoIncrement = false;
    public bool $Unique = false;

    public bool $HasDefault = false;
    public mixed $Default = null;
    public ?int $Length = null;


    private array $_migrationUseing = [];


    public function __construct(public string $Name, public string $PhpType) {}

    public function Nullable(bool $v = true): self { $this->Nullable = $v; return $this; }
    public function AutoIncrement(bool $v = true): self { $this->AutoIncrement = $v; return $this; }
    public function Unique(bool $v = true): self { $this->Unique = $v; return $this; }
    public function MaxLength(int $value): self { $this->Length = $value; return $this; }

    public function Default(mixed $value): self
    {
        // allow explicit "set default null" ? (optional)
        // if you don't want ->Default(null) to mean "set default", keep your old behavior.
        if ($value === null) {
            $this->HasDefault = false;
            $this->Default = null;
            return $this;
        }

        $this->HasDefault = true;

        if ($value instanceof ISqlExpression) {
            $this->Default = $value->Signature(); // e.g. "kind:current_timestamp" or "raw:NOW()"
            return $this;
        }

        // scalar only
        if (is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            $this->Default = $value;
            return $this;
        }

        throw new \InvalidArgumentException("Default value must be scalar or SqlExpression");
    }

    public function DefaultSql(string $expr): self
    {
        $this->HasDefault = true;
        $this->Default = SqlExpression::Raw($expr)->Signature();
        return $this;
    }

    public function EmitColumn(string $var): string
    {
        $type = strtolower($this->PhpType);

        $method = match ($type) {
            'int', 'integer' => 'Int',
            'float', 'double' => 'Float',
            'bool', 'boolean' => 'Bool',
            'date' => 'Date',
            'datetime' => 'DateTime',
            default => 'String',
        };

        $chain = "{$var}->{$method}(" . $this->phpStringLiteral($this->Name) . ")";

        if (isset($this->Length) && $this->Length !== null) $chain .= '->MaxLength(' . $this->Length . ')';
        
        if (($this->Nullable ?? true) === false) $chain .= '->Nullable(false)';
        if (!empty($this->AutoIncrement)) $chain .= '->AutoIncrement()';
        if (!empty($this->Unique)) $chain .= '->Unique()';

        if (!empty($this->HasDefault)) {
            $defaultExpr = $this->emitDefaultValue($this->Default ?? null);

            if ($defaultExpr !== null) {
                $chain .= '->Default(' . $defaultExpr . ')'; 
            } else {
                $chain .= '->Default(' . $this->phpStringLiteral($this->Default ?? null) . ')';
            }
        }


        return $chain;
    }
    
    public function GetMigrationUsing(): array { return $this->_migrationUseing; }
    private function addMigrationUsing($path): void { $this->_migrationUseing[$path] = 1; }
    private function phpStringLiteral(string $s): string { return var_export($s, true); }
    
    private function emitDefaultValue(?string $default): ?string
    {   
        if ($default === null) return null;

        if (str_starts_with($default, 'kind:') && $default === 'kind:current_timestamp') {
            $this->addMigrationUsing("DafDb\Migrations\Services\SqlExpression");
            return 'SqlExpression::CURRENT_TIMESTAMP';
        }

        return null; // fall back to phpValueLiteral
    }
}
