<?php
namespace DafCore;

class Application{
    use RouterMapMethods;

    private bool $isRelease = true;
    public static string $BaseFolder = 'App';
    public Router $Router;
    public IServicesProvidor $Services;
    private bool $calcTimePerformance = false;


    public function __construct(string $baseFolder = 'App', bool $isRelease = true) {
        self::$BaseFolder = $baseFolder;
        $this->isRelease = $isRelease;
        $this->Services = new ServicesProvidor(new DIContainer());
        $this->registerServices($this->Services);

        $this->SetUseGlobalComponent();
    }

    function AddGlobalMiddleware($callback){
        $this->Router->AddMiddleware($callback);
    }

    function AddAntiForgeryToken(){
        $this->Services->AddSingleton(AntiForgery::class);
        $this->AddGlobalMiddleware(function(IViewManager $vm, AntiForgery $antiForgery, callable $next){
            $vm->OnRender(function() use ($antiForgery){
                $antiForgery->RegisterToken();
            });
            $next();
         });
    }

    function Run(){
        if(!$this->calcTimePerformance)
            echo $this->Router->resolve();
        else echo $this->runWithTimePerformance();
    }

    function ShowTimePerformance(){
        $this->calcTimePerformance = true;
    }

    private function SetUseGlobalComponent()
    {
        $arr = [
            'vendor\DafCore\Components\*'
        ];

        if($this->isRelease){
            $arr = [
                'phar://vendor\DafCore.phar\Components\*'
            ];
        }
        Component::AddNamespaces($arr);
    }

    private function runWithTimePerformance(){
        if($this->calcTimePerformance)
            $startTime = microtime(true);

        echo $this->Router->resolve();

        if($this->calcTimePerformance){
            // Stop measuring time
            $endTime = microtime(true);
            
            // Calculate the execution time
            $executionTime = $endTime - $startTime;
            
            // Output the execution time
            echo "Execution time: " . number_format($executionTime, 6) . " seconds <br>";
        }
    }

    private function registerServices(ServicesProvidor $container) {
        $arr = [Request::class, Response::class, ApplicationContext::class, Router::class , ViewManager::class,
        Session::class, HeadOutlet::class, ScriptsOutlet::class];
        
        // Add interface bindings here
        $container->BindInterface(IRequest::class, Request::class);
        $container->BindInterface(IResponse::class, Response::class);
        $container->BindInterface(IViewManager::class, ViewManager::class);

        // Add App here
        $container->AddSingleton(Application::class, fn()=> $this);
        $container->AddSingleton(RequestBody::class, function(IServicesProvidor $sp){
            /** @var IRequest $req */
            $req = $sp->GetOne(IRequest::class);
            return $req->GetBody();
        });

        // Add Services arr here
        foreach ($arr as $d) $container->AddSingleton($d);

        try {
            // Load Router here
            $this->Router = $container->GetOne(Router::class);

        } catch (\Throwable $th) {
            echo $th->getMessage();
        }
    }
}

class ApplicationContext {
    public IRequest $request;
    public IResponse $response;

    public $endPoint;
    public array $endPointMetadata = [];

    public function __construct(IRequest $request, IResponse $response){
        $this->request = $request;
        $this->response = $response;
    }

    // function getEndpointMetadata() : array {
    //     if (is_array($this->endPoint))
    //         $ref = new \ReflectionMethod(...$this->endPoint);
    //     else $ref = new \ReflectionFunction($this->endPoint);

    //     return $ref->getAttributes();
    // }
}
/**
 * @mixin Application
 */
trait RouterMapMethods{
    function Get(string $path, ...$callback) {
        $this->Router->Get($path, ...$callback);
    }

    function Post(string $path, ...$callback) {
        $this->Router->Post($path, ...$callback);
    }

    function Put(string $path, ...$callback) {
        $this->Router->Put($path, ...$callback);
    }

    function Delete(string $path, ...$callback) {
        $this->Router->Delete($path, ...$callback);
    }

    function SetRouteBasePath(string $path){
        $this->Router->SetBasePath($path);
    }
    function AddController(string $controller){
        $this->Router->AddController($controller);
    }
}


