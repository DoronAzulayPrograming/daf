<?php
namespace DafGlobals\Dates;

interface IDate
{
    public static function FromString(string $value, ?string $format = null): self;
    public static function FromDateTime(\DateTimeInterface $value): self;

    public function Format(?string $format = null): string;
    public function ToDateTime(): \DateTimeImmutable;

    public function AddDays(int $days): self;
    public function AddMonths(int $months): self;
    public function AddYears(int $years): self;

    /** <0 if this < other, 0 when equal, >0 if greater */
    public function Compare(IDate $other): int;
    public function Equals(IDate $other): bool;

    public function __toString(): string;
}
