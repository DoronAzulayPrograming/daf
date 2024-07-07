<?php
namespace DafCore\Controllers;
use DafCore\IResponse;

abstract class BaseController {
    protected IResponse $response;

    function SetResponse(IResponse $response){
        $this->response = $response;
    }
}

namespace DafCore\Controllers\Attributes;
        
#[\Attribute(\Attribute::TARGET_CLASS)]
class Route {
    public function __construct(
        public string $Path = "",
        public string $Prefix = ""
    ){}
}

abstract class HttpAttribute {
    public function __construct(public string | null $Path = null) {}
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class HttpGet extends HttpAttribute {}

#[\Attribute(\Attribute::TARGET_METHOD)]
class HttpPut extends HttpAttribute {}

#[\Attribute(\Attribute::TARGET_METHOD)]
class HttpPost extends HttpAttribute {}

#[\Attribute(\Attribute::TARGET_METHOD)]
class HttpDelete extends HttpAttribute {}

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
class Layout {
    private string $name;
    function __construct(string $layoutName){
        $this->name = $layoutName;
    }

    function Handle(\DafCore\Controllers\Controller $c, callable $next){
        $c->SetLayout($this->name);
        $next();
    }
}
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
class AntiForgeryValidateToken{
    function __construct(private string $errorMsg = ""){}
    function Handle(\DafCore\Session $session, \DafCore\ViewManager $viewManager, \DafCore\Request $request, \DafCore\Response $response, callable $next){
        $session->Start();
        if($session->TryGetValue("CSRF-token", $token)){
            $req_parmas = $request->GetBodyArray();
            if(isset($req_parmas['csrft']) && $token === $req_parmas['csrft']){
                $next();
                return;
            }
        }

        $msg = empty($this->errorMsg) ? "Invalid CSRF token." : $this->errorMsg;
        $view = "";
        if(file_exists(\DafCore\Application::$BaseFolder . "/Views/_Errors/_400.php")){
            $view = "_Errors/_400";
        } else if(file_exists(\DafCore\Application::$BaseFolder . "/Views/_400.php")){
            $view = "_400";
        } else if(file_exists(\DafCore\Application::$BaseFolder . "/Views/_ErrorPage.php")){
            $view = "_ErrorPage";
        } else {
            $response->BadRequest($msg);
            return;
        }

        $response->Status(\DafCore\Response::HTTP_BAD_REQUEST);
        echo $viewManager->RenderView($view, ["Msg"=>$msg]);
    }
}