namespace DafCore\Attributes;
use DafCore\Validator;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
abstract class ValidationAttribute {
    public function __construct(
        public string $errorMsg = ""
    ) {}

    public string $Msg = "";

    abstract function Validate($prop_name, $value, $displayName = "");

    protected function getDisplayName($prop_name, $displayName) {
        return !empty($displayName) ? $displayName : $prop_name;
    }

    protected function getErrorMsg($displayName, $value, $defaultMsg) {
        if(!empty($this->errorMsg)){
            return str_replace("{1}", $value, str_replace("{0}", $displayName, $this->errorMsg));
        } else {
            return $defaultMsg;
        }
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Url extends ValidationAttribute { 
    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        $pattern = '/^(https?:\/\/)?((([a-z\d]([a-z\d-]*[a-z\d])*)\.)+[a-z]{2,}|((\d{1,3}\.){3}\d{1,3}))(:\d+)?(\/[-a-z\d%_.~+]*)*(\?[;&a-z\d%_.~+=-]*)?(\#[-a-z\d_]*)?$/i';
        
        if(preg_match($pattern, $value) !== 1){
            $this->Msg = $this->getErrorMsg($displayName, $value, "field $displayName invalid address.");
            return false;
        }
        return true;
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Pattern extends ValidationAttribute { 
    public function __construct(
        public string $pattern,
        public string $errorMsg = ""
    ) {}

    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if(preg_match($this->pattern, $value) !== 1){
            $pattern = $this->pattern;
            $this->Msg = $this->getErrorMsg($displayName, $value, "field $displayName required pattern [$pattern].");
            return false;
        }
        return true;
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Email extends ValidationAttribute { 
    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->Msg = $this->getErrorMsg($displayName, $value, "field $displayName invalid address.");
            return false;
        } 

        return true;
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Range  extends ValidationAttribute
{
    public function __construct(
        public float $min = 0,
        public float $max = PHP_INT_MAX, 
        public string $errorMsg = "",
    ) {}

    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if($value < $this->min){
            $this->Msg = $this->getErrorMsg($displayName, $value, "field $displayName require min value of [".$this->min."].");
            return false;
        }

        if($value > $this->max){
            $this->msg = $this->getErrorMsg($displayName, $value, "field $displayName require max value max [".$this->max."]");
            return false;
        }

        return true;
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Length extends ValidationAttribute 
{
    public function __construct(
        public int $max = PHP_INT_MAX, 
        public int $min = 0, 
        public string $errorMsg = "",
        public string $maxErrorMsg = "",
        public string $minErrorMsg = "",
    ) {}

    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if(strlen($value) < $this->min){
            $this->Msg = $this->getErrorMsg($displayName, $this->min, "field $displayName require min length of ".$this->min);
            return false;
        }

        if(strlen($value) > $this->max){
            $this->Msg = $this->getErrorMsg($displayName, $this->max, "field $displayName require max length of ".$this->max);
            return false;
        }

        return true;
    }

    protected function getErrorMsg($displayName, $value, $defaultMsg) {
        $error_msg = $this->errorMsg ?? ($value == $this->min ? $this->minErrorMsg : $this->maxErrorMsg);
        if(!empty($error_msg)){
            return str_replace("{1}", $value, str_replace("{0}", $displayName, $error_msg));
        } else {
            return $defaultMsg;
        }
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class In extends ValidationAttribute {
    public function __construct(
        public $container,
        public string $errorMsg = ""
    ) {}

    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if(is_string($this->container)){
            if(!is_string($value)){
                $this->Msg = $this->getErrorMsg($displayName, $value, "field $displayName is require type string.");
                return false;
            }

            if(!str_contains($this->container, $value)){
                $this->Msg = $this->getErrorMsg($displayName, $value, "field $displayName unknown value [$value].");
                return false;
            }

            return true;
            
        }
        else if (is_array($this->container)){
            if(!is_array($value)){
                foreach ($this->container as $c_value) {
                    if($c_value === $value)
                        return true;
                }
            }else{
                foreach ($this->container as $c_value) {
                    if(in_array($c_value, $value))
                        return true;
                }
            }
        }

        $this->Msg = $this->getErrorMsg($displayName, $value, "field $displayName unknown value.");
        return false;
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Json extends ValidationAttribute {

    public function __construct(
        public string $errorMsg = "",
        public string $typeErrorMsg = ""
    ) {}

    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if(!is_string($value)){
            $this->Msg = $this->getErrorMessage($displayName, $value, "field $displayName is require type string with json value.", $this->typeErrorMsg);
            return false;
        }

        $decode = json_decode($value, true);
        if($decode === NULL){
            $this->Msg = $this->getErrorMsg($displayName, $value, "field $displayName incurrect json value.");
            return false;
        }

        return true;
    }
    protected function getErrorMessage($displayName, $value, $defaultMsg, $diffrentErrorMsg) {
        $error_msg = $this->errorMsg ?? $diffrentErrorMsg;
        if(!empty($error_msg)){
            return str_replace("{1}", $value, str_replace("{0}", $displayName, $error_msg));
        } else {
            return $defaultMsg;
        }
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class JsonValidateClass extends ValidationAttribute {
    public function __construct(
        public string $class,
        public string $errorMsg = "",
        public string $typeErrorMsg = "",
        public string $decodeErrorMsg = "",
        public string $createErrorMsg = ""
    ) {}

    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);
        
        if(!is_string($value)){
            $this->Msg = $this->getErrorMessage($displayName, $value, "field $displayName is require type string with json value.", $this->typeErrorMsg);
            return false;
        }

        $decode = json_decode($value, true);
        if($decode === NULL){
            $this->Msg = $this->getErrorMessage($displayName, $value, "field $displayName incurrect json value.", $this->decodeErrorMsg);
            return false;
        }

        try {
            $class = $this->class;
            $model = new $class($decode);

        } catch (\Throwable $th) {
            $this->Msg = $this->getErrorMessage($displayName, $value, "field $displayName error on create $class errorMsg:".$th->getMessage(), $this->createErrorMsg);
            return false;
        }

        if(!Validator::Validate($model)) {
            $this->Msg = implode("\n", Validator::$errorMsgs);
            return false;
        }

        return true;
    }
    protected function getErrorMessage($displayName, $value, $defaultMsg, $diffrentErrorMsg) {
        $error_msg = $this->errorMsg ?? $diffrentErrorMsg;
        if(!empty($error_msg)){
            return str_replace("{1}", $value, str_replace("{0}", $displayName, $error_msg));
        } else {
            return $defaultMsg;
        }
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ArrayValidateClass extends ValidationAttribute {
    public function __construct(
        public string $class,
        public string $errorMsg = ""
    ) {}

    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if(!is_array($value)){
            $this->Msg = $this->getErrorMsg($displayName, $value, "field $displayName require array type.");
            return false;
        }
        
        foreach ($value as $item) {
            if(is_array($item)){
                $tm = $this->class;
                $item = new $tm($item);
            }
            
            if(!Validator::Validate($item)) {
                $this->Msg = implode("\n", Validator::$errorMsgs);
                return false;
            }
        }

        return true;
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class OnlyEmpty extends ValidationAttribute {
    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if(!empty($value)){
            $this->Msg = $this->getErrorMsg($displayName, $value, "field $displayName required empty value.");
            return false;
        }

        return true;
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class NotNull extends ValidationAttribute {
    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if(!isset($value)){
            $this->Msg = $this->getErrorMsg($displayName, $value, "field $displayName is required.");
            return false;
        }

        return true;
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class NotEmpty extends ValidationAttribute {
    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if(empty($value)){
            $this->Msg = $this->getErrorMsg($displayName, $value, "field $displayName is required.");
            return false;
        }

        return true;
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Required extends ValidationAttribute {
    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);
        
        if(!isset($value) || empty($value)){
            $this->Msg = $this->getErrorMsg($displayName, $value, "field $displayName is required.");
            return false;
        }

        return true;
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DisplayName {
    public function __construct(
        public string $Text
    ) {}
}
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Validate { }