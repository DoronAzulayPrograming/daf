<?php
namespace DafCore;

class RequestBody extends \stdClass
{
    public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }
}

interface IRequest{
    function GetUrlPath() : string;
    function GetQueryParams() : array;
    function GetRouteParams() : array;
    function SetRouteParams(array $params) : void;
    function GetMethod() : string;
    function GetBody() : RequestBody;
    function GetBodyArray() : array;
    function GetHeaders() : array;
    public function GetCookies(): array;
    public function TryGetCookie(string $name , &$value) : bool;
}

class Request implements IRequest {
    private $data = [];
    private array $routeParameters = [];

    public function __set($name, $value) {
        $this->data[$name] = $value;
    }

    public function __get($name) {
        return $this->data[$name] ?? null;
    }

    function GetUrlPath() : string {
        $p = $_SERVER['REQUEST_URI'];

        $urlPath = trim(parse_url($p, PHP_URL_PATH), "/");

        return "/".$urlPath;
    }

    function GetQueryParams() : array {
        $queryParams = [];
        parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $queryParams);
        return $queryParams;
    }
    function GetRouteParams() : array {
        return $this->routeParameters;
    }
    function SetRouteParams(array $params) : void {
        $this->routeParameters = $params;
    }

    function GetMethod() : string {
        return $_SERVER['REQUEST_METHOD'];
    }

    function GetHeaders() : array {
        return apache_request_headers();
    }


    function GetBody() : RequestBody {
        $body = $this->GetBodyArray();
        return new RequestBody($body);
    }
    
    function GetBodyArray() : array {
        $params = [];
        if(!count($_POST)){
            $params = $this->getInputStream();
        }else{
            $params = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
        return $params ?? [];
    }

    public function GetCookies(): array
    {
        return $_COOKIE;
    }
    public function TryGetCookie(string $name , &$value) : bool
    {
        if(!isset($_COOKIE[$name])) return false;

        $value = $_COOKIE[$name];
        return true;
    }

    public function GetFiles(): array
    {
        return $_FILES ? array_values($_FILES) : [];
    }

    private function getInputStream() : array {
        $rawData = file_get_contents("php://input");
        return is_bool($rawData) || !strlen($rawData) ? [] : json_decode($rawData, true);
    }
    
    // public function parseUrlPath() : array {
    //     $path = $this->getUrlPath();
    //     return explode('/',filter_var(trim($path,'/'), FILTER_SANITIZE_URL));
    // }
}