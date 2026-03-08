<?php
namespace DafCore\Routing;

use DafCore\IRequest;
use DafCore\Response;
use DafCore\IViewManager;
use DafCore\ServicesProvidor;
use DafCore\ApplicationContext;
use DafCore\Controllers\Controller;
use DafCore\Controllers\ApiController;
use DafGlobals\Collections\Collection;
use DafCore\Routing\RouteMatchContext;
use DafCore\Controllers\BaseController;
use DafCore\Controllers\Attributes\Route;
use DafGlobals\IO\Path;

class Router
{
    public bool $needRecach = false;

    private ServicesProvidor $sp;
    private IViewManager $viewManager;
    private ApplicationContext $context;

    private array $routes = [];
    private array $middlewares = [];
    private array $route_params = [];
    private array $globalMiddlewares = [];

    private array $http_methods_class_names = [
        Route::class => 1,
        \DafCore\Controllers\Attributes\HttpGet::class => 1,
        \DafCore\Controllers\Attributes\HttpPut::class => 1,
        \DafCore\Controllers\Attributes\HttpPost::class => 1,
        \DafCore\Controllers\Attributes\HttpDelete::class => 1
    ];

    private array $route_cache_meta = [];
    private array $pendingControllers = [];

    public function __construct(ServicesProvidor $sp, ApplicationContext $context, IViewManager $viewManager)
    {
        $this->sp = $sp;
        $this->context = $context;
        $this->viewManager = $viewManager;
    }


    public function AddMiddleware(callable $callback): void { $this->globalMiddlewares[] = $callback; }

    public function Get($path, ...$callback): void { $this->addRoute('get', $path, $callback); }
    public function Put($path, ...$callback): void { $this->addRoute('put', $path, $callback); }
    public function Post($path, ...$callback): void { $this->addRoute('post', $path, $callback); }
    public function Delete($path, ...$callback): void { $this->addRoute('delete', $path, $callback); }

    public function RegisterControllers(array $controllers): void
    {
        foreach ($controllers as $controller) {
            if (is_string($controller)) {
                $this->pendingControllers[] = $controller;
            }
        }
    }

    public function AddController(string $controllerClass): void {
        
        $reflection = new \ReflectionClass($controllerClass);
        $this->addRoutesCacheDependency($reflection->getFileName());
        $attributes = new Collection($reflection->getAttributes());

        $routeAttr = $attributes->SingleOrDefault(fn($attr) => $attr->getName() === Route::class);
        if ($routeAttr === null) return;

        $routeIns = $routeAttr->newInstance();
        $basePath = $this->buildBasePath($controllerClass, $routeIns);
        
        $data = $this->extractMiddlewaresAndMetadata($attributes);
        $classMetadata = $data['metadata'];
        $classMiddlewares = $data['middlewares'];

        $methods = (new Collection($reflection->getMethods()))
            ->Where(fn($method) => $method->name !== '__construct' && $method->class === $controllerClass);
    
        foreach ($methods as $method) {
            $this->processControllerMethod($method, $controllerClass, $basePath, $classMiddlewares, $classMetadata);
        }
    }





    public function Resolve(): string
    {
        $this->middlewares = [
            ...$this->globalMiddlewares,
            fn() => $this->dispatchRoute()
        ];

        return $this->handleMiddlewares() ?? '';
    }




    public function NeedsRecache(): bool { return $this->needRecach; }

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

