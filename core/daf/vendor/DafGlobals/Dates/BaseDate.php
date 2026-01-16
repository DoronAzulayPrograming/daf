<?php 
namespace DafGlobals\Dates;

abstract class BaseDate implements IDate
{
    protected \DateTimeImmutable $value;

    protected function __construct(\DateTimeImmutable $value)
    {
        $this->value = $value;
    }

    abstract protected function defaultFormat(): string;

    public static function FromDateTime(\DateTimeInterface $value): static
    {
        return new static(\DateTimeImmutable::createFromInterface($value));
    }

    public function Format(?string $format = null): string
    {
        return $this->value->format($format ?? $this->defaultFormat());
    }

    public function ToDateTime(): \DateTimeImmutable
    {
        return $this->value;
    }

    public function AddDays(int $days): static   { return new static($this->value->modify(($days >= 0 ? '+' : '') . "{$days} days")); }
    public function AddMonths(int $months): static { return new static($this->value->modify(($months >= 0 ? '+' : '') . "{$months} months")); }
    public function AddYears(int $years): static { return new static($this->value->modify(($years >= 0 ? '+' : '') . "{$years} years")); }

    public function Compare(IDate $other): int
    {
        return $this->value <=> $other->ToDateTime();
    }

    public function Equals(IDate $other): bool
    {
        return $this->Compare($other) === 0;
    }

    public function __toString(): string
    {
        return $this->Format();
    }
}
