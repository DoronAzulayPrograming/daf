<?php
namespace DafCore\Routing;

final class RouteMatchContext
{
    public bool $Found;
    public string $Path;
    public string $Method;
    public int $StatusCode = 200;
    public array $Pipeline = [];
    public mixed $Endpoint = null;
    public array $RouteParams = [];
    public array $EndPointMetadata = [];
    public mixed $NotFoundPayload = "404 - Route Not Found";

    public function __construct(string $method, string $path)
    {
        $this->Method = $method;
        $this->Path = $path;
    }
}