        return true;
    }

    public function SaveRoutesCache(string $cacheFile): void
    {
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        // $cacheRoutes = [];
        // foreach ($this->routes as $method => $routesByPath) {
        //     foreach ($routesByPath as $path => $pipeline) {
        //         if ($this->isCacheablePipeline($pipeline)) {
        //             $cacheRoutes[$method][$path] = $pipeline;
        //         }
        //     }
        // }
        $cacheRoutes = [];
        foreach ($this->routes as $method => $routesByPath) {
            foreach ($routesByPath as $path => $entry) {
                $routeEntry = $this->normalizeRouteEntry($entry);
                if ($routeEntry === false) continue;

                if ($this->isCacheablePipeline($routeEntry['pipeline'])) {
                    $cacheRoutes[$method][$path] = $routeEntry;
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

    public function SaveRoutesCacheNotPretty(string $cacheFile): void
    {
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $cacheRoutes = [];
        foreach ($this->routes as $method => $routesByPath) {
            foreach ($routesByPath as $path => $entry) {
                $routeEntry = $this->normalizeRouteEntry($entry);
                if ($routeEntry === false) continue;

                if ($this->isCacheablePipeline($routeEntry['pipeline'])) {
                    $cacheRoutes[$method][$path] = [
                        'pipeline' => $routeEntry['pipeline'],
                        'metadata' => $this->serializeMetadataForCache($routeEntry['metadata'] ?? []),
                    ];
                }
            }
        }

        $payload = [
            'routes' => $cacheRoutes,
            'meta' => $this->route_cache_meta,
        ];

        $content = "<?php\nreturn " . var_export($payload, true) . ";\n";
        file_put_contents($cacheFile, $content);
    }




    private function addRoute(string $method, string $path, array $callbacks){
        $this->routes[$method][$path] = $this->transformAttrToPipelineAndMetadata($callbacks);
    }
    private function getRoute(string $method, string $path): array|bool
    {
        $candidates = $this->normalizeRouteCandidates($path);

        foreach ($candidates as $candidate) {
            if (isset($this->routes[$method][$candidate])) {
                return $this->normalizeRouteEntry($this->routes[$method][$candidate]);
            }
        }

        return $this->matchParameterizedRoute($method, $candidates[0]);
    }
    private function matchParameterizedRoute(string $method, string $path): array|bool
    {
        foreach ($this->routes[$method] ?? [] as $routePath => $entry) {
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
                $this->context->Request->SetRouteParams($params);
                return $this->normalizeRouteEntry($entry);
            }
        }

        return false;
    }


    public function handleMiddlewares(): ?string
    {
        $middleware = array_shift($this->middlewares);
        
        if (!$middleware) return null;

        return $this->invokeEndPoint($middleware, fn() => $this->handleMiddlewares());
    }
    private function invokeEndPoint(callable $endPoint, callable $next = null): ?string
    {
        $endPointDependeces = $this->getEndPointDependeces($endPoint);
        if($next) $endPointDependeces = [...$endPointDependeces, $next];

        try {
            $result = call_user_func_array($endPoint, $endPointDependeces);
            if ($result === null) return null;
            return (string)$result;
        } catch (\Throwable $th) {
            echo $th->getMessage();
            return "";
        }
    }
    private function wrapEndpoint(string|array|callable $endpoint): callable
    {
        if (is_string($endpoint)) {
            return fn() => $this->viewManager->RenderView($endpoint);
        }

        if (is_array($endpoint)) {
            $callable = [$endpoint[0], $endpoint[1]];
            return fn() => $this->invokeEndPoint($callable);
        }

        return fn() => $this->invokeEndPoint($endpoint);
    }


    private function dispatchRoute(): string
    {
        $ctx = $this->resolveCurrentRequest();
        $this->context->RouteContext = $ctx;

        if (!$ctx->Found) {
            return $this->executeNotFound($ctx);
        }

        $this->middlewares = [
            ...$ctx->Pipeline,
            $this->wrapEndpoint($ctx->Endpoint)
        ];

        return $this->handleMiddlewares() ?? '';
    }
    private function resolveCurrentRequest(): RouteMatchContext
    {
        $path = $this->context->Request->getUrlPath();
        $method = strtolower($this->context->Request->getMethod());
        return $this->resolveRequest($method, $path);
    }
    private function resolveRequest(string $method, string $path): RouteMatchContext
    {
        $this->route_params = [];
        $this->context->Request->SetRouteParams([]);

        $ctx = new RouteMatchContext($method, $path);
        $routeEntry = $this->getRoute($method, $path);

        if ($routeEntry === false && !empty($this->pendingControllers)) {
            $this->needRecach = true;
            $routeEntry = $this->tryResolveFromPending($method, $path);
        }

        if ($routeEntry === false) {
            $ctx->Found = false;
            $ctx->StatusCode = Response::HTTP_NOT_FOUND;
            return $ctx;
        }

        $routePipeline = $routeEntry['pipeline'];
        $ctx->EndPointMetadata = $routeEntry['metadata'] ?? [];

        $ep = array_pop($routePipeline);

        if (is_array($ep)) {
            $controller = $this->loadControllerToDic($ep[0]);
            $ctx->Endpoint = [$controller, $ep[1]];
        } else {
            $ctx->Endpoint = $ep;
        }

        $ctx->Found = true;
        $ctx->RouteParams = $this->route_params;
        $ctx->Pipeline = $routePipeline;

        return $ctx;
    }
    private function executeNotFound(RouteMatchContext $ctx): string
    {
        $appRootFolder = \DafCore\Application::$BaseFolder;
        $hostPath = Path::Combine($appRootFolder, "Views", "_Layouts", "Host");
        if(file_exists("$hostPath.view.php")){
            $this->context->Response ->Status($ctx->StatusCode);
            return $this->viewManager->RenderView("");
        }
        else return $this->context->Response
            ->Status($ctx->StatusCode)
            ->Send((string)$ctx->NotFoundPayload);
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



    private function loadControllerToDic(string $controllerClass): BaseController | Controller | ApiController
    {
        $this->sp->addSingleton($controllerClass);
        $this->sp->bindInterface(BaseController::class, $controllerClass);
        /** @var BaseController $controller */
        $controller = $this->sp->getOne($controllerClass);
        $controller->SetResponse($this->context->Response);

        if(method_exists($controller, "SetViewManager")){
            $this->sp->bindInterface(Controller::class, $controllerClass);
            /** @var Controller $controller */
            $controller->SetViewManager($this->viewManager);
        } else $this->sp->bindInterface(ApiController::class, $controllerClass);


        return $controller;
    }
    private function getEndPointDependeces(callable $endPoint): array
    {
        $additionalParameters = array_merge($this->route_params, $this->context->Request->getQueryParams());
        
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

                        // $param->getAttributes();
    
                        // $needValidate = array_filter($param->getAttributes(),
                        // fn($attr) => $attr->getName() === \DafCore\Attributes\Validate::class);
                        
                        // if(count($needValidate) && !Validator::Validate($obj)){
                        //     $this->context->Response
                        //     ->Status(Response::HTTP_BAD_REQUEST)
                        //     ->Json(Validator::GetErrors());
                        //     die();
                        // }
    
                        return $obj;
                    } catch (\Throwable $th) {
                        echo $th->getMessage();
                    }
                }
            }
        });
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
    private function getControllerName(string $classNameSpace) : string {
        // Logic to get the table name from the class name, e.g., "ProductsController" becomes "Products"
        $search_text = "Controller";
        
        $pathArr = explode("\\", $classNameSpace);
        $className = end($pathArr);
        if(str_contains($className, "Api") === true)
            $search_text = "Api".$search_text;

        return str_replace($search_text, "", substr($classNameSpace, strrpos($classNameSpace, "\\") + 1));
    }
    private function class_basename($class): string { return basename(str_replace('\\', '/', $class)); }
    private function capitalizePath($path) : string {
        $segments = explode('/', $path); // Split the path into segments
        $capitalizedSegments = array_map(function($segment) {
            return ucfirst($segment); // Capitalize the first letter of each segment
        }, $segments);
        
        return implode('/', $capitalizedSegments); // Reassemble the path
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
    
    
    private function extractMiddlewaresAndMetadata(Collection $attributes): array
    {
        $metadata = [];
        $middlewares = [];
        foreach ($attributes as $attr) {
            $name = $attr->getName();
            $instance = $attr->newInstance();
            if (!isset($this->http_methods_class_names[$name])) {
                if (method_exists($instance, 'Handle')) {
                    $middlewares[] = [$instance, 'Handle'];
                }
            }
            $metadata[$name] = $instance;
        }
        return [
            'metadata' => $metadata,
            'middlewares' => $middlewares
        ];
    }
    private function transformAttrToPipelineAndMetadata(array $pipeline): array
    {
        $ep = end($pipeline);
        if (!is_callable($ep)) {
            return ['pipeline' => $pipeline, 'metadata' => []];
        }

        $ref = new \ReflectionFunction($ep);
        $attributes = new Collection($ref->getAttributes());

        $data = $this->extractMiddlewaresAndMetadata($attributes);
        $metadata = $data['metadata'];
        $middlewares = $data['middlewares'];

        array_pop($pipeline);

        return [
            'pipeline' => array_merge($pipeline, $middlewares, [$ep]),
            'metadata' => $metadata
        ];
    }
    private function processControllerMethod($method, string $controllerClass, string $basePath, array $classMiddlewares, array $classMetadata): void
    {
        $methodAttributes = new Collection($method->getAttributes());
        $httpAttr = $methodAttributes->SingleOrDefault(fn($attr) => isset($this->http_methods_class_names[$attr->getName()]));

        if ($httpAttr === null) return;

        $httpAttrInstance = $httpAttr->newInstance();
        $httpMethod = strtolower(str_replace("Http", "", $this->class_basename($httpAttr->getName())));

        $methodPath = trim((string)($httpAttrInstance->Path ?? ""), "/");
        $routePath = rtrim($basePath, "/");
        $routePath = $routePath . ($methodPath !== "" ? "/" . $methodPath : "");
        $routePath = $routePath === "" ? "/" : $routePath;

        $data = $this->extractMiddlewaresAndMetadata($methodAttributes);
        $methodMetadata = $data['metadata'];
        $middlewares = $data['middlewares'];

        $middlewares[] = [$controllerClass, $method->name];
        $metadata = [...$classMetadata, ...$methodMetadata]; // method overrides class on same key

        $this->routes[$httpMethod][$routePath] = [
            'pipeline' => array_merge($classMiddlewares, $middlewares),
            'metadata' => $metadata,
        ];
    }


    //||===================||
    //||  Cash Helpers     ||
    //||===================||
    private function addRoutesCacheDependency(string $file): void
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

    // private function normalizeRouteEntry(mixed $entry): array|bool
    // {
    //     if (!is_array($entry) || !array_key_exists('pipeline', $entry)) {
    //         return false;
    //     }

    //     $entry['metadata'] = $entry['metadata'] ?? [];
    //     return $entry;
    // }

    private function normalizeRouteEntry(mixed $entry): array|bool
    {
        if ($entry === false || !is_array($entry) || !array_key_exists('pipeline', $entry)) {
            return false;
        }

        $rawMeta = is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [];
        $entry['metadata'] = $this->hydrateMetadataFromCache($rawMeta);

        return $entry;
    }

    private function serializeMetadataForCache(array $metadata): array
    {
        $out = [];

        foreach ($metadata as $class => $instance) {
            if (!is_object($instance)) continue;

            $args = [];
            $ro = new \ReflectionObject($instance);
            $ctor = $ro->getConstructor();

            if ($ctor !== null) {
                foreach ($ctor->getParameters() as $p) {
                    $name = $p->getName();
                    if ($ro->hasProperty($name)) {
                        $prop = $ro->getProperty($name);
                        $prop->setAccessible(true);
                        $args[] = $prop->getValue($instance);
                    }
                }
            }

            $out[$class] = ['args' => $args];
        }

        return $out;
    }

    private function hydrateMetadataFromCache(array $metadata): array
    {
        $out = [];

        foreach ($metadata as $class => $item) {
            try {
                $args = is_array($item) ? ($item['args'] ?? []) : [];
                $ref = new \ReflectionClass($class);
                $out[$class] = $ref->newInstanceArgs($args);
            } catch (\Throwable $e) {
                // skip invalid cached metadata entries
            }
        }

        return $out;
    }
}
