<?php
namespace DafCore;

use DafGlobals\Collections\Collection;
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

    private array $pendingControllers = [];
    private array $route_cache_meta = [];

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


    private function buildBasePath(string $controllerClass, $routeIns): string
    {
        $path = $routeIns->Path ?? "";
        $prefix = $routeIns->Prefix ?? "";

        if (empty($path)) {
            $path = $this->getControllerName($controllerClass);
        }

        if (!str_starts_with($path, "/")) {
            $path = "/" . $path;
        }

        if (!empty($prefix)) {
            $path = "/" . trim($prefix, "/") . $path;
        }

        return $path;
    }
    private function extractMiddlewares(Collection $attributes): array
    {
        $middlewares = [];
        foreach ($attributes as $attr) {
            $name = $attr->getName();
            if (!isset($this->http_methods_class_names[$name])) {
                $instance = $attr->newInstance();
                if (method_exists($instance, 'Handle')) {
                    $middlewares[] = [$instance, 'Handle'];
                }
            }
        }
        return $middlewares;
    }

    private function class_basename($class): string
    {
        return basename(str_replace('\\', '/', $class));
    }
    private function processControllerMethod($method, string $controllerClass, string $basePath, array $classMiddlewares): void
    {
        $methodAttributes = new Collection($method->getAttributes());
        $httpAttr = $methodAttributes->SingleOrDefault(fn($attr) => isset($this->http_methods_class_names[$attr->getName()]));

        if ($httpAttr === null) return;

        $httpAttrInstance = $httpAttr->newInstance();
        $httpMethod = strtolower(str_replace("Http", "", $this->class_basename($httpAttr->getName())));

        $methodPath = $httpAttrInstance->Path ?? "";
        $routePath = rtrim($basePath . "/" . $methodPath, "/");
        $routePath = str_replace("//", "/", $routePath);

        $middlewares = $this->extractMiddlewares($methodAttributes);
        $middlewares[] = [$controllerClass, $method->name];

        $this->routes[$httpMethod][$routePath] = array_merge($classMiddlewares, $middlewares);
    }

    function AddController(string $controllerClass){
        
        $reflection = new \ReflectionClass($controllerClass);
        $this->AddRoutesCacheDependency($reflection->getFileName());
        $attributes = new Collection($reflection->getAttributes());

        $routeAttr = $attributes->SingleOrDefault(fn($attr) => $attr->getName() === Route::class);
        if ($routeAttr === null) return;

        $routeIns = $routeAttr->newInstance();
        $basePath = $this->buildBasePath($controllerClass, $routeIns);
        
        $classMiddlewares = $this->extractMiddlewares($attributes);

        $methods = (new Collection($reflection->getMethods()))
            ->Where(fn($method) => $method->name !== '__construct' && $method->class === $controllerClass);
    
        foreach ($methods as $method) {
            $this->processControllerMethod($method, $controllerClass, $basePath, $classMiddlewares);
        }
    }
    public function LoadAllPendingControllers(): void
    {
        while (!empty($this->pendingControllers)) {
            $controller = array_shift($this->pendingControllers);
            $this->AddController($controller);
        }
    }


    public function AddRoutesCacheDependency(string $file): void
    {
        if (is_file($file)) {
            $this->route_cache_meta[$file] = filemtime($file) ?: 0;
        }
    }

    private function isCacheMetaValid(array $meta): bool
    {
        foreach ($meta as $file => $mtime) {
            if (!is_file($file) || (filemtime($file) ?: 0) !== $mtime) {
                return false;
            }
        }
        return true;
    }

    public function LoadRoutesCache(string $cacheFile): bool
    {
        if (!file_exists($cacheFile)) {
            return false;
        }
        $data = include $cacheFile;
        if (!is_array($data) || !isset($data['routes'])) {
            return false;
        }
        $meta = $data['meta'] ?? [];
        if (is_array($meta) && !$this->isCacheMetaValid($meta)) {
            return false;
        }

        // Merge cached routes into existing ones (keep existing if same key)
        foreach ($data['routes'] as $method => $routesByPath) {
            if (!isset($this->routes[$method])) {
                $this->routes[$method] = [];
            }
            $this->routes[$method] = $this->routes[$method] + $routesByPath;
        }

        $this->route_cache_meta = is_array($meta) ? $meta : [];

        // Prevent lazy loading from re-reflecting controllers
        if (property_exists($this, 'pendingControllers')) {
            $this->pendingControllers = [];
        }

        return true;
    }

    private function isCacheablePipeline(array $pipeline): bool
    {
        foreach ($pipeline as $step) {
            if ($step instanceof \Closure) {
                return false;
            }
            if (is_array($step) && isset($step[0]) && $step[0] instanceof \Closure) {
                return false;
            }
        }
        return true;
    }

    public function SaveRoutesCache(string $cacheFile): void
    {
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $cacheRoutes = [];
        foreach ($this->routes as $method => $routesByPath) {
            foreach ($routesByPath as $path => $pipeline) {
                if ($this->isCacheablePipeline($pipeline)) {
                    $cacheRoutes[$method][$path] = $pipeline;
                }
            }
        }

        $payload = [
            'routes' => $cacheRoutes,
            'meta' => $this->route_cache_meta,
        ];

        $serialized = serialize($payload);
        $content = "<?php\nreturn unserialize(" . var_export($serialized, true) . ");\n";
        file_put_contents($cacheFile, $content);
    }

    public function RegisterControllers(array $controllers): void
    {
        foreach ($controllers as $controller) {
            if (is_string($controller)) {
                $this->pendingControllers[] = $controller;
            }
        }
    }

    private function tryResolveFromPending(string $method, string $path): array|bool
    {
        while (!empty($this->pendingControllers)) {
            $controller = array_shift($this->pendingControllers);
            $this->AddController($controller);

            $pipeline = $this->getRoute($method, $path);
            if ($pipeline !== false) {
                return $pipeline;
            }
        }
        return false;
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
            $attributes = new Collection($ref->getAttributes());

            $middlewares = $this->extractMiddlewares($attributes);

            array_pop($pipline);
            return array_merge($pipline, $middlewares, [$ep]);
        }
        return $pipline;
    }

    private function addRoute(string $method, string $path, array $callbacks){
        $this->routes[$method][$this->MakePath($path)] = $this->GetTransfromAttrToMiddlewheres($callbacks);
    }
    
    function Get($path, ...$callback): void
    {
        $this->addRoute('get', $path, $callback);
    }
    function Put($path, ...$callback): void
    {
        $this->addRoute('put', $path, $callback);
    }
    function Post($path, ...$callback): void
    {
        $this->addRoute('post', $path, $callback);
    }
    function Delete($path, ...$callback): void
    {
        $this->addRoute('delete', $path, $callback);
    }
    
    private function capitalizePath($path) : string {
        $segments = explode('/', $path); // Split the path into segments
        $capitalizedSegments = array_map(function($segment) {
            return ucfirst($segment); // Capitalize the first letter of each segment
        }, $segments);
        
        return implode('/', $capitalizedSegments); // Reassemble the path
    }

    private function getRoute(string $method, string $path): array|bool
    {
        $candidates = $this->normalizeRouteCandidates($path);

        foreach ($candidates as $candidate) {
            if (isset($this->routes[$method][$candidate])) {
                return $this->routes[$method][$candidate];
            }
        }

        return $this->matchParameterizedRoute($method, $candidates[0]);
    }

    private function normalizeRouteCandidates(string $path): array
    {
        $trimmed = '/' . trim($path, '/');
        if ($trimmed === '//') {
            $trimmed = '/';
        }

        $lower = strtolower($trimmed);
        $capitalized = $this->capitalizePath($lower);

        return array_unique([$trimmed, $lower, $capitalized]);
    }

    private function matchParameterizedRoute(string $method, string $path): array|bool
    {
        foreach ($this->routes[$method] ?? [] as $routePath => $pipeline) {
            if (strpos($routePath, ':') === false) {
                continue;
            }

            $routeSegments = explode('/', $routePath);
            $pathSegments = explode('/', $path);

            if (count($routeSegments) !== count($pathSegments)) {
                continue;
            }

            $params = [];
            $match = true;

            foreach ($routeSegments as $idx => $segment) {
                $value = $pathSegments[$idx];
                if (str_starts_with($segment, ':')) {
                    $params[substr($segment, 1)] = $value;
                    continue;
                }

                if (strcasecmp($segment, $value) !== 0) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                $this->route_params = $params;
                $this->context->request->SetRouteParams($params);
                return $pipeline;
            }
        }

        return false;
    }

    private function getRouteOld($method, $path) : array | bool
    {
        if (isset($this->routes[$method][$path])) return $this->routes[$method][$path];

        $temp_path = strtolower($path);
        if (isset($this->routes[$method][$temp_path])) return $this->routes[$method][$temp_path];

        $temp_path = $this->capitalizePath($temp_path);
        if (isset($this->routes[$method][$temp_path])) return $this->routes[$method][$temp_path];

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
        //run the pipline for global middlewares
        $this->handleMiddlewares();
        
        //get the current request route string path
        $path = $this->context->request->getUrlPath();
        //get the current request method string name [GET,POST,DELETE,PUT]
        $method = strtolower($this->context->request->getMethod());
        //get the current route pipline array 
        $rotePipeline = $this->getRoute($method, $path);

        if ($rotePipeline === false && !empty($this->pendingControllers)) {
            $rotePipeline = $this->tryResolveFromPending($method, $path);
        }

        //if false no route found
        if ($rotePipeline === false) {
            if(file_exists("App/Views/_ErrorPage.php")){
                $this->context->response->Status(Response::HTTP_NOT_FOUND);
                return $this->viewManager->SetLayout('none')->RenderView('_ErrorPage', ["Status"=> 404,"Msg"=>"404 - Route Not Found"]);
            }
            return $this->context->response
                ->Status(Response::HTTP_NOT_FOUND)
                ->Send("404 - Route Not Found");
        }
        
        $this->middlewares = $rotePipeline;

        //get the route endpoint
        $ep = array_pop($this->middlewares);
        
        //if the endpoint is array of [class_name, method_name] load it
        if (is_array($ep)) {
            $this->loadControllerToDic($ep[0]);
            $this->context->endPoint = [$this->controller, $ep[1]];
        }else{
            $this->context->endPoint = $ep;
        }
        
        $this->endPoint = $ep;
        $this->middlewares[] = $this->wrapEndpoint($this->endPoint);

        //run route middlewares pipline
        $sr = $this->handleMiddlewares();
        return $sr;
    }
    


    private function handleMiddlewares(): string
    {
        $middleware = array_shift($this->middlewares);
        
        if (!$middleware) return '';

        return $this->invokeEndPoint($middleware, fn() => $this->handleMiddlewares());
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

    private function wrapEndpoint(string|array|callable $endpoint): callable
    {
        if (is_string($endpoint)) {
            return fn() => $this->viewManager->RenderView($endpoint);
        }

        if (is_array($endpoint)) {
            $callable = [$this->controller, $endpoint[1]];
            return fn() => $this->invokeEndPoint($callable);
        }

        return fn() => $this->invokeEndPoint($endpoint);
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

    private function getEndPointDependeces(callable $endPoint): array
    {
        $additionalParameters = array_merge($this->route_params, $this->context->request->getQueryParams());
        
        return $this->sp->getServicesForCallback($endPoint, onNotFound: function (\ReflectionParameter $param, $sp) use($additionalParameters) {
            $pn = $param->getName();
            $type = $param->getType();
            $pt = $type instanceof \ReflectionNamedType ? $type->getName() ?? "" : "";
            
            if (isset ($additionalParameters[$pn]))
                return $additionalParameters[$pn];
            else if (strlen($pt)) {
                if (is_subclass_of($pt, \DafCore\AutoConstruct::class)) {

                    try {
                        /** @var IRequest $req*/
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
