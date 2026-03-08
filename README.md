# DAF Framework (DafCore + DafDb + DafGlobals)

This project uses a custom PHP framework composed of 3 packages:

- `DafCore`: web app runtime (Application, Routing, Controllers, Views, Components, Forms, DI, Session/Flash/CSRF).
- `DafDb`: data layer (DbContext, DbSet, Queryable, attributes, migration generation/execution, SQL providers).
- `DafGlobals`: shared utilities (collections, paths, dates, object mapping).

This README is based on the actual source in:

- `vendor/DafCore`
- `vendor/DafDb`
- `vendor/DafGlobals`

## 1. How A DAF App Boots

Current app entrypoint (`index.php`):

```php
<?php
namespace App;
require_once "./Vendor/autoloader.php";

use App\Controllers\HomeController;

$app = new ApplicationEx();
$app->UseViews();
$app->Router->RegisterControllers([HomeController::class]);
$app->Run();
```

Runtime flow:

1. `Application` creates DI container and registers framework services.
2. `Router` resolves request path/method to a controller action.
3. If views are enabled (`UseViews()`), `ViewManager` renders through host/layout pipeline.
4. Route metadata is cached to `Vendor/storage/routes.cache.php`.

## 2. DafCore

## 2.1 Application and DI

`DafCore\Application` wires core services automatically:

- Request/Response: `IRequest`, `IResponse`
- Rendering: `IViewManager`
- Routing: `Router`
- Session/Flash/Form services

Useful APIs:

- `UseViews()`
- `Run()`
- `AddGlobalMiddleware(callable $callback)`
- `AddAntiForgeryToken()`

DI is reflection-based via `ServicesProvidor` and constructor injection.

## 2.2 Routing + Controllers (Attribute-based)

Controller classes are registered once and scanned lazily:

```php
$app->Router->RegisterControllers([
    App\Controllers\HomeController::class,
]);
```

Controller example:

```php
<?php
namespace App\Controllers;

use DafCore\Controllers\Controller;
use DafCore\Controllers\Attributes\Route;
use DafCore\Controllers\Attributes\HttpGet;

#[Route("/")]
class HomeController extends Controller
{
    #[HttpGet]
    public function Index(): string
    {
        return $this->Ok(App\Views\Pages\HomePage::class);
    }

    #[HttpGet("About")]
    public function About(): string
    {
        return $this->Ok(App\Views\Pages\AboutPage::class);
    }
}
```

### Route attributes

- Class: `#[Route(path, prefix)]`
- Method: `#[HttpGet]`, `#[HttpPost]`, `#[HttpPut]`, `#[HttpDelete]`
- Middleware-like attributes with `Handle(...)` are auto-injected into route pipeline.

Built-in middleware attributes include:

- `#[Layout("MainLayout")]`
- `#[AntiForgeryValidateToken("optional message")]`
- `#[Placeholder(ViewClass::class, delayMs)]`

### Route params

Parameterized routes use `:name` segments, e.g. `"/users/:id"`.

The value can be injected by parameter name in action/middleware callbacks.

## 2.3 Controller types

- `DafCore\Controllers\Controller`: returns rendered views (`Ok(view, params)`, `NotFound(view)`, etc.).
- `DafCore\Controllers\ApiController`: returns HTTP/text/json responses (`Ok($obj)`, `BadRequest()`, etc.).

## 2.4 View + Component system

DAF components are class+template pairs:

- Class: `Something.php` extends `DafCore\Component`
- Template: `Something.view.php`

Example (`App/Views/Components/Alert.php`):

```php
<?php
namespace App\Views\Components;

use DafCore\Component;

class Alert extends Component {
    public string $Message = "alert message";
}
```

Template (`Alert.view.php`):

```php
<?php /** @var App\Views\Components\Alert $this */ ?>
<p style="color:red;"><?=$this->Message?></p>
```

Used from another view:

```php
<App\Views\Components\Alert Message="Hello" />
```

### Important component conventions

- Uppercase parameters are treated as component parameters and auto-bind to public typed properties.
- Lowercase parameters are treated as raw HTML attributes.
- Child content is rendered with `$this->RenderChildContent()`.
- You can resolve services inside a component via `$this->Inject(Type::class)`.

### Built-in layout/routing components

Commonly used inside `Views/_Layouts/Host.view.php`:

- `<RouterView>` with `<Found>` and `<NotFound>`
- `<RouteView />`
- `<LayoutView>`
- `<PageView />`
- `<HeadOutlet />`, `<ScriptsOutlet />`
- `<DafJs />`

### Built-in utility components

- `<PageTitle>...</PageTitle>`
- `<NavLink href="...">...</NavLink>`
- `<Script>` (queues script to scripts outlet)
- `<FlashSummary />`
- `<AntiForgeryToken />`

## 2.5 Forms + validation

Core form components:

