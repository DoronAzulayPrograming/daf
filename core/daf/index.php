<?php
namespace App;
require_once "./vendor/autoloader.php";

use App\Models\User;
use DafDb\JsonRepository;
use App\Controllers\UsersController;
use App\Middlewares\CookieAuthentioaction;
use App\Middlewares\JwtAuthentioaction;

$app = new ApplicationEx();
($app->Services->GetOne(\DafCore\Session::class))->Start();

$app->AddAuthentioactionScheme("jwt", JwtAuthentioaction::class);
$app->AddAuthentioactionScheme("cookie", CookieAuthentioaction::class);
$app->Services->AddSingleton(JsonRepository::class, fn()=> new JsonRepository("users.json", ['model'=>User::class, 'auto_increment'=>'id']));

$app->AddController(UsersController::class);

$app->Get("/" , "Pages/Home");
$app->Get("/GetStarted" , "Pages/GetStarted");

$app->SetRouteBasePath("/Docs");
$app->Get("/", "Pages/Docs");
$app->Get("/AntiForgery", "Pages/Docs/Classes/AntiForgery");
$app->Get("/Application", "Pages/Docs/Classes/Application");
$app->Get("/ApplicationContext", "Pages/Docs/Classes/ApplicationContext");
$app->Get("/IDIContainer", "Pages/Docs/Interfaces/IDIContainer");
$app->Get("/DIContainer", "Pages/Docs/Classes/DIContainer");
$app->Get("/IRequest", "Pages/Docs/Interfaces/IRequest");
$app->Get("/Request", "Pages/Docs/Classes/Request");
$app->Get("/RequestBody", "Pages/Docs/Classes/RequestBody");
$app->Get("/IResponse", "Pages/Docs/Interfaces/IResponse");
$app->Get("/Response", "Pages/Docs/Classes/Response");
$app->Get("/Router", "Pages/Docs/Classes/Router");
$app->Get("/RouterMapMethods", "Pages/Docs/Traits/RouterMapMethods");
$app->Get("/IServicesProvidor", "Pages/Docs/Interfaces/IServicesProvidor");
$app->Get("/ServicesProvidor", "Pages/Docs/Classes/ServicesProvidor");
$app->Get("/ViewManager", "Pages/Docs/Classes/ViewManager");
$app->SetRouteBasePath("");


$app->MapIdentityRoutes();
$app->AddAntiForgeryToken();
$app->Run();

//require_once "buildDaf.php";




// require_once "./vendor/autoloader.php";

// use DafDb\Repository;
// use DafDb\SqliteContext;

// #[\DafDb\Attributes\Table(model: App\Models\User::class)]
// class UsersRepository extends Repository {

// }

// try {
//    //$context = new MySqlContext('dafTest', 'root', '');
//    $context = new SqliteContext('text.db');
//    $users_repo = new UsersRepository($context);
//    $u = new App\Models\User(null, "admin", "123", "Admin");
//    //$users_repo->Add($u);
   
//    echo "<pre>";
//    //$table = $context->Table(UsersRepository::class);
//    //$table->Clear();
//    //$table->Add($u);
//    //$table->Remove($table->FirstOrDefault());
//    $users_repo->Take(1);
//    var_dump($users_repo);
//    echo "</pre>";

// } catch (\Throwable $th) {
//    echo $th->getMessage();
// }






// require_once "./vendor/autoloader.php";

// use DafCore\AutoConstruct;
// use DafDb\Repository;
// use DafDb\SqliteContext;

// class Product extends AutoConstruct
// {
//    #[\DafDb\Attributes\PrimaryKey]
//    #[\DafDb\Attributes\AutoIncrement]
//    public int $Id;

//    #[\DafCore\Attributes\Required]
//    public string $Name;

//    #[\DafCore\Attributes\Required]
//    public float $Price;

//    #[\DafCore\Attributes\Required]
//    public string $Category;

// }

// #[\DafDb\Attributes\Table(model: Product::class)]
// class ProductsRepository extends Repository {

// }

// $context = new SqliteContext('text.db');
// $products = new ProductsRepository($context);


// // insert random products with random prices and random categories

// // $products->BigTransaction(function ($products) {
// //    /** @var ProductsRepository $products */
// //    $products->Add(new Product(null, "Nokia 205", rand(100, 500), "phone"));
// //    $products->Add(new Product(null, "Nokia 8210", rand(100, 400), "phone"));
// //    $products->Add(new Product(null, "MIRS", rand(100, 600), "phone"));
// //    $products->Add(new Product(null, "Motorola v70", rand(100, 700), "phone"));
// //    $products->Add(new Product(null, "Iphone 10", rand(600, 1200), "phone,smart"));
// //    $products->Add(new Product(null, "Iphone 15", rand(3600, 6200), "phone,smart"));
// //    $products->Add(new Product(null, "ROG phone 8", rand(1600, 2000), "phone,smart"));
// //    $products->Add(new Product(null, "Redmi 7", rand(300, 700), "phone,smart"));
// //    $products->Add(new Product(null, "Zenphone 11 Ultra", rand(2300, 2700), "phone,smart"));
// // });
