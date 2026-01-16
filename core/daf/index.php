<?php
// namespace App;
// require_once "./vendor/autoloader.php";

// use App\Models\User;
// use DafCore\IViewManager;
// use DafDb\Context;
// use DafDb\JsonRepository;
// use App\Controllers\UsersController;
// use App\Middlewares\CookieAuthentioaction;
// use App\Middlewares\JwtAuthentioaction;
// use DafDb\SqliteContext;

// date_default_timezone_set('Asia/Jerusalem');

// function setLayoutMiddlewere($layout){
//     return function (IViewManager $vm, $next) use ($layout){
//         $vm->SetLayout($layout);
//         return $next();
//     };
// }

// $app = new ApplicationEx();
// ($app->Services->GetOne(\DafCore\Session::class))->Start();
// $app->Services->AddSingleton(AppContext::class, fn()=> new AppContext(context: new SqliteContext("dafContext.db")));

// $app->AddAuthentioactionScheme("jwt", JwtAuthentioaction::class);
// $app->AddAuthentioactionScheme("cookie", CookieAuthentioaction::class);
// $app->Services->AddSingleton(JsonRepository::class, fn()=> new JsonRepository("users.json", ['model'=>User::class, 'auto_increment'=>'id']));

// $app->RegisterControllers([UsersController::class]);

// $app->Get("/" , "Pages/Home");
// $app->Get("/GetStarted" , "Pages/GetStarted");

// $app->SetRouteBasePath("/Docs");
// $app->Get("/", setLayoutMiddlewere("DocsLayout"), "Pages/Docs");
// $app->Get("/AntiForgery", setLayoutMiddlewere("DocsLayout"), "Pages/Docs/Classes/AntiForgery");
// $app->Get("/Application", setLayoutMiddlewere("DocsLayout"), "Pages/Docs/Classes/Application");
// $app->Get("/ApplicationContext", setLayoutMiddlewere("DocsLayout"), "Pages/Docs/Classes/ApplicationContext");
// $app->Get("/IDIContainer", setLayoutMiddlewere("DocsLayout"), "Pages/Docs/Interfaces/IDIContainer");
// $app->Get("/DIContainer", setLayoutMiddlewere("DocsLayout"), "Pages/Docs/Classes/DIContainer");
// $app->Get("/IRequest", setLayoutMiddlewere("DocsLayout"), "Pages/Docs/Interfaces/IRequest");
// $app->Get("/Request", setLayoutMiddlewere("DocsLayout"), "Pages/Docs/Classes/Request");
// $app->Get("/RequestBody", setLayoutMiddlewere("DocsLayout"), "Pages/Docs/Classes/RequestBody");
// $app->Get("/IResponse", setLayoutMiddlewere("DocsLayout"), "Pages/Docs/Interfaces/IResponse");
// $app->Get("/Response", setLayoutMiddlewere("DocsLayout"), "Pages/Docs/Classes/Response");
// $app->Get("/Router", setLayoutMiddlewere("DocsLayout"), "Pages/Docs/Classes/Router");
// $app->Get("/RouterMapMethods", setLayoutMiddlewere("DocsLayout"), "Pages/Docs/Traits/RouterMapMethods");
// $app->Get("/IServicesProvidor", setLayoutMiddlewere("DocsLayout"), "Pages/Docs/Interfaces/IServicesProvidor");
// $app->Get("/ServicesProvidor", setLayoutMiddlewere("DocsLayout"), "Pages/Docs/Classes/ServicesProvidor");
// $app->Get("/ViewManager", setLayoutMiddlewere("DocsLayout"), "Pages/Docs/Classes/ViewManager");
// $app->SetRouteBasePath("");


// $app->MapIdentityRoutes();
// $app->AddAntiForgeryToken();

// $app->ShowTimePerformance();
// $app->Run();


//require_once "buildDaf.php";

//echo "<pre>";
//Context::$Show_Queary = true;
///** @var \App\AppContext $appContext */
//$appContext = $app->Services->GetOne(AppContext::class);

// $arr = $appContext->Users
//     ->Include(fn($u) => $u->Roles)
//     ->ThenInclude(fn($ur) => $ur->Role)
//     ->Take(2);

// var_dump($arr);


//$migrations = new \DafDb\Migrations\Migrations();
//$migrations->Generate($appContext, "AddSocialProfiles","App"); //AddNullablePhone
//$migrations->Generate($appContext, "InitDb","App"); 
//$migrations->Migrate($appContext,"App");
//echo "</pre>";
//$migrations->Rollback($appContext,"App");