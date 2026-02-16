<?php
namespace DafCore;

use DafCore\Flash\FlashMessages;
use DafCore\Flash\IFlashMessages;
use DafCore\Forms\FormContext;
use DafCore\Forms\IFormFeedback;
use DafCore\Forms\FlashFormFeedback;
use DafCore\Forms\IFormValidator;
use DafCore\Forms\FormValidator;
use DafCore\Flash\IFlashStore;
use DafCore\Flash\SessionFlashStore;

require_once __DIR__."/Controllers/Attributes.php";

class Application{
    use RouterMapMethods;

    public static $BuildOnly = false;
    private bool $isRelease = true;
    public static string $BaseFolder = '';
    public Router $Router;
    public IServicesProvidor $Services;
    private string $executionTime = "";


    public function __construct(bool $isRelease = true) {

        $this->loadAppBaseFolder();
        $this->isRelease = $isRelease;
        $this->Services = new ServicesProvidor(new DIContainer());

        $this->registerSystemServices($this->Services);
        $this->registerSystemComponents();
    }

    public function AddGlobalMiddleware($callback): void { $this->Router->AddMiddleware($callback); }

    public function AddAntiForgeryToken(){
        $this->Services->AddSingleton(AntiForgery::class);
        $this->AddGlobalMiddleware(function(IViewManager $vm, AntiForgery $antiForgery, callable $next){
            $vm->OnRender(function() use ($antiForgery){
                $antiForgery->RegisterToken();
            });
            return $next();
         });
    }

    public function Run(): void {
        if (self::$BuildOnly) return;

        $startTime = microtime(true);

        $cacheFile = \DafGlobals\IO\Path::Combine("vendor", "storage", "routes.cache.php");
        $loaded = $this->Router->LoadRoutesCache($cacheFile);

        echo $this->Router->Resolve();

        if (!$loaded || Router::$NeedRecach) {
            $this->Router->SaveRoutesCache($cacheFile);
        }

        $endTime = microtime(true);
        $time = $endTime - $startTime;

        $this->executionTime = number_format($time*10, 3);
    }


    public function GetExecutionTime(): string { return $this->executionTime; }

    /**
     * Returns the current request host name.
     *
     * This value is taken from the HTTP_HOST server variable and represents
     * the domain name (and optional port) used by the client to access
     * the application (e.g. "example.com" or "example.com:8080").
     *
     * @return string The request host name.
     */
    static function HostName(): string { return $_SERVER['HTTP_HOST']; }

    /**
     * Determines whether the current request is using HTTPS.
     *
     * Checks the HTTPS server variable and returns true if the request
     * was made over a secure SSL/TLS connection.
     *
     * @return bool True if the request is HTTPS, otherwise false.
     */
    static function IsHttps(): bool { return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; }


    private function registerSystemComponents()
    {
        $arr = [
            'vendor\DafCore\Components',
            'vendor\DafCore\Components\Forms',
            'vendor\DafCore\Components\Forms\Inputs',
            ];

        if($this->isRelease){ $arr = [
            'phar://vendor\DafCore.phar\Components' ,
            'phar://vendor\DafCore.phar\Components\Forms' ,
            'phar://vendor\DafCore.phar\Components\Forms\Inputs' ,
        ]; }

        SystemComponent::AddNamespaces($arr);
    }

