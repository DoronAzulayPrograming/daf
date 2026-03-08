<?php
namespace DafCore\Flash;

final class FlashMessages implements IFlashMessages
{
    private const KEY = 'flash_msgs';

    public function __construct(private IFlashStore $flash) {}

    public function Add(string $type, string $text): void
    {
        $list = $this->All();
        $list[] = ['type' => $type, 'text' => $text];
        $this->flash->Put(self::KEY, $list);
    }

    public function AddMany(array $msgs): void
    {
        foreach ($msgs as $m) {
            $type = $m['type'] ?? null;
            $text = $m['text'] ?? null;
            if (!$type || $text === null || $text === '') continue;
            $this->Add((string)$type, (string)$text);
        }
    }

    public function All(): array
    {
        $out = [];
        $this->flash->Peek(self::KEY, $out);
        return is_array($out) ? $out : [];
    }

    public function ByType(string $type): array
    {
        $all = $this->All();
        if (!$all) return [];

        return array_values(array_filter($all, fn($m) => ($m['type'] ?? null) === $type));
    }

    public function Clear(): void
    {
        $this->flash->Clear(self::KEY);
    }

    public function ClearType(string $type): void
    {
        $all = $this->All();
        $all = array_values(array_filter($all, fn($m) => ($m['type'] ?? null) !== $type));
        $this->flash->Put(self::KEY, $all);
    }

    public function Ok(string $text): void { $this->Add('ok', $text); }
    public function Warning(string $text): void { $this->Add('warning', $text); }
    public function Error(string $text): void { $this->Add('error', $text); }
}