- `<Form ...>`
- `<ValidationSummary />`
- `<ValidationMessage For="FieldName" />`
- Inputs: `<TextInput>`, `<EmailInput>`, `<PasswordInput>`, `<NumberInput>`, `<Checkbox>`, `<TextArea>`, `<Input>`

Form example:

```php
<Form id="signup-form" Method="post" ClientSideValidation="true">
    <ValidationSummary class="alert alert-danger" />

    <TextInput For="Email" class="form-control" />
    <ValidationMessage For="Email" />

    <PasswordInput For="Password" class="form-control" />
    <ValidationMessage For="Password" />

    <button type="submit">Sign up</button>
</Form>
```

Validation attributes are in `DafCore\Attributes` (inside `Application.php`), including:

- `Required`, `NotEmpty`, `NotNull`, `OnlyEmpty`
- `Email`, `Url`, `Pattern`
- `Range`, `Length`, `In`
- `Json`, `JsonValidateClass`, `ArrayValidateClass`
- `DisplayName`

Server-side validation utility:

```php
if (!DafCore\Validator::Validate($dto)) {
    $errors = DafCore\Validator::GetErrors();
}
```

## 2.6 Request, Response, Session, Flash, CSRF

### Request (`DafCore\Request`)

- URL/method/query/headers/cookies/files access
- Body parsing supports JSON, form-urlencoded, multipart (`$_POST`), fallback parsing
- Body values are sanitized recursively for strings

### Response (`DafCore\Response`)

Fluent HTTP helpers:

- `Status`, `Header`, `Headers`
- `Send`, `Json`
- `Redirect`, `RedirectBack`
- `Ok`, `Created`, `NoContent`, `BadRequest`, `NotFound`, `Forbidden`, `Unauthorized`, `InternalError`

### Session (`DafCore\Session`)

- `Start`, `SetItem`, `TryGetItem`, `RemoveItem`, `Clear`, `Destroy`

### Flash

- Interfaces: `IFlashStore`, `IFlashMessages`
- Default store: session-backed (`SessionFlashStore`)
- Use `IFlashMessages` to add/get typed flash messages (`Ok`, `Warning`, `Error`)

### Anti-forgery

- `Application->AddAntiForgeryToken()` registers token generation middleware.
- Render hidden token in forms with `<AntiForgeryToken />`.
- Validate with `#[AntiForgeryValidateToken]` on endpoint.

## 3. DafDb

## 3.1 Core pieces

- `Context` (DB connection + tracker + query execution)
- `DbContext` (aggregates your typed `DbSet` properties)
- `DbSet` (table access + add/update/remove/clear)
- `Queryable` (LINQ-like query API)

## 3.2 Define model + DbSet + DbContext

```php
<?php
namespace App\Data\Models;

use DafCore\AutoConstruct;
use DafDb\Attributes\PrimaryKey;
use DafDb\Attributes\AutoIncrement;
use DafDb\Attributes\Unique;

class User extends AutoConstruct
{
    #[PrimaryKey]
    #[AutoIncrement]
    public int $Id;

    #[Unique]
    public string $Email;

    public string $Name;
}
```

```php
<?php
namespace App\Data\DbSets;

use DafDb\Query\DbSet;
use DafDb\Attributes\Table;
use App\Data\Models\User;

#[Table('users', User::class)]
class UserDbSet extends DbSet {}
```

```php
<?php
namespace App\Data;

use DafDb\Context\DbContext;
use DafDb\Context\MysqlContext;
use App\Data\DbSets\UserDbSet;

class AppDbContext extends DbContext
{
    public UserDbSet $Users;

    public function __construct()
    {
        parent::__construct(new MysqlContext('db_name', 'db_user', 'db_pass'));
    }
}
```

Notes:

- `DbSet` requires `#[Table(name, model)]`.
- Model metadata comes from public properties + attributes.
- Models are easiest when extending `DafCore\AutoConstruct`.

## 3.3 Query API

Typical usage:

```php
$users = $db->Users
    ->Where(fn($u) => $u->Id > 10 && str_contains($u->Email, '@'))
    ->OrderBy(fn($u) => $u->Name)
    ->Skip(0)
    ->Take(20)
    ->ToArray();

$one = $db->Users->FirstOrDefault(fn($u) => $u->Id == 1);
$count = $db->Users->Count(fn($u) => $u->Id > 0);
$exists = $db->Users->Any(fn($u) => $u->Email == 'admin@site.com');
```

Supported predicate helpers in `WhereParser` include:

- Comparisons: `==`, `!=`, `<`, `>`, `<=`, `>=`
- Logical: `&&`, `||`, `!`
- Functions: `str_contains`, `str_starts_with`, `str_ends_with`, `in_array`

Also supported:

- `Include(fn($x) => $x->Relation)`
- `ThenInclude(fn($r) => $r->SubRelation)`
- `RowToArray(true|false)`

## 3.4 Change tracking and SaveChanges

`DbSet` mutation calls are queued, then committed:

