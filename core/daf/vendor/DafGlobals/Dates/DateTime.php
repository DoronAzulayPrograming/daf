<?php
namespace DafGlobals\Dates;

final class DateTime extends BaseDate
{
    private function __construct(\DateTimeImmutable $value)
    {
        $this->value = $value;
    }

    protected function defaultFormat(): string
    {
        return 'Y-m-d H:i:s';
    }

    public static function FromString(string $value, ?string $format = null): self
    {
        $dt = \DateTimeImmutable::createFromFormat($format ?? 'Y-m-d H:i:s', $value);
        if (!$dt) throw new \InvalidArgumentException("Invalid DateTime string '{$value}' for format '{$format}'.");
        return new self($dt);
    }

    public static function Now(): self
    {
        return new self(new \DateTimeImmutable('now'));
    }

    public function AddHours(int $hours): self { return new self($this->value->modify(($hours >= 0 ? '+' : '') . "{$hours} hours")); }
    public function AddMinutes(int $minutes): self { return new self($this->value->modify(($minutes >= 0 ? '+' : '') . "{$minutes} minutes")); }
}
