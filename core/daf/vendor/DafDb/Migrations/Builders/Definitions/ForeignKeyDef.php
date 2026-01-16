<?php
namespace DafDb\Migrations\Builders\Definitions;

final class ForeignKeyDef {
    public function __construct(
        public string $Name,
        //public string $Column,
        public array $Columns,
        public string $RefTable,
        //public string $RefColumn,
        public array $RefColumns,
        public ?string $OnDelete = null
    ) {
        $this->OnDelete = $this->normalizeOnDelete($this->OnDelete);

        if (count($this->Columns) === 0 || count($this->RefColumns) === 0) {
            throw new \InvalidArgumentException("FK columns cannot be empty");
        }
        if (count($this->Columns) !== count($this->RefColumns)) {
            throw new \InvalidArgumentException("FK columns and referenced columns must have same length");
        }
    }


    // Backward compatibility helpers (optional)
    public function getColumn(): string { return $this->Columns[0]; }
    public function getRefColumn(): string { return $this->RefColumns[0]; }

    private function normalizeOnDelete(?string $v): ?string
    {
        if ($v === null) return null;
        $v = strtoupper(trim($v));

        $allowed = ['CASCADE','RESTRICT','SET NULL','NO ACTION'];
        if (!in_array($v, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid OnDelete action: {$v}");
        }
        return $v;
    }
}