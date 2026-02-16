# DAF Framework

DAF is a lightweight PHP framework focused on fast server-side development with a controller pipeline, dependency injection, and a component-first view system. It also includes DafDb (a high-level query layer with migrations) and DafGlobals (utility primitives).

This README is structured as official documentation for the framework.

## Table of Contents

- Overview
- Philosophy
- Requirements
- Project Layout
- Quick Start
- Core Concepts
  - Application and Routing
  - Controllers and Middleware
  - Dependency Injection
  - Requests and Responses
  - Validation
  - Sessions and Flash
  - Security (Anti-forgery)
- Views and Components
  - Component Markup
  - Layouts and Host Layout
  - Outlets (Head/Scripts)
  - DafJs Navigation
- DafDb (Data Layer)
  - Context and Repositories
  - Queryable API
  - Includes and Object Graphs
  - Change Tracking and SaveChanges
  - Transactions
  - Migrations and Snapshots
  - JSON Repository
- DafGlobals
  - Collections
  - Dates
  - Path Helpers
- Examples
- Notes and Conventions

## Overview

DAF provides a minimal but expressive framework for building PHP web sites and APIs. It emphasizes:

- Controller and pipeline-based routing
- First-class dependency injection
- A component-based view system with HTML-like tags
- A data layer that turns lambdas into SQL
- Schema migrations built from model metadata

DAF is split into three libraries:

- DafCore: application, routing, views, components, validation, session, and security
- DafDb: repository and query layer, migrations, and providers (SQLite/MySQL)
- DafGlobals: collections, dates, and path utilities

## Philosophy

- Keep the framework small and readable
- Use attributes and reflection to reduce boilerplate
- Make server-side rendering expressive with component tags
- Provide powerful data access without heavy ORM overhead

## Requirements

DAF targets modern PHP with attributes and typed properties. Ensure your PHP version supports attributes (PHP 8.0+).

## Project Layout

Typical app structure:

- `App/`
  - `Views/`
    - `_Layouts/`
    - `_Host.php` (optional)
    - `_GlobalUsing.php` (optional)
  - `Controllers/`
  - `Models/`
- `public/` (web root)
- `vendor/` (DAF libraries)

## Quick Start

A minimal entry point:

```php
<?php
use DafCore\Application;

$app = new Application(baseFolder: 'App');

$app->Get('/', function() {
    return "Hello DAF";
});

$app->Run();
```

A controller-based route:

```php
<?php
use DafCore\Controllers\Controller;
use DafCore\Controllers\Attributes\Route;
use DafCore\Controllers\Attributes\HttpGet;

#[Route('/products')]
class ProductsController extends Controller
{
    #[HttpGet('')]
    function Index() {
        return $this->RenderView('Products/Index');
    }
}
```

Register controllers:

```php
$app->RegisterControllers([
    ProductsController::class,
]);
```

## Core Concepts

### Application and Routing

DAF routes requests via a Router that supports direct routes and attribute-driven controllers. It builds a middleware pipeline and resolves method parameters through DI.

```php
$app->Get('/status', function() {
    return "ok";
});
```

### Controllers and Middleware

Controllers can return views or JSON. Middleware is implemented through attributes with a `Handle` method.

```php
use DafCore\Controllers\Attributes\Layout;
use DafCore\Controllers\Attributes\HttpGet;

#[Layout('MainLayout')]
#[HttpGet('')]
function Index() {
    return $this->RenderView('Home/Index');
}
```

### Dependency Injection

DAF uses a lightweight DI container. Dependencies are injected into controllers, middleware, and action parameters by type.

```php
function Index(\DafCore\Request $req, \DafCore\Response $res) {
    return $res->Ok(['path' => $req->GetUrlPath()]);
}
```

### Requests and Responses

`Request` exposes URL, query, body, headers, cookies, and uploaded files. `Response` provides helpers for HTML and JSON.

```php
$res->Ok(['ok' => true]);
$res->BadRequest('Invalid payload');
```

### Validation

Use attributes to annotate model properties and validate them. You can also validate DTOs automatically when used as action parameters.

```php
class CreateUser extends \DafCore\AutoConstruct
{
    #[\DafCore\Attributes\Required]
    public string $Email;
}
```

### Sessions and Flash

`Session` supports normal values and "flush" values cleared after render.

```php
$session->Set('user_id', 10);
$session->AddFlushMsg('Saved successfully');
```

### Security (Anti-forgery)

Use `AntiForgery` and the `<AntiForgeryToken>` component to emit and validate CSRF tokens.

