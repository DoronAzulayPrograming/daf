<?php
namespace App\Middlewares;
use App\AuthenticationSchemes;
use DafCore\ApplicationContext;
use DafCore\IServicesForCallback;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Auth{
    public function __construct(public string $roles = "", public string $scheme = 'cookie'){}

    function Handle(ApplicationContext $context, AuthenticationSchemes $authenticationSchemes, IServicesForCallback $sp, $next){
        
        $allowAnonimus = isset($context->endPointMetadata[AllowAnonymous::class]);
        if($allowAnonimus){
            $next();
            return;
        }

        //Add Authentication logic
        $auth_scheme = $authenticationSchemes->sceames[$this->scheme];
        $valid = $auth_scheme->Authentication(...$sp->getServicesForCallback([$auth_scheme, "Authentication"]));

        if(!$valid) return;

        //Add Authorization logic
        $temp = explode(",", $this->roles);
        $roles = [];

        foreach ($temp as $role) {
            if(!empty($role))
                $roles[$role] = 1;
        }
        
        $valid = $auth_scheme->Authorization(...[...$sp->getServicesForCallback([$auth_scheme, "Authorization"]), $roles]);

        if(!$valid) return;
        
        return $next();
    }
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class AllowAnonymous{}


