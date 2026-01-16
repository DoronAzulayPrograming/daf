<?php
namespace DafCore;
interface IResponse
{
    function Reset();
    function Status(int $statusCode, string $reasonPhrase = null) : self;
    function Send(string $text = null, array $headers = null);
    function Json(mixed $data, array $headers = null);

    function Redirect(string $location = "");
    function RedirectBack();

    function Ok(mixed $obj = null, array $headers = null);
    function Created(mixed $obj = null, array $headers = null);
    function InternalError(string $msg = null);
    function NoContent();
    function BadRequest(string $msg = null);
    function NotFound(string $msg = null);
    function Forbidden(string $msg = null);
    function Unauthorized(string $msg = null);
}
class Response implements IResponse {
    private $statusCode;
    private $reasonPhrase;
    private $headers;
    private $body;

    public function __construct($statusCode = 200, $reasonPhrase = null) {
        $this->status($statusCode, $reasonPhrase);
        $this->headers = [];
        $this->body = '';
    }

    public function Reset() {
        $this->statusCode = 200;
        $this->reasonPhrase = null;
        $this->headers = [];
        $this->body = '';
    }

    public function Status($statusCode, $reasonPhrase = null) : self {
        $this->statusCode = $statusCode;
        $this->reasonPhrase = $reasonPhrase ? $reasonPhrase : $this->getHttpStatusReasonPhrase($statusCode);
        
        http_response_code($this->statusCode);
        header(sprintf('HTTP/1.1 %d %s', $this->statusCode, $this->reasonPhrase), true, $this->statusCode);
        return $this;
    }

    public function Send($text = null, array $headers = null) {
        $this->body = $text ?? ""; 
        
        if($headers === null)
            header("Content-Type: text/html");
        else $this->sendHeaders($headers);

        echo $this->body;

        $this->reset();
    }
    
    public function Json($data, array $headers = null) {
        $this->body = $this->json_stringify($data); 

        if(!isset($headers))
            header("Content-Type: application/json; charset=utf-8");
        else $this->sendHeaders($headers);

        echo $this->body;

        $this->reset();
    }

    
    private function sendHeaders(array $headers) {
        foreach ($headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }
    }

    function Redirect($location = ""){
        header("Location: $location");
        //exit();
    }
    
    function RedirectBack(){
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }

    const HTTP_OK = 200;
    const HTTP_CREATED = 201;
    const HTTP_NO_CONTENT = 204;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_FOUND = 302;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_NOT_FOUND = 404;
    const HTTP_FORBIDDEN = 403;
    const HTTP_INTERNAL_ERROR = 500;


    public function Ok($obj = null, array $headers = null){
        $this->Status(self::HTTP_OK);
        if(isset($obj))
        {
            if(is_string($obj))
                $this->Send($obj,$headers);
            else $this->Json($obj,$headers);
        }
        else $this->Send(null,$headers);
    }
    public function Created($obj = null, array $headers = null){
        $this->Status(self::HTTP_CREATED);
        if($obj)
        {
            if(!(is_array($obj) || is_object($obj))){
                $this->Send($obj,$headers);
            }
            else $this->Json($obj,$headers);
        }
        else $this->Send(null,$headers);
    }
    
    public function InternalError($msg = null){
        $this->Status(self::HTTP_INTERNAL_ERROR)->Send($msg);
    }
    
    public function NoContent(){
        $this->Status(self::HTTP_NO_CONTENT)->Send();
    }

    public function BadRequest($msg = null){
        $this->Status(self::HTTP_BAD_REQUEST)->Send($msg);
    }

    public function NotFound($msg = null){
        $this->Status(self::HTTP_NOT_FOUND)->Send($msg);
    }

    public function Forbidden($msg = null){
        $this->Status(self::HTTP_FORBIDDEN)->Send($msg);
    }

    public function Unauthorized($msg = null){
        $this->Status(self::HTTP_UNAUTHORIZED)->Send($msg);
    }


    
    private function json_stringify($obj){
        try
        {
            return json_encode($obj, JSON_THROW_ON_ERROR);
        }
        catch (\Throwable $e)
        {
            return "Throwable on json stringify: " . $e->getMessage() . PHP_EOL;
        }
    }
    private function getHttpStatusReasonPhrase($statusCode) {
        switch ($statusCode) {
            case 100:
                return 'Continue';
            case 101:
                return 'Switching Protocols';
            case 200:
                return 'OK';
            case 201:
                return 'Created';
            case 202:
                return 'Accepted';
            case 203:
                return 'Non-Authoritative Information';
            case 204:
                return 'No Content';
            case 205:
                return 'Reset Content';
            case 206:
                return 'Partial Content';
            case 300:
                return 'Multiple Choices';
            case 301:
                return 'Moved Permanently';
            case 302:
                return 'Found';
            case 303:
                return 'See Other';
            case 304:
                return 'Not Modified';
            case 305:
                return 'Use Proxy';
            case 307:
                return 'Temporary Redirect';
            case 400:
                return 'Bad Request';
            case 401:
                return 'Unauthorized';
            case 402:
                return 'Payment Required';
            case 403:
                return 'Forbidden';
            case 404:
                return 'Not Found';
            case 405:
                return 'Method Not Allowed';
            case 406:
                return 'Not Acceptable';
            case 407:
                return 'Proxy Authentication Required';
            case 408:
                return 'Request Timeout';
            case 409:
                return 'Conflict';
            case 410:
                return 'Gone';
            case 411:
                return 'Length Required';
            case 412:
                return 'Precondition Failed';
            case 413:
                return 'Request Entity Too Large';
            case 414:
                return 'Request-URI Too Long';
            case 415:
                return 'Unsupported Media Type';
            case 416:
                return 'Requested Range Not Satisfiable';
            case 417:
                return 'Expectation Failed';
            case 500:
                return 'Internal Server Error';
            case 501:
                return 'Not Implemented';
            case 502:
                return 'Bad Gateway';
            case 503:
                return 'Service Unavailable';
            case 504:
                return 'Gateway Timeout';
            case 505:
                return 'HTTP Version Not Supported';
            default:
                return 'Internal Server Error';
        }
    }
}