    private function registerSystemServices(ServicesProvidor $container) {
        $arr = [
            Request::class, ApplicationContext::class, 
            Router::class, Session::class,
            HeadOutlet::class, ScriptsOutlet::class
        ];

        // Add interface bindings here
        $container->BindInterface(IRequest::class, Request::class);
        
        $container->AddSingletonInterfaceAndClass(IResponse::class, Response::class);
        $container->AddSingletonInterfaceAndClass(IViewManager::class, ViewManager::class);

        $container->AddSingleton(FormContext::class);
        $container->AddSingletonInterfaceAndClass(IFlashStore::class, SessionFlashStore::class);
        $container->AddSingletonInterfaceAndClass(IFlashMessages::class, FlashMessages::class);

        $container->AddSingletonInterfaceAndClass(IFormFeedback::class, FlashFormFeedback::class);
        $container->AddSingletonInterfaceAndClass(IFormValidator::class, FormValidator::class);
        

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

    private function loadAppBaseFolder(): void{
        self::$BaseFolder = basename(dirname(__DIR__,2));
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
    function RegisterControllers(array $controllers){
        $this->Router->RegisterControllers($controllers);
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

    public function ToClientRule(string $propName, string $displayName = ""): ?array
    {
        $displayName = $this->getDisplayName($propName, $displayName);
        return null; // default: not supported on client
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Url extends ValidationAttribute { 
    public function __construct(public string $errorMsg = "field {0} invalid address.") {
        parent::__construct($errorMsg);
    }
    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        $pattern = '/^(https?:\/\/)?((([a-z\d]([a-z\d-]*[a-z\d])*)\.)+[a-z]{2,}|((\d{1,3}\.){3}\d{1,3}))(:\d+)?(\/[-a-z\d%_.~+]*)*(\?[;&a-z\d%_.~+=-]*)?(\#[-a-z\d_]*)?$/i';
        
        if(preg_match($pattern, $value) !== 1){
            $this->Msg = $this->getErrorMsg($displayName, $value, $this->errorMsg);
            return false;
        }
        return true;
    }
    public function ToClientRule(string $propName, string $displayName = ""): ?array
    {
        $displayName = $this->getDisplayName($propName, $displayName);
        return [
            'name' => 'url',
            'msgTemplate' => $this->errorMsg,
        ];
    }

}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Pattern extends ValidationAttribute { 
    public function __construct(
        public string $pattern,
        public string $errorMsg = "field {0} required pattern [{1}]."
    ) { parent::__construct($errorMsg); }

    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if(preg_match($this->pattern, $value) !== 1){
            $pattern = $this->pattern;
            $this->Msg = $this->getErrorMsg($displayName, $pattern, $this->errorMsg);
            return false;
        }
        return true;
    }
    public function ToClientRule(string $propName, string $displayName = ""): ?array
    {
        $displayName = $this->getDisplayName($propName, $displayName);
        return [
            'name' => 'pattern',
            'pattern' => $this->pattern,
            'msgTemplate' => $this->errorMsg,
        ];
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Email extends ValidationAttribute { 
    public function __construct(
        public string $errorMsg = "field {0} invalid address."
    ) { parent::__construct($errorMsg); }

    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->Msg = $this->getErrorMsg($displayName, $value, $this->errorMsg);
            return false;
        } 

        return true;
    }

    public function ToClientRule(string $propName, string $displayName = ""): ?array
    {
        $displayName = $this->getDisplayName($propName, $displayName);
        return [
            'name' => 'email',
            'msgTemplate' => $this->errorMsg,
        ];
    }


}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Range  extends ValidationAttribute
{
    public function __construct(
        public float $min = 0,
        public float $max = PHP_INT_MAX, 
        public string $errorMsg = "",
        public string $minErrorMsg = "field {0} require min value of [{1}].",
        public string $maxErrorMsg = "field {0} require max value max [{1}].",
    ) { parent::__construct($errorMsg); }

    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if($value < $this->min){
            $this->Msg = $this->getErrorMsg($displayName, $value, $this->minErrorMsg);
            return false;
        }

        if($value > $this->max){
            $this->msg = $this->getErrorMsg($displayName, $value, $this->maxErrorMsg);
            return false;
        }

        return true;
    }

    public function ToClientRule(string $propName, string $displayName = ""): ?array
    {
        $displayName = $this->getDisplayName($propName, $displayName);

        $minTemplate = !empty($this->errorMsg) ? $this->errorMsg : $this->minErrorMsg;
        $maxTemplate = !empty($this->errorMsg) ? $this->errorMsg : $this->maxErrorMsg;

        return [
            'name' => 'range',
            'min' => $this->min,
            'max' => $this->max,
            'minMsgTemplate' => $minTemplate,
            'maxMsgTemplate' => $maxTemplate,
        ];
    }

}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Length extends ValidationAttribute 
{
    public function __construct(
        public int $max = PHP_INT_MAX, 
        public int $min = 0, 
        public string $errorMsg = "",
        public string $maxErrorMsg = "field {0} require max length of {1}",
        public string $minErrorMsg = "field {0} require min length of {1}",
    ) { parent::__construct($errorMsg); }

    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if (mb_strlen($value, 'UTF-8') < $this->min) {
            $this->Msg = $this->getErrorMsg($displayName, $this->min, $this->minErrorMsg);
            return false;
        }

        if (mb_strlen($value, 'UTF-8') > $this->max) {
            $this->Msg = $this->getErrorMsg($displayName, $this->max, $this->maxErrorMsg);
            return false;
        }

        return true;
    }


    protected function getErrorMsg($displayName, $value, $defaultMsg) {
        $error_msg = !empty($this->errorMsg) ? $this->errorMsg : ($value == $this->min ? $this->minErrorMsg : $this->maxErrorMsg);
        if(!empty($error_msg)){
            return str_replace("{1}", $value, str_replace("{0}", $displayName, $error_msg));
        } else {
            return $defaultMsg;
        }
    }

    public function ToClientRule(string $propName, string $displayName = ""): ?array
    {
        $displayName = $this->getDisplayName($propName, $displayName);

        $minTemplate = !empty($this->errorMsg) ? $this->errorMsg : $this->minErrorMsg;
        $maxTemplate = !empty($this->errorMsg) ? $this->errorMsg : $this->maxErrorMsg;

        return [
            'name' => 'length',
            'min' => $this->min,
            'max' => $this->max,
            'minMsgTemplate' => $minTemplate,
            'maxMsgTemplate' => $maxTemplate,
        ];
    }

}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class In extends ValidationAttribute {
    public function __construct(
        public $container,
        public string $errorMsg = "field {0} unknown value."
    ) {parent::__construct($errorMsg);}

    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if(is_string($this->container)){
            if(!is_string($value)){
                $this->Msg = $this->getErrorMsg($displayName, $value, "field $displayName is require type string.");
                return false;
            }

            if(!str_contains($this->container, $value)){
                $this->Msg = $this->getErrorMsg($displayName, $value, $this->errorMsg);
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

        $this->Msg = $this->getErrorMsg($displayName, $value, $this->errorMsg);
        return false;
    }

    public function ToClientRule(string $propName, string $displayName = ""): ?array
    {
        $displayName = $this->getDisplayName($propName, $displayName);
        return [
            'name' => 'in',
            'container' => $this->container,
            'msgTemplate' => $this->errorMsg,
        ];
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
            $this->Msg = implode("\n", Validator::GetErrors());
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
                $this->Msg = implode("\n", Validator::GetErrors());
                return false;
            }
        }

        return true;
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class OnlyEmpty extends ValidationAttribute {
    public function __construct(
        public string $errorMsg = "field {0} required empty value."
    ) { parent::__construct($errorMsg); }

    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if(!empty($value)){
            $this->Msg = $this->getErrorMsg($displayName, $value, $this->errorMsg);
            return false;
        }

        return true;
    }
   
    public function ToClientRule(string $propName, string $displayName = ""): ?array
    {
        $displayName = $this->getDisplayName($propName, $displayName);
        return [
            'name' => 'onlyempty',
            'msgTemplate' => $this->errorMsg,
        ];
    }

}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class NotNull extends ValidationAttribute {
    public function __construct(
        public string $errorMsg = "field {0} is required."
    ) { parent::__construct($errorMsg); }

    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if(!isset($value)){
            $this->Msg = $this->getErrorMsg($displayName, $value, $this->errorMsg);
            return false;
        }

        return true;
    }

