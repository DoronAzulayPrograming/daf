<?php
namespace DafCore;

use DafGlobals\Collection;
use DafCore\Controllers\Controller;
use DafCore\Controllers\ApiController;
use DafCore\Controllers\BaseController;
use DafCore\Controllers\Attributes\Route;

class Router
{
    private ServicesProvidor $sp;
    private ViewManager $viewManager;
    private BaseController $controller;
    private ApplicationContext $context;

    private array $routes = [];
    private array $middlewares = [];
    private array $route_params = [];

    private $endPoint;

    private string $routeBasePath = "";

    private array $http_methods_class_names = [
        Route::class => 1,
        \DafCore\Controllers\Attributes\HttpGet::class => 1,
        \DafCore\Controllers\Attributes\HttpPut::class => 1,
        \DafCore\Controllers\Attributes\HttpPost::class => 1,
        \DafCore\Controllers\Attributes\HttpDelete::class => 1
    ];


    public function __construct(ServicesProvidor $sp, ApplicationContext $context, ViewManager $viewManager)
    {
        $this->sp = $sp;
        $this->context = $context;
        $this->viewManager = $viewManager;
    }

    function SetBasePath(string $basePath){
        $this->routeBasePath = $basePath ?? "";
    }

    function AddMiddleware(callable $callback) {
        $this->middlewares[] = $callback;
    }
    private function getControllerName(string $classNameSpace) : string {
        // Logic to get the table name from the class name, e.g., "ProductsController" becomes "Products"
        $search_text = "Controller";
        
        $pathArr = explode("\\", $classNameSpace);
        $className = end($pathArr);
        if(str_contains($className, "Api") === true)
            $search_text = "Api".$search_text;

        return str_replace($search_text, "", substr($classNameSpace, strrpos($classNameSpace, "\\") + 1));
    }

    function AddController(string $controllerClass){
        $reflection = new \ReflectionClass($controllerClass);
        $ref_attrs = $reflection->getAttributes();
        $attributes = new Collection($ref_attrs);
        $routeAttr = $attributes->SingleOrDefault(fn($attr) => $attr->getName() === Route::class);
       
        if($routeAttr === null) return;
        $routeIns = $routeAttr->newInstance();

        $path = $routeIns->Path ?? "";
        $prefix = $routeIns->Prefix ?? "";
        $path = !empty($path) ? $path : $this->getControllerName($controllerClass);

        if(str_starts_with($path, "/") === false)
            $path = "/".$path;
        
        if(!empty($prefix))
            $path = "/" .$prefix.$path;
        
        $methods = $reflection->getMethods();
        $methods = (new Collection($methods))->Where(fn($method) => strcmp($method->name, "__construct") !== 0 && strcmp($method->class, $controllerClass) === 0 );
        $routeList = &$this->routes;

        $http_methods_class_names = $this->http_methods_class_names;

        $class_middlewares = [];
        $attributes->ForEach(function($attr) use(&$class_middlewares, $http_methods_class_names){
            $an = $attr->getName();
            if(!isset($http_methods_class_names[$an])){
                $ins = $attr->newInstance();
                if(method_exists($ins,'Handle'))
                    $class_middlewares[] = [$ins, 'Handle'];
            }
        });

        $methods->ForEach(function($method) use($class_middlewares, $path, $controllerClass, &$routeList){
            $middlewares = [];
            $methodAttributes = new Collection($method->getAttributes());
            $httpAttribute = $methodAttributes->SingleOrDefault(function($attr){ 
                $an = $attr->getName();
                if($an === \DafCore\Controllers\Attributes\HttpGet::class || 
                    $an === \DafCore\Controllers\Attributes\HttpPut::class ||
                    $an === \DafCore\Controllers\Attributes\HttpPost::class ||
                    $an === \DafCore\Controllers\Attributes\HttpDelete::class
                )
                    return true;

                return false;
            });

            if($httpAttribute === null) return;
            $httpAttributeIns = $httpAttribute->newInstance();

            $arr = explode("\\", $httpAttribute->getName());

            $http_method = strtolower(str_replace("Http", "",end($arr)));
            $method_path = $httpAttributeIns->Path ?? null;
            $route_path = $path. (empty($method_path) ? "" : "/".$method_path);
            $route_path = str_replace("//", "/", $route_path);
            
            $http_methods_class_names = $this->http_methods_class_names;

            $methodAttributes->ForEach(function($attr) use(&$middlewares, $http_methods_class_names){
                $an = $attr->getName();
                $this->context->endPointMetadata[$an] = 1;
                if(!isset($http_methods_class_names[$an])){
                    $ins = $attr->newInstance();
                    if(method_exists($ins,'handle'))
                        $middlewares[] = [$ins, 'handle'];
                }
            });
            $middlewares[] = [$controllerClass, $method->name];
            $routeList[$http_method][$route_path] = array_merge($class_middlewares, $middlewares);
        });
    }

