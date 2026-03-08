<?php
namespace DafCore\Flash;

use DafCore\Session;
use DafCore\IViewManager;

final class SessionFlashStore implements IFlashStore
{
    private const ROOT = '__daf_flash__';

    public function __construct(
        private Session $session,
        IViewManager $vm
    ) {
        // Ensure flash exists early (optional)
        $this->session->Start();

        // Clear once after the response is rendered
        $vm->OnAfterRender(function () {
            $this->ClearAll();
        });
    }

    public function Put(string $key, mixed $value): void
    {
        $flash = &$this->flashRef();
        $flash[$key] = $value;
    }

    public function Has(string $key): bool
    {
        $flash = $this->flash(false);
        return is_array($flash) && array_key_exists($key, $flash);
    }

    public function Peek(string $key, mixed &$value): bool
    {
        $flash = $this->flash(false);
        if (!is_array($flash) || !array_key_exists($key, $flash)) return false;
        $value = $flash[$key];
        return true;
    }

    public function Pull(string $key, mixed &$value): bool
    {
        $flash = &$this->flashRef();
        if (!array_key_exists($key, $flash)) return false;
        $value = $flash[$key];
        unset($flash[$key]);
        return true;
    }

    public function Clear(string $key): void
    {
        $flash = &$this->flashRef();
        unset($flash[$key]);
    }

    public function ClearAll(): void
    {
        $this->session->Start();
        unset($_SESSION[self::ROOT]);
    }

    // -------------------
    // Internals
    // -------------------
    private function flash(bool $createIfMissing = true): ?array
    {
        $this->session->Start();

        if (!isset($_SESSION[self::ROOT])) {
            if (!$createIfMissing) return null;
            $_SESSION[self::ROOT] = [];
        }

        if (!is_array($_SESSION[self::ROOT])) {
            $_SESSION[self::ROOT] = [];
        }

        return $_SESSION[self::ROOT];
    }

    /** @return array<string,mixed> */
    private function &flashRef(): array
    {
        $this->session->Start();

        if (!isset($_SESSION[self::ROOT]) || !is_array($_SESSION[self::ROOT])) {
            $_SESSION[self::ROOT] = [];
        }

        return $_SESSION[self::ROOT];
    }
}
