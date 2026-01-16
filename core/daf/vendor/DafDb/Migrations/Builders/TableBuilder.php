<?php
namespace DafDb\Migrations\Builders;

use DafDb\Migrations\Builders\Definitions\IndexDef;
use DafDb\Migrations\Services\MigrationsSchemaHelper;

final class TableBuilder
{
    use MigrationsSchemaHelper;
    
    public string $Name;

    /** @var array<string,ColumnBuilder> */
    public array $Columns = [];

    public ConstraintsBuilder $Constraints;

    /** @var IndexDef[] */
    public array $Indexes = [];

    private array $_migrationUseing = [];

    public function __construct(string $name)
    {
        $this->Name = $name;
        $this->Constraints = new ConstraintsBuilder($name);
    }

    public function Int(string $name): ColumnBuilder    { return $this->Columns[$name] = new ColumnBuilder($name, 'int'); }
    
    public function String(string $name, ?int $length = null): ColumnBuilder {
        $col = $this->Columns[$name] = new ColumnBuilder($name, 'string');
        if ($length !== null) $col->MaxLength($length);
        return $col;
    }

    public function Float(string $name): ColumnBuilder  { return $this->Columns[$name] = new ColumnBuilder($name, 'float'); }
    public function Bool(string $name): ColumnBuilder   { return $this->Columns[$name] = new ColumnBuilder($name, 'bool'); }

    public function Index(string $name, array $columns, bool $unique = false): self
    {
        $this->Indexes[] = new IndexDef($name, $this->Name, $columns, $unique);
        return $this;
    }

    public function Date(string $name): ColumnBuilder
    {
        return $this->Columns[$name] = new ColumnBuilder($name, 'date');
    }

    public function DateTime(string $name): ColumnBuilder
    {
        return $this->Columns[$name] = new ColumnBuilder($name, 'datetime');
    }

    public function EmitCreateTable(): string
    {
        $this->addMigrationUseing("DafDb\Migrations\Builders\TableBuilder");

        $lines = [];
        $lines[] = "\$builder->CreateTable(" . $this->phpStringLiteral($this->Name) . ", function(TableBuilder \$t) {";

        foreach ($this->Columns as $col) {
            $lines[] = "    " . $this->emitColumnStatement('$t',$col);
            $this->addMigrationUseing($col->GetMigrationUsing());
        }

        if ($this->Constraints->primaryKey) {
            $lines[] = "    \$t->Constraints->PrimaryKey(" . $this->phpArrayShort($this->Constraints->primaryKey->Columns) . ");";
        }

        foreach ($this->Constraints->ForeignKeys as $fk) {
            $args = [
                $this->phpArrayShort($fk->Columns),
                $this->phpStringLiteral($fk->RefTable),
                $this->phpArrayShort($fk->RefColumns),
            ];
            if (!empty($fk->OnDelete)) {
                $onDelete = $this->emitOnDeleteValue($fk->OnDelete);
                if($onDelete !== null) $args[] = $onDelete;
                else $args[] = $this->phpStringLiteral($fk->OnDelete);
            }
            $lines[] = "    \$t->Constraints->ForeignKey(" . implode(', ', $args) . ");";
        }

        foreach ($this->Indexes as $idx) {
            $unique = $idx->Unique ? 'true' : 'false';
            $lines[] = "    \$t->Index(" . $this->phpStringLiteral($idx->Name) . ", " . $this->phpArrayShort($idx->Columns) . ", {$unique});";
        }

        $lines[] = "});";

        return $this->joinIndentedLines($lines);
    }


    
    public function GetMigrationUseing(): array { return $this->_migrationUseing; }
    private function addMigrationUseing(array|string $useing): void{

        if(is_array($useing)){
            $this->_migrationUseing = array_merge($this->_migrationUseing, $useing);
            return;
        }

        $this->_migrationUseing[$useing] = 1;
    }






    private function emitColumnStatement(string $var, ColumnBuilder $col): string
    {
        return $col->EmitColumn($var) . ';';
    }
    private function emitOnDeleteValue(?string $value): ?string
    {   
        if ($value === null) return null;

        $v = match($value){
            'CASCADE' => 'OnDeleteAction::CASCADE',
            'SET NULL' => 'OnDeleteAction::SET_NULL',
            'RESTRICT' => 'OnDeleteAction::RESTRICT',
            'NO ACTION' => 'OnDeleteAction::NO_ACTION',
            'SET DEFAULT' => 'OnDeleteAction::SET_DEFAULT',
            default => null

        };

        if($v === null) return null;

        $this->addMigrationUseing("DafDb\OnDeleteAction");
        return $v;
    }


}
