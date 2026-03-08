<?php
namespace DafCore\Forms;

interface IFormFeedback
{
    public function AddGlobal(string $msg): void;
    public function AddField(string $field, string $msg): void;

    /** @param array<int, string|array{field?:string|null,msg?:string}> $errors */
    public function AddMany(array $errors): void;

    /** @return array<string, array<int,string>> */
    public function ErrorsByField(): array;

    public function HasErrors(): bool;
    public function Clear(): void;
}