```php
$app->AddAntiForgeryToken();
```

## Views and Components

DAF views are PHP files with component-style tags. Components are resolved from namespaces and rendered recursively.

### Component Markup

```php
<PageTitle>Products</PageTitle>
<NavLink href="/products" StartWith="true">All</NavLink>
```

Components can inject services and access parameters:

```php
<?php
/** @var DafCore\IComponent $this */
$req = $this->Inject(DafCore\Request::class);
$match = $this->Parameter('Match', 'bool') ?? true;
?>
```

### Layouts and Host Layout

`ViewManager` supports a layout and optional host layout:

- `App/Views/_Layouts/MainLayout.php`
- `App/Views/_Layouts/_Host.php` (optional)

### Outlets (Head/Scripts)

Outlets let components push content into layout sections:

```php
<PageTitle>Dashboard</PageTitle>
```

In the layout:

```php
<?php $headOutlet->RenderOutlet(); ?>
```

### DafJs Navigation

`<DafJs>` injects a client-side navigation helper that uses `morphdom` to update the page without full reloads. It also intercepts form submits by default.

## DafDb (Data Layer)

### Context and Repositories

Create a context for SQLite or MySQL. Repositories map models to tables via attributes.

```php
$ctx = new \DafDb\SqliteContext('storage/app.db');
```

Repository example:

```php
#[\DafDb\Attributes\Table('Users', \App\Models\User::class)]
class UsersRepository extends \DafDb\Repository {}
```

### Queryable API

Queryable provides fluent methods like `Where`, `OrderBy`, `Skip`, `Take`, `FirstOrDefault`, `ToArray`.

```php
$users = $ctx->Table(UsersRepository::class)
    ->Where(fn($u) => $u->IsActive == true)
    ->OrderByDescending(fn($u) => $u->Id)
    ->Take(20)
    ->ToArray();
```

### Includes and Object Graphs

Define relationships using `DbInclude` on model properties, then include them with `Include` and `ThenInclude`.

```php
$users = $ctx->Table(UsersRepository::class)
    ->Include(fn($u) => $u->Roles)
    ->ThenInclude(fn($r) => $r->Permissions)
    ->ToArray();
```

### Change Tracking and SaveChanges

Changes are queued and persisted in a single transaction:

```php
$user = $ctx->Table(UsersRepository::class)->FirstOrDefault(fn($u) => $u->Id == 1);
$user->Name = 'Updated';
$ctx->Table(UsersRepository::class)->Update($user);
$ctx->SaveChanges();
```

### Transactions

```php
$ctx->BigTransaction(function() use ($ctx) {
    // multiple operations
});
```

### Migrations and Snapshots

DAF generates migrations from model snapshots, with provider-specific SQL for SQLite and MySQL.

```php
$migrations = new \DafDb\Migrations\Migrations();
$migrations->Generate($dbContext, 'InitDb', 'App');
$migrations->Migrate($dbContext, 'App');
```

### JSON Repository

A file-backed repository with collection semantics:

```php
$repo = new \DafDb\JsonRepository('storage/users.json', [
    'model' => User::class,
    'auto_increment' => 'Id',
]);
```

## DafGlobals

### Collections

`Collection` and `ReadOnlyCollection` provide LINQ-style helpers such as `Map`, `Where`, `FirstOrDefault`, and `Count`.

### Dates

`DateOnly` and `DateTime` wrap immutable PHP dates and provide consistent formatting and arithmetic.

### Path Helpers

`Path::Combine` and `Path::ResolveRelative` normalize and resolve paths in a cross-platform way.

## Examples

Minimal view with layout:

```php
// App/Views/Home/Index.php
<PageTitle>Home</PageTitle>
<h1>Welcome</h1>
```

Layout:

```php
// App/Views/_Layouts/MainLayout.php
<!doctype html>
<html>
<head>
  <?php $headOutlet->RenderOutlet(); ?>
</head>
<body>
  <?=$this->RenderChildContent()?>
  <?php $scriptsOutlet->RenderOutlet(); ?>
</body>
</html>
```

## Notes and Conventions

- Component tags are detected by a custom parser; tags must begin with an uppercase letter.
- Attributes on controllers and actions can act as middleware.
- `AutoConstruct` models can be instantiated from arrays or parameter lists.
- Query lambdas are parsed from source; keep them simple and side-effect free.

---

If you want a more formal multi-page docs structure (Markdown folders, navigation, and dedicated sections for API reference), tell me your preferred doc tooling (plain Markdown, MkDocs, Docusaurus, etc.).
