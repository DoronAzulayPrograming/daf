<?php
namespace DafCore;

class Session
{
    public function __construct(IViewManager $vm) {
        $vm->OnAfterRender(function(){
            // if(isset($_SESSION['flush_list'])){
            //     unset($_SESSION['flush_list']);
            // }
            $this->ClearFlush();
        });
    }

    function Start(): void
    {
        if(!$this->IsAvailable())
            session_start();
    }
    function GetId(): string
    {
        return session_id();
    }
    function IsAvailable(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }
    function GetKeys(): array
    {
        return array_keys($_SESSION);
    }
    function Clear(): void
    {
        session_unset();
    }
    function Stop(): void
    {
        session_destroy();
    }
    function Destroy(): void
    {
        session_unset();
        session_destroy();
    }

    function Remove(string $key): void
    {
        if(isset($_SESSION[$key]))
            unset($_SESSION[$key]);
    }

    function Set(string $key, $value): void
    {
        if (headers_sent()) {
            throw new \Exception('Cannot set session variable after the response has been sent');
        }
        if(is_object($value) || is_array($value)){
            $value = json_encode($value);
        }

        $_SESSION[$key] = $value;
    }

    function TryGetValue(string $key, &$value): bool
    {
        if (!isset($_SESSION[$key])) {
            return false;
        }

        $value = $_SESSION[$key];
        return true;
    }
    function TryGetValueFromJson(string $key, &$value, $toArray = false): bool
    {
        if (!isset($_SESSION[$key])) {
            return false;
        }

        $value = json_decode($_SESSION[$key], $toArray);
        return true;
    }

    function AddFlushMsg(string $value): void {
        if (headers_sent()) {
            throw new \Exception('Cannot set session variable after the response has been sent');
        }

        if(!isset($_SESSION['flush_list']))
            $_SESSION['flush_list'] = [];

        if(!isset($_SESSION['flush_list']["flush_msgs"]))
            $_SESSION['flush_list']["flush_msgs"] = [];
        
        $_SESSION['flush_list']["flush_msgs"][] = $value;
    }
    function SetFlushMsgs(array $value): void {
        if (headers_sent()) {
            throw new \Exception('Cannot set session variable after the response has been sent');
        }

        if(!isset($_SESSION['flush_list']))
            $_SESSION['flush_list'] = [];

        if(!isset($_SESSION['flush_list']["flush_msgs"]))
            $_SESSION['flush_list']["flush_msgs"] = [];
        
        $_SESSION['flush_list']["flush_msgs"] = $value;
    }
    function TryGetFlushMsgs(&$msgs) : bool {
        if($this->TryGetFlushValue("flush_msgs", $msgs)){
            return true;
        }

        return false;
    }

    function AddFlushError(string $value): void {
        if (headers_sent()) {
            throw new \Exception('Cannot set session variable after the response has been sent');
        }

        if(!isset($_SESSION['flush_list']))
            $_SESSION['flush_list'] = [];

        if(!isset($_SESSION['flush_list']["flush_errors"]))
            $_SESSION['flush_list']["flush_errors"] = [];
        
        $_SESSION['flush_list']["flush_errors"][] = $value;
    }
    function SetFlushErrors(array $value): void {
        if (headers_sent()) {
            throw new \Exception('Cannot set session variable after the response has been sent');
        }

        if(!isset($_SESSION['flush_list']))
            $_SESSION['flush_list'] = [];

        if(!isset($_SESSION['flush_list']["flush_errors"]))
            $_SESSION['flush_list']["flush_errors"] = [];
        
        $_SESSION['flush_list']["flush_errors"] = $value;
    }
    function TryGetFlushErrors(&$msgs) : bool {
        if($this->TryGetFlushValue("flush_errors", $msgs)){
            return true;
        }

        return false;
    }

    function SetFlush(string $key, $value): void {
        if (headers_sent()) {
            throw new \Exception('Cannot set session variable after the response has been sent');
        }
        if(is_object($value) || is_array($value)){
            $value = json_encode($value);
        }

        if(!isset($_SESSION['flush_list']))
            $_SESSION['flush_list'] = [];
        
        $_SESSION['flush_list'][$key] = $value;
    }
    function TryGetFlushValue(string $key, &$value): bool {
        if (!isset($_SESSION['flush_list']) || !isset($_SESSION['flush_list'][$key])) {
            return false;
        }

        $value = $_SESSION['flush_list'][$key];
        return true;
    }
    function TryGetFlushValueFromJson(string $key, &$value, $toArray = false): bool
    {
        if (!isset($_SESSION['flush_list']) || !isset($_SESSION['flush_list'][$key])) {
            return false;
        }

        $flush_value = $_SESSION['flush_list'][$key];
        $value = json_decode($flush_value, $toArray);
        return true;
    }
    function RemoveFlushValue(string $key): void {
        if(isset($_SESSION['flush_list']) && isset($_SESSION['flush_list'][$key]))
            unset($_SESSION['flush_list'][$key]);
    }
    function ClearFlush(): void {
        if(isset($_SESSION['flush_list']))
            unset($_SESSION['flush_list']);
    }
}