    private function MakePath(string $path) : string {
        if($path === "/" && !empty($this->routeBasePath))
            $path = "";
        return $this->routeBasePath.$path;
    }
    private function GetTransfromAttrToMiddlewheres(array $pipline){
        $ep = end($pipline);
        if(is_callable($ep)){
            $ref = new \ReflectionFunction($ep);
            $ref_attrs = $ref->getAttributes();
            $attributes = new Collection($ref_attrs);

            $middlewares = [];
            $http_methods_class_names = $this->http_methods_class_names;
            $attributes->ForEach(function($attr) use(&$middlewares, $http_methods_class_names){
                $an = $attr->getName();
                $this->context->endPointMetadata[$an] = 1;
                if(!isset($http_methods_class_names[$an])){
                    $ins = $attr->newInstance();
                    if(method_exists($ins,'handle'))
                        $middlewares[] = [$ins, 'handle'];
                }
            });
            array_pop($pipline);
            return array_merge($pipline, $middlewares, [$ep]);
        }
        return $pipline;
    }
    function Get($path, ...$callback)
    {
        $this->routes['get'][$this->MakePath($path)] = $this->GetTransfromAttrToMiddlewheres($callback);
    }

    function Put($path, ...$callback)
    {
        $this->routes['put'][$this->MakePath($path)] = $this->GetTransfromAttrToMiddlewheres($callback);
    }

    function Post($path, ...$callback)
    {
        $this->routes['post'][$this->MakePath($path)] = $this->GetTransfromAttrToMiddlewheres($callback);
    }

    function Delete($path, ...$callback)
    {
        $this->routes['delete'][$this->MakePath($path)] = $this->GetTransfromAttrToMiddlewheres($callback);
    }
    
    private function capitalizePath($path) : string {
        $segments = explode('/', $path); // Split the path into segments
        $capitalizedSegments = array_map(function($segment) {
            return ucfirst($segment); // Capitalize the first letter of each segment
        }, $segments);
        
        return implode('/', $capitalizedSegments); // Reassemble the path
    }
    private function getRoute($method, $path) : array | bool
    {
        if (empty(count($this->routes)))
            return false;

        if (isset($this->routes[$method][$path])) {
            return $this->routes[$method][$path];
        } else {
            $temp_path = strtolower($path);
            if (isset($this->routes[$method][$temp_path])) {
                return $this->routes[$method][$temp_path];
            } else {
                $temp_path = $this->capitalizePath($temp_path);
                if (isset($this->routes[$method][$temp_path])) {
                    return $this->routes[$method][$temp_path];
                }
            }
        }

        foreach ($this->routes[$method] as $key => $value) {
            if (strpos($key, ":") !== false) {
                $keySegments = explode("/", $key);
                $pathSegments = explode("/", $path);
                $routeParams = [];

                if (count($keySegments) === count($pathSegments)) {
                    $match = true;
                    foreach ($keySegments as $index => $segment) {
                        if (strpos($segment, ":") === 0) {
                            $paramKey = substr($segment, 1);
                            $routeParams[$paramKey] = $pathSegments[$index];
                        } elseif ($segment !== $pathSegments[$index] && strtolower($segment) !== strtolower($pathSegments[$index])) {
                            $match = false;
                            break;
                        }
                    }

                    if ($match) {
                        $this->route_params = $routeParams;
                        $this->context->request->SetRouteParams($this->route_params);
                        return $value;
                    }
                }
            }
        }

        return false;
    }

