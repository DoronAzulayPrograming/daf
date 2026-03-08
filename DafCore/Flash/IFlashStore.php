<?php
namespace DafCore\Flash;

interface IFlashStore
{
    public function Put(string $key, mixed $value): void;   // store for next request
    public function Has(string $key): bool;
    public function Peek(string $key, mixed &$value): bool; // read without removing
    public function Pull(string $key, mixed &$value): bool; // read + remove
    public function Clear(string $key): void;
    public function ClearAll(): void;
}
