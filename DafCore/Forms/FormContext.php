<?php
namespace DafCore\Forms;

final class FormContext
{
    /** @var array<int, array{model: object|null, errors: array<string,array<int,string>>, rules: array<string,array>, clientSideValidation: bool}> */
    private array $stack = [];

    public function Push(?object $model, array $errorsByField, array $rules = [], bool $clientSideValidation = false): void
    {
        $this->stack[] = [
            'model' => $model,
            'errors' => $errorsByField,
            'rules' => $rules,
            'clientSideValidation' => $clientSideValidation,
        ];
    }

    public function Pop(): void { array_pop($this->stack); }

    public function Current(): ?array { return $this->stack[count($this->stack) - 1] ?? null; }

    public function HasError(string $field): bool
    {
        $cur = $this->Current();
        if (!$cur) return false;
        return !empty($cur['errors'][$field] ?? []);
    }

    public function FieldErrors(string $field): array
    {
        $cur = $this->Current();
        if (!$cur) return [];
        return $cur['errors'][$field] ?? [];
    }

    public function GlobalErrors(): array
    {
        $cur = $this->Current();
        if (!$cur) return [];
        return $cur['errors']['__global__'] ?? [];
    }
}
