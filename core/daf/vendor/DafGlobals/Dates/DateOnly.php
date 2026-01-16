<?php
namespace DafGlobals\Dates;

final class DateOnly extends BaseDate
{
    private function __construct(\DateTimeImmutable $value)
    {
        $this->value = $value->setTime(0, 0, 0);
    }

    protected function defaultFormat(): string
    {
        return 'Y-m-d';
    }

    public static function FromString(string $value, ?string $format = null): self
    {
        $v = $value;
        if(str_contains($v, " ")) $v = explode(' ',$v)[0];

        $dt = \DateTimeImmutable::createFromFormat($format ?? 'Y-m-d', $v);
        if (!$dt) throw new \InvalidArgumentException("Invalid DateOnly string '{$value}' for format '{$format}'.");
        return new self($dt);
    }

    public static function Today(): self
    {
        return new self(new \DateTimeImmutable('today'));
    }
}
