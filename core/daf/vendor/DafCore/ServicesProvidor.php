<?php
namespace DafCore;

interface IServicesForCallback{
    public function GetServicesForCallback(string | callable $classOrFunction, callable $onNotFound = null) : array;
}

interface IServicesProvidor{
    public function GetOne(string $serviceName);
    public function BindInterface(string $interface, string $concrete);
    public function AddSingleton(string $serviceName, \Closure $callback = null) : self;
}

class ServicesProvidor implements IServicesProvidor,IServicesForCallback {
    private DIContainer $container;
    public static IDIContainer $DI;

    public function __construct(DIContainer $dIContainer){
        $this->container = $dIContainer;

        $this->container->AddSingleton(ServicesProvidor::class, fn()=> $this);
        $this->container->BindInterface(IServicesProvidor::class, ServicesProvidor::class);
        $this->container->BindInterface(IServicesForCallback::class, ServicesProvidor::class);
        self::$DI = $this->container;
    }

    public function AddSingleton(string $serviceName, \Closure $callback = null) : IServicesProvidor {
        if($callback){
            $this->container->AddSingleton($serviceName, fn()=> $callback($this));
            return $this;
        }

        $this->container->AddSingleton($serviceName, function($c) use ($serviceName) {
            $vars = $this->GetServicesForCallback($serviceName);
            return new $serviceName(...$vars);
        });
        return $this;
    }

    public function BindInterface(string $interface, string $concrete){
        return $this->container->BindInterface($interface, $concrete);
    }

    public function GetOne(string $serviceName){
        return $this->container->GetOne($serviceName);
    }

    //get the requsted services for the callback params - callback can be a function or a class method or a class
    public function GetServicesForCallback(string | callable $classOrCallable, callable $onNotFound = null) : array {
        if(is_string($classOrCallable))
        {
            try {
                $reflection = new \ReflectionMethod($classOrCallable."::__construct");
            } catch (\Throwable $th) {
                return [];
            }
        }
        else if (is_array($classOrCallable))
            $reflection = new \ReflectionMethod(...$classOrCallable);
        else $reflection = new \ReflectionFunction($classOrCallable);

        if(!$reflection) return [];

        $params = $reflection->getParameters();
        $services = [];
        foreach ($params as $param) {
            $type = $param->getType();

            $pt = $type instanceof \ReflectionNamedType ? $type->getName() ?? "" : "";

            if($this->container->exists($pt))
                $services[] = $this->container->GetOne($pt);
            else if($onNotFound) {
                $value = $onNotFound($param, $this);
                if(isset($value)){
                    $services[] = $value;
                }
            }
        }
        return $services;
    }
}