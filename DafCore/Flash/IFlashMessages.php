<?php
namespace DafCore\Flash;

interface IFlashMessages
{
    public function Add(string $type, string $text): void;

    /** @param array<int, array{type?:string, text?:string}> $msgs */
    public function AddMany(array $msgs): void;

    /** @return array<int, array{type:string, text:string}> */
    public function All(): array;

    /** @return array<int, array{type:string, text:string}> */
    public function ByType(string $type): array;

    public function Clear(): void;
    public function ClearType(string $type): void;

    // optional convenience:
    public function Ok(string $text): void;
    public function Warning(string $text): void;
    public function Error(string $text): void;
}
