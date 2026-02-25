<?php
namespace DafCore;

interface IResponse
{
    public function GetStatus(): int;

    public function Status(int $statusCode, ?string $reasonPhrase = null): self;
    public function Header(string $name, string $value): self;
    public function Headers(array $headers): self;

    // Return payload string (no echo, no reset)
    public function Send(?string $text = null, ?array $headers = null): string;
    public function Json(mixed $data, ?array $headers = null): string;

    // Return payload string (usually empty for redirect/no-content)
    public function Redirect(string $location = "", int $statusCode = 302): string;
    public function RedirectBack(int $statusCode = 302): string;

    public function Ok(mixed $obj = null, ?array $headers = null): string;
    public function Created(mixed $obj = null, ?array $headers = null): string;
    public function InternalError(?string $msg = null): string;
    public function NoContent(): string;
    public function BadRequest(?string $msg = null): string;
    public function NotFound(?string $msg = null): string;
    public function Forbidden(?string $msg = null): string;
    public function Unauthorized(?string $msg = null): string;
}

class Response implements IResponse
{
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_NO_CONTENT = 204;
    public const HTTP_FOUND = 302;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_INTERNAL_ERROR = 500;

    private int $statusCode = self::HTTP_OK;
    private string $reasonPhrase = 'OK';
    private array $headers = [];


    public function GetStatus(): int
    {
        return $this->statusCode;
    }

    public function Status(int $statusCode, ?string $reasonPhrase = null): self
    {
        $this->statusCode = $statusCode;
        $this->reasonPhrase = $reasonPhrase ?? self::ReasonPhraseFor($statusCode);

        http_response_code($this->statusCode);
        header(sprintf('HTTP/1.1 %d %s', $this->statusCode, $this->reasonPhrase), true, $this->statusCode);

        return $this;
    }

    public function Header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        header($name . ': ' . $value, true);
        return $this;
    }

    public function Headers(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->Header((string)$name, (string)$value);
        }
        return $this;
    }

    public function Send(?string $text = null, ?array $headers = null): string
    {
        if ($headers !== null) {
            $this->Headers($headers);
        } elseif (!isset($this->headers['Content-Type'])) {
            $this->Header('Content-Type', 'text/html; charset=utf-8');
        }

        return $text ?? '';
    }

    public function Json(mixed $data, ?array $headers = null): string
    {
        if ($headers !== null) {
            $this->Headers($headers);
        } elseif (!isset($this->headers['Content-Type'])) {
            $this->Header('Content-Type', 'application/json; charset=utf-8');
        }

        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
            return $json === false ? '' : $json;
        } catch (\Throwable $e) {
            $this->Status(self::HTTP_INTERNAL_ERROR);
            return '{"error":"JSON encode failed"}';
        }
    }

    public function Redirect(string $location = "", int $statusCode = self::HTTP_FOUND): string
    {
        $this->Status($statusCode);
        $this->Header('Location', $location);
        return '';
    }

    public function RedirectBack(int $statusCode = self::HTTP_FOUND): string
    {
        $location = $_SERVER['HTTP_REFERER'] ?? '/';
        return $this->Redirect($location, $statusCode);
    }

    public function Ok(mixed $obj = null, ?array $headers = null): string
    {
        $this->Status(self::HTTP_OK);

        if ($obj === null) {
            return $this->Send('', $headers);
        }

        return is_string($obj)
            ? $this->Send($obj, $headers)
            : $this->Json($obj, $headers);
    }

    public function Created(mixed $obj = null, ?array $headers = null): string
    {
        $this->Status(self::HTTP_CREATED);

        if ($obj === null) {
            return $this->Send('', $headers);
        }

        return is_string($obj)
            ? $this->Send($obj, $headers)
            : $this->Json($obj, $headers);
    }

    public function InternalError(?string $msg = null): string
    {
        return $this->Status(self::HTTP_INTERNAL_ERROR)->Send($msg ?? 'Internal Server Error');
    }

    public function NoContent(): string
    {
        return $this->Status(self::HTTP_NO_CONTENT)->Send('');
    }

    public function BadRequest(?string $msg = null): string
    {
        return $this->Status(self::HTTP_BAD_REQUEST)->Send($msg ?? 'Bad Request');
    }

    public function NotFound(?string $msg = null): string
    {
        return $this->Status(self::HTTP_NOT_FOUND)->Send($msg ?? 'Not Found');
    }

    public function Forbidden(?string $msg = null): string
    {
        return $this->Status(self::HTTP_FORBIDDEN)->Send($msg ?? 'Forbidden');
    }

    public function Unauthorized(?string $msg = null): string
    {
        return $this->Status(self::HTTP_UNAUTHORIZED)->Send($msg ?? 'Unauthorized');
    }

    private static function ReasonPhraseFor(int $statusCode): string
    {
        return match ($statusCode) {
            100 => 'Continue',
            101 => 'Switching Protocols',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            415 => 'Unsupported Media Type',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            default => 'Internal Server Error',
        };
    }
}