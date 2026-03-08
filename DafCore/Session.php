<?php
namespace DafCore;

final class Session
{
    public function Start(): void
    {
        if ($this->IsAvailable()) return;

        if (headers_sent()) {
            throw new \RuntimeException('Cannot start session: headers already sent.');
        }

        session_start();
    }

    public function IsAvailable(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function GetId(): string
    {
        $this->ensureStarted();
        return session_id();
    }

    public function GetKeys(): array
    {
        $this->ensureStarted();
        return array_keys($_SESSION);
    }

    public function Clear(): void
    {
        $this->ensureStarted();
        session_unset();
    }

    public function Stop(): void
    {
        $this->ensureStarted();
        session_destroy();
        $_SESSION = [];
    }

    public function Destroy(): void
    {
        $this->Clear();
        $this->Stop();
    }

    public function SetItem(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    public function RemoveItem(string $key): void
    {
        $this->ensureStarted();
        unset($_SESSION[$key]);
    }

    public function HasItem(string $key): bool
    {
        $this->ensureStarted();
        return array_key_exists($key, $_SESSION);
    }

    public function TryGetItem(string $key, mixed &$value): bool
    {
        $this->ensureStarted();
        if (!array_key_exists($key, $_SESSION)) return false;
        $value = $_SESSION[$key];
        return true;
    }

    public function GetItemOr(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    private function ensureStarted(): void
    {
        if (!$this->IsAvailable()) {
            throw new \RuntimeException("Session not started. Call Session::Start() first.");
        }
    }
}
