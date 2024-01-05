# daf
 php framework to build easy web apps (api, mvc, alpine js) !..release ..!


 # Base Use
### Example-1
```php
require_once "./vendor/autoloader.php";

$app = new Application();

// render functions
$app->router->get('/', function(){
    // code...
    return "<h1>Hello World.!!!</h1>";
});
  
$app->run();            
```

# Controller Use
- Project Root
  - app
    - controllers
        - HomeController.php
    - views
        - home
            - index.php
  - .htaccess
  - index.php

###### HomeController.php
```php
namespace App\Controllers;

use DafCore\Controller;
use DafCore\Controller\Attributes as a;
use DafCore\RequestBody;

#[a\HttpRoute]
class HomeController extends Controller {

    // GET: /Home
    #[a\HttpGet]
    public function index(){
        return $this->view("index");
    }
}         
```
###### index.php
```php
namespace App;
require_once "./vendor/autoloader.php";
use App\Controllers;

$app = new Application();

// render view
$app->router->addController(HomeController::class);
  
$app->run();            
```
