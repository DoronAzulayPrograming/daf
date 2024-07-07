<?php
namespace DafCore;
use Closure;
interface IDIContainer
{
    public function Exists(string $key): bool;
    public function GetOne(string $key);
    public function GetAll(array $keys): array;
    public function AddScop(string $key, Closure $callback);
    public function AddSingleton(string $key, Closure $callback);
    public function BindInterface(string $interface, string $concrete);
}

class DIContainer implements IDIContainer {
    
    private array $services = [];
    private array $interfaceBindings = []; // Store interface bindings
    // Add a method to bind an interface to a concrete implementation
    public function BindInterface(string $interface, string $concrete) {
        $this->interfaceBindings[$interface] = $concrete;
    }

    public function Exists(string $key): bool{
        if (isset($this->interfaceBindings[$key])) {
            return true;
        }
        if (!array_key_exists($key, $this->services) && !(interface_exists($key) && isset($this->interfaceBindings[$key]))) {
            return false;
        }
        return true;
    }

    public function GetOne(string $key) {
        if (isset($this->interfaceBindings[$key])) {
            // If the requested key is an interface and it's bound to a concrete class,
            // return an instance of the concrete class
            return $this->GetOne($this->interfaceBindings[$key]);
        }

        if (!array_key_exists($key, $this->services)) {
            throw new \Exception("Service $key not found");
        }

        $service = $this->services[$key];
        if($service->is_singleton)
        {
            if(!$service->is_active){
                $service->is_active = true;
                $service->dependency = ($service->dependency)($this);
            }
            
            if($service->is_active)
                return $service->dependency;
        }

        return ($service->dependency)($this);
    }

    public function GetAll(array $keys) : array {
        $result = array();
        foreach ($keys as $key) {
            if(array_key_exists($key, $this->services)){
                $service = $this->services[$key];
                if($service->is_singleton){

                    if($service->is_active){
                        array_push($result, $service->dependency);
                    }else{
                        $service->is_active = true;
                        array_push($result, ($service->dependency)($this));
                    }
                }
                else {
                    array_push($result, ($service->dependency)($this));
                }
            }
        }
        return $result;
    }

    public function AddScop(string $key, Closure $callback){
        $service = new \stdClass;
        $service->is_singleton = false;
        $service->dependency = $callback;
        $this->services[$key] = $service;
    }

    public function AddSingleton(string $key, Closure $callback){
        $service = new \stdClass;
        $service->is_singleton = true;
        $service->is_active = false;
        $service->dependency = $callback;
        $this->services[$key] = $service;
    }
}