    public function Resolve(): string | null
    {
        //adding some object to the end of the pipline because 
        //the last one not trigger (for more look in handleMiddlewares func)
        $this->middlewares[] = false; 

        //run the pipline for global middlewares
        $this->handleMiddlewares();
        
        //get the current request route string path
        $path = $this->context->request->getUrlPath();
        //get the current request method string name [GET,POST,DELETE,PUT]
        $method = strtolower($this->context->request->getMethod());
        //get the current route pipline array 
        $rotePipeline = $this->getRoute($method, $path);

        //if false no route found
        if ($rotePipeline === false) {
            return $this->context->response
                ->Status(Response::HTTP_NOT_FOUND)
                ->Send("404 - Route Not Found");
        }
        
        $this->middlewares = $rotePipeline;

        //get the route endpoint
        $ep = end($this->middlewares);
        
        //if the endpoint is array of [class_name, method_name] load it
        if (is_array($ep)) {
            $this->loadControllerToDic($ep[0]);
            $this->context->endPoint = [$this->controller, $ep[1]];
        }else{
            $this->context->endPoint = $ep;
        }

        //run route middlewares pipline
        $this->handleMiddlewares();

        //if the endpoint is array of [class_name, method_name] set the endpoit to the current one
        if (is_array($this->endPoint)) {
            $this->setControllerAsEndPoint($this->endPoint);
        }

        if(is_string($this->endPoint)){
            return $this->viewManager->RenderView($this->endPoint);
        }
        
        if (!is_callable($this->endPoint)) {
            return "";
        }

        //run the endpoint
        return $this->invokeEndPoint($this->endPoint);
    }

    private function handleMiddlewares()
    {
        $middleware = array_shift($this->middlewares) ?? null;

        if ($middleware) {
            if (!count($this->middlewares))
                $this->endPoint = $middleware;
            else
                $this->invokeEndPoint($middleware, fn() => $this->handleMiddlewares());
        }
    }

    private function invokeEndPoint(callable $endPoint, callable $next = null): string
    {
        $endPointDependeces = $this->getEndPointDependeces($endPoint);
        if($next) $endPointDependeces = [...$endPointDependeces, $next];

        try {
            return call_user_func_array($endPoint, $endPointDependeces) ?? "";
        } catch (\Throwable $th) {
            echo $th->getMessage();
        }
        return "";
    }

    private function loadControllerToDic(string $controllerClass)
    {
        $this->sp->addSingleton($controllerClass);
        $this->sp->bindInterface(BaseController::class, $controllerClass);
        /** @var BaseController $controller */
        $controller = $this->sp->getOne($controllerClass);
        $controller->SetResponse($this->context->response);

        if(method_exists($controller, "SetViewManager")){
            $this->sp->bindInterface(Controller::class, $controllerClass);
            /** @var Controller $controller */
            $controller->SetViewManager($this->viewManager);
        }else $this->sp->bindInterface(ApiController::class, $controllerClass);

        $this->controller = $controller;
    }
    private function setControllerAsEndPoint(array $endPoint)
    {
        $method = $endPoint[1];
        $this->endPoint = [$this->controller, $method];
    }

    private function getEndPointDependeces(callable $endPoint): array
    {
        $additionalParameters = array_merge($this->route_params, $this->context->request->getQueryParams());
        
        return $this->sp->getServicesForCallback($endPoint, onNotFound: function (\ReflectionParameter $param, $sp) use($additionalParameters) {
            $pn = $param->getName();
            $pt = $param->getType()?->getName() ?? "";

            if (isset ($additionalParameters[$pn]))
                return $additionalParameters[$pn];
            else if (strlen($pt)) {
                if (is_subclass_of($pt, \DafCore\AutoConstruct::class)) {
                    /** @var IRequest $req*/

                    try {
                        $req = $sp->getOne(IRequest::class);
                        $obj = new $pt($req->GetBodyArray());
                        $param->getAttributes();
    
                        $needValidate = array_filter($param->getAttributes(),
                        fn($attr) => $attr->getName() === \DafCore\Attributes\Validate::class);
                        
                        if(count($needValidate) && !Validator::Validate($obj)){
                            $this->context->response
                            ->status(Response::HTTP_BAD_REQUEST)
                            ->json(Validator::$errorMsgs);
                            die();
                        }
    
                        return $obj;
                    } catch (\Throwable $th) {
                        echo $th->getMessage();
                    }
                }
            }
        });
    }
}
