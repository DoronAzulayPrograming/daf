<?php
namespace App;
require_once "./vendor/autoloader.php";

// $appContext = $app->Services->GetOne(AppContext::class);
// $migrations = new \DafDb\Migration();
// $migrations->Generate($appContext, "InitDb","App");


//require_once "buildDaf.php";

// $sqls = $appContext->getCreateTableSqls();
// $snapshot = $appContext->getModelSnapshot();

// Output JSON
// echo "<pre>";
// echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
// echo "</pre>";
//echo json_encode($sqls, JSON_PRETTY_PRINT);


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
