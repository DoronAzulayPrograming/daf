<?php
namespace DafDb\Migrations\Providers;

trait SqlHelpersTrait
{
    protected function q(string $name): string
    {
        return "`" . str_replace("`", "``", $name) . "`";
    }

    protected function joinCols(array $cols): string
    {
        return implode(',', array_map(fn($c) => $this->q($c), $cols));
    }

}
