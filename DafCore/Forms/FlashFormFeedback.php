<?php
namespace DafCore\Forms;

use DafCore\Flash\IFlashStore;

final class FlashFormFeedback implements IFormFeedback
{
    private const KEY = 'form_errors';

    /** @var array<string, array<int,string>> */
    private array $errors = [];

    public function __construct(private IFlashStore $flash)
    {
        // load once per request (non-destructive read is OK, but usually you want Pull)
        if ($this->flash->Pull(self::KEY, $loaded) && is_array($loaded)) {
            $this->errors = $loaded;
        }
    }

    public function AddGlobal(string $msg): void
    {
        $this->errors['__global__'][] = $msg;
        $this->persist();
    }

    public function AddField(string $field, string $msg): void
    {
        $this->errors[$field] ??= [];
        $this->errors[$field][] = $msg;
        $this->persist();
    }

    public function AddMany(array $errors): void
    {
        foreach ($errors as $e) {
            if (is_string($e)) {
                $this->AddGlobal($e);
                continue;
            }
            if (is_array($e)) {
                $msg = (string)($e['msg'] ?? '');
                if ($msg === '') continue;
                $field = $e['field'] ?? null;
                if (!$field) $this->AddGlobal($msg);
                else $this->AddField((string)$field, $msg);
            }
        }
    }

    public function ErrorsByField(): array
    {
        return $this->errors;
    }

    public function HasErrors(): bool
    {
        foreach ($this->errors as $list) {
            if (!empty($list)) return true;
        }
        return false;
    }

    public function Clear(): void
    {
        $this->errors = [];
        $this->flash->Clear(self::KEY);
    }

    private function persist(): void
    {
        $this->flash->Put(self::KEY, $this->errors);
    }
}