```php
$db->Users->Add(['Email' => 'a@b.com', 'Name' => 'A']);
$db->Users->Update(['Name' => 'New'], fn($u) => $u->Id == 1);
$db->Users->Remove(fn($u) => $u->Id == 2);

$db->SaveChanges();
```

## 3.5 Attributes for schema metadata

Available attributes in `DafDb\Attributes`:

- Class: `Table`
- Property: `PrimaryKey`, `AutoIncrement`, `Unique`, `MaxLength`, `DefaultValue`, `DefaultValueSql`, `DbIgnore`, `ForeignKey`, `DbInclude`

`ForeignKey` uses optional on-delete behaviors from `DafDb\OnDeleteAction`:

- `CASCADE`, `SET_NULL`, `RESTRICT`, `NO_ACTION`, `SET_DEFAULT`

## 3.6 Migrations

Facade class: `DafDb\Migrations\Migrations`

- `Generate(DbContext $dbContext, string $migrationName, string $appFolder)`
- `Migrate(DbContext $dbContext, string $appFolder)`
- `Rollback(DbContext $dbContext, string $appFolder)`

Expected folders under app root:

- `Migrations/*.php`
- `Migrations/Snapshots/*_Snapshot.php`

Minimal script example:

```php
<?php
require_once './Vendor/autoloader.php';

use DafDb\Migrations\Migrations;
use App\Data\AppDbContext;

$db = new AppDbContext();
$m = new Migrations();

$appFolder = __DIR__ . '/App';

$m->Generate($db, 'InitSchema', $appFolder);
$m->Migrate($db, $appFolder);
// $m->Rollback($db, $appFolder);
```

Generated migration classes extend `DafDb\Migrations\Migration` and implement `Up()` / `Down()` using `MigrationBuilder`.

## 3.7 JsonSet (file-backed set)

`DafDb\Query\JsonSet` gives queryable, filterable, mutable JSON-array storage with `SaveChanges()`.
Useful for lightweight file-based data without SQL.

## 4. DafGlobals

## 4.1 Collections

- `Collection` (mutable)
- `ReadOnlyCollection` (immutable)
- Common API via `ICollection`:
  - `Add`, `Remove`, `Clear`
  - `Where`, `Map`, `ForEach`, `Any`, `Count`
  - `FirstOrDefault`, `SingleOrDefault`
  - `Skip`, `Take`, `Reverse`, `FindKey`, `ToArray`

Example:

```php
use DafGlobals\Collections\Collection;

$c = new Collection([1,2,3,4]);
$evens = $c->Where(fn($x) => $x % 2 === 0)->ToArray();
```

## 4.2 Path helpers

`DafGlobals\IO\Path`:

- `Path::Combine(...$parts)`
- `Path::ResolveRelative($base, $relative)`

## 4.3 Date types

- `DafGlobals\Dates\DateOnly`
- `DafGlobals\Dates\DateTime`
- Shared interface: `IDate`

Examples:

```php
use DafGlobals\Dates\DateOnly;
use DafGlobals\Dates\DateTime;

$today = DateOnly::Today();
$nextWeek = $today->AddDays(7);

$now = DateTime::Now();
$later = $now->AddHours(2)->AddMinutes(30);
```

These types are recognized by `DafDb` model hydration when used as property types.

## 4.4 Object mapping

`DafGlobals\Mapper\ObjectMapper::Map($source, $target, $mapping = [])`

Supports rename + transform + getter/setter strategy flags.

## 5. Practical conventions for this codebase

- App root namespace is `App`.
- Views are component-based (`*.php` + `*.view.php`).
- Layout files are under `App/Views/_Layouts`.
- `ViewManager` default layout is `MainLayout`.
- Route cache is stored at `Vendor/storage/routes.cache.php`.
- Autoloader currently uses `./Vendor/autoloader.php` in `index.php` (case-sensitive environments may require matching folder case exactly).

## 6. Minimal end-to-end example

1. Create page component:

```php
// App/Views/Pages/ProfilePage.php
namespace App\Views\Pages;

use DafCore\Component;

class ProfilePage extends Component {
    public string $Title = 'Profile';
}
```

```php
<!-- App/Views/Pages/ProfilePage.view.php -->
<PageTitle><?=$this->Title?></PageTitle>
<h1><?=$this->Title?></h1>
```

2. Add controller route:

```php
#[HttpGet('Profile')]
public function Profile(): string {
    return $this->Ok(App\Views\Pages\ProfilePage::class);
}
```

3. Register controller in `index.php` and run.

## 7. Summary

DAF gives you:

- Attribute-driven routing/controllers and middleware (`DafCore`)
- Component-first server rendering with layout host pipeline (`DafCore`)
- Typed query + migration system with model attributes (`DafDb`)
- Small core utility layer for collections/dates/paths/mapping (`DafGlobals`)

For new features in this app, the fastest pattern is:

1. Add route method (attribute).
2. Return view component class from controller.
3. Build UI in `Component + .view.php`.
4. For data, define `Model + DbSet + DbContext`, then generate/migrate.
