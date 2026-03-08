<?php

namespace DafCore\Controllers\Attributes;

use DafCore\IRequest;
use DafCore\IViewManager;
use DafCore\Views\ScriptsOutlet;
use DafGlobals\IO\Path;
        
#[\Attribute(\Attribute::TARGET_CLASS)]
class Route {
    public string $Path = "";
    public string $Prefix = "";
    public function __construct(string $path = "", string $prefix = ""){
        $this->Path = $path;
        $this->Prefix = $prefix;
    }
}

abstract class HttpAttribute {
    public ?string $Path = null;
    public function __construct(?string $path = null) {
        $this->Path = $path;
    }
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class HttpGet extends HttpAttribute {}

#[\Attribute(\Attribute::TARGET_METHOD)]
class HttpPut extends HttpAttribute {}

#[\Attribute(\Attribute::TARGET_METHOD)]
class HttpPost extends HttpAttribute {}

#[\Attribute(\Attribute::TARGET_METHOD)]
class HttpDelete extends HttpAttribute {}

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::TARGET_CLASS)]
class Layout {
    private string $name;
    function __construct(string $layoutName){
        $this->name = $layoutName;
    }

    function Handle(IViewManager $vm, callable $next){
        $vm->SetLayout($this->name);
        return $next();
    }
}
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
class AntiForgeryValidateToken{
    function __construct(private string $errorMsg = ""){}
    function Handle(\DafCore\Session $session, IViewManager $viewManager, \DafCore\Request $request, \DafCore\IResponse $response, callable $next){
        $session->Start();
        if($session->TryGetItem("CSRF-token", $token)){
            $req_parmas = $request->GetBodyArray();
            if(isset($req_parmas['csrft']) && $token === $req_parmas['csrft']){
                return $next();
            }
        }
        $status = \DafCore\Response::HTTP_BAD_REQUEST;
        $msg = empty($this->errorMsg) ? "Invalid CSRF token." : $this->errorMsg;
        $filePath = Path::Combine(\DafCore\Application::$BaseFolder, "Views", "_ErrorPage");
        $view = "";
        if(file_exists("$filePath.php") || file_exists("$filePath.view.php")){
            $view = $filePath;
        } else {
            return $response->BadRequest($msg);
        }

        $response->Status($status);
        return $viewManager->RenderView($view, ["Status"=>$status, "Message"=>$msg]);
    }
}

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
class Placeholder {
    function __construct(public string $viewName, public int $dellay = 100){ }

    private function getRetrunUrl(IRequest $req): string{
        $path = $req->GetUrlPath(); // e.g. "/products"
        $queryParams = $req->GetQueryParams(); // e.g. ["page" => "2", "sort" => "price"]

        $returnUrl = $path;
        if (!empty($queryParams)) {
            $returnUrl .= '?' . http_build_query($queryParams);
        }

        return $returnUrl;
    }

    function Handle(IRequest $req, ScriptsOutlet $scriptsOutlet, IViewManager $vm, callable $next): string {
        if(!empty($req->GetHeaders()['daf-placeholder'])){
            return $next();
        }
        $data = json_encode($req->GetBodyArray()) ?? null;
        if($data === "[]") $data = null;
        
        $scriptsOutlet->AddContent("<script>
        setTimeout(()=>{

            fetch('".$this->getRetrunUrl($req)."', {
                method: '".$req->GetMethod()."',
                headers: {
                    'Content-Type': 'application/json',
                    'daf-placeholder': 'just-placeholder'
                },
                ".(!empty($data) ? "data:'$data'" : "")."
            }).then(response => response.text())
            .then(text => {
                window.Daf.renderHtml(text)
            }).catch(error => {
                console.error(error);
            })

        },".$this->dellay." )
        </script>");

        return $vm->RenderView($this->viewName);
    }
}
