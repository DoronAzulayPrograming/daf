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
    function GetRealUrl() : string;
    function GetUrlPath() : string;
    function GetQueryParams() : array;
    function GetRouteParams() : array;
    function SetRouteParams(array $params) : void;
    function GetMethod() : string;
    function GetBody() : RequestBody;
    function GetBodyArray() : array;
    public function GetFiles(): array;
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

    function GetRealUrl() : string {
        return $_SERVER['REQUEST_URI'];
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

    public function GetBodyArray() : array {
        // 1) JSON body (API)
        $ct = $this->GetContentType();
        if ($ct && str_contains($ct, 'application/json')) {
            $json = $this->getInputStream();
            return is_array($json) ? $this->sanitizeDeep($json) : [];
        }

        // 2) Multipart/form-data (files) or x-www-form-urlencoded (classic form)
        // Use $_POST to preserve nested arrays: LoginModel[Address][Name]
        if (!empty($_POST)) {
            return $this->sanitizeDeep($_POST);
        }

        // 3) Fallback for PUT/PATCH/DELETE with urlencoded (some clients send this)
        // If it's not JSON and there is a body, parse it like query string.
        $raw = file_get_contents("php://input");
        if (!is_string($raw) || $raw === '') return [];

        $ct = $ct ?? '';
        if (str_contains($ct, 'application/x-www-form-urlencoded')) {
            $out = [];
            parse_str($raw, $out);
            return $this->sanitizeDeep($out);
        }

        // 4) If unknown content-type, try JSON decode, else empty
        $maybe = json_decode($raw, true);
        if (is_array($maybe)) return $this->sanitizeDeep($maybe);

        return [];
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

    private function GetContentType(): ?string
    {
        // be defensive across servers
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? null;
        return $ct ? strtolower(trim(explode(';', $ct)[0])) : null;
    }

    private function sanitizeDeep($value)
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $k => $v) {
                // keep keys stable
                $clean[$k] = $this->sanitizeDeep($v);
            }
            return $clean;
        }

        // Only sanitize strings. Leave numbers/bools/null as-is.
        if (is_string($value)) {
            // Similar intent to FILTER_SANITIZE_FULL_SPECIAL_CHARS
            return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return $value;
    }
}