    public function ToClientRule(string $propName, string $displayName = ""): ?array
    {
        $displayName = $this->getDisplayName($propName, $displayName);
        return [
            'name' => 'notnull',
            'msgTemplate' => $this->errorMsg,
        ];
    }

}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class NotEmpty extends ValidationAttribute {
    public function __construct(
        public string $errorMsg = "field {0} is required."
    ) { parent::__construct($errorMsg); }

    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);

        if(empty($value)){
            $this->Msg = $this->getErrorMsg($displayName, $value, $this->errorMsg);
            return false;
        }

        return true;
    }

    public function ToClientRule(string $propName, string $displayName = ""): ?array
    {
        $displayName = $this->getDisplayName($propName, $displayName);
        return [
            'name' => 'notempty',
            'msgTemplate' => $this->errorMsg,
        ];
    }

}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Required extends ValidationAttribute {
    public function __construct(
        string $errorMsg = "field {0} is required."
    ) { parent::__construct($errorMsg); }

    function Validate($prop_name, $value, $displayName = ""){
        $displayName = $this->getDisplayName($prop_name, $displayName);
        
        if(!isset($value) || empty($value)){
            $this->Msg = $this->getErrorMsg($displayName, $value, $this->errorMsg);
            return false;
        }

        return true;
    }

    public function ToClientRule(string $propName, string $displayName = ""): ?array
    {
        $displayName = $this->getDisplayName($propName, $displayName);
        return [
            'name' => 'required',
            'msgTemplate' => $this->errorMsg,
        ];
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