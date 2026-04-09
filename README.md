# Laravel Crudless

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Minimal base API controller for Laravel. Full CRUD with a single property declaration - no boilerplate, no repetition.

## Requirements

- PHP ^8.1
- Laravel 10, 11, or 12
- [`raditzfarhan/laravel-api-response`](https://github.com/raditzfarhan/laravel-api-response)

## Installation

```bash
composer require laraditz/crudless
```

## Basic Usage

Extend `BaseApiController`. If your controller follows the `{Model}Controller` naming convention, no configuration is needed:

```php
use Laraditz\Crudless\BaseApiController;

class UserController extends BaseApiController {}
```

Register routes:

```php
Route::apiResource('users', UserController::class);
```

That is all. `index`, `show`, `store`, `update`, `destroy` are fully operational.

`UserController` automatically resolves to `App\Models\User` (falling back to `App\User`). Set `$model` explicitly if your model lives elsewhere.

---

## Configuration Reference

Every property is optional. Declare only what the resource needs.

### Model

```php
protected ?string $model = null;
```

The Eloquent model this controller manages. Optional - when not declared, the model is guessed from the controller class name:

| Controller       | Resolved model                 |
| ---------------- | ------------------------------ |
| `UserController` | `App\Models\User` → `App\User` |
| `PostController` | `App\Models\Post` → `App\Post` |

The first class that exists wins. Set `$model` explicitly when the model lives outside these namespaces:

```php
protected string $model = \Domain\Blog\Models\Post::class;
```

---

### API Resource

```php
protected ?string $resource = null;
```

When set, all responses are wrapped through this resource class.

```php
class UserController extends BaseApiController
{
    protected string $model     = User::class;
    protected ?string $resource = UserResource::class;
}
```

Applies to `index`, `show`, `store`, and `update` responses.

---

### Eager Loading

```php
protected array $with     = [];   // used by index()
protected array $withShow = [];   // used by show() - falls back to $with if empty
```

```php
class PostController extends BaseApiController
{
    protected string $model    = Post::class;
    protected array $with      = ['author', 'category'];
    protected array $withShow  = ['author', 'category', 'tags', 'comments'];
}
```

`$withShow` lets `show()` load heavier relationships without affecting the list response.

---

### Authorization

```php
protected array $authorizedMethods = ['index', 'show', 'store', 'update', 'destroy'];
```

Lists which methods run a policy check. Laravel resolves the correct policy from the model automatically.

```php
// Only write operations require authorization
class PostController extends BaseApiController
{
    protected string $model            = Post::class;
    protected array $authorizedMethods = ['store', 'update', 'destroy'];
}

// No authorization at all
class PublicPostController extends BaseApiController
{
    protected string $model            = Post::class;
    protected array $authorizedMethods = [];
}
```

Default is all five methods - secure by default.

---

### Pagination

```php
protected ?int $perPage    = null;   // null = no pagination
protected int $maxPerPage  = 100;    // cap on client-requested page size
```

```php
class PostController extends BaseApiController
{
    protected string $model  = Post::class;
    protected ?int $perPage  = 15;
    protected int $maxPerPage = 50;
}
```

Client can override page size via `?per_page=25`. The `$maxPerPage` cap prevents abuse.

When paginated, `index()` returns `response()->api()->collection()` which includes `meta` and `links` automatically.

When not paginated, returns the full collection via `response()->api()->data()->success()`.

---

### Validation

Three levels available - use whichever fits:

**Level 1 - Form Request (recommended for complex rules):**

```php
protected ?string $storeRequest  = null;
protected ?string $updateRequest = null;
```

```php
class PostController extends BaseApiController
{
    protected string $model          = Post::class;
    protected ?string $storeRequest  = StorePostRequest::class;
    protected ?string $updateRequest = UpdatePostRequest::class;
}
```

**Level 2 - Ad-hoc rules (for simple cases):**

```php
class TagController extends BaseApiController
{
    protected string $model = Tag::class;

    protected function storeRules(): array
    {
        return ['name' => 'required|string|max:50|unique:tags'];
    }

    protected function updateRules(): array
    {
        return ['name' => 'required|string|max:50|unique:tags,name,' . request()->route('tag')];
    }
}
```

**Level 3 - No validation:**

Declare neither. Falls back to `$request->all()`.

**Priority:** `$storeRequest` → `storeRules()` → `$request->all()`

---

### Custom Query

Override `query()` to chain additional constraints on `index()`:

```php
class PostController extends BaseApiController
{
    protected string $model = Post::class;

    protected function query(): Builder
    {
        return $this->resolveModel()::query()
            ->with($this->with)
            ->where('published', true)
            ->latest();
    }
}
```

The base `query()` is simply `$this->resolveModel()::query()->with($this->with)` - zero cost when not overridden.

---

### Lifecycle Hooks

Override any hook to add behaviour without replacing the entire method.

**Before hooks** run before the main action. Throw an exception (e.g. `abort(403)`) to stop execution.

**After hooks** receive the result and must return it - return a modified value to change what is sent in the response.

| Hook            | Signature                            | When it runs                             |
| --------------- | ------------------------------------ | ---------------------------------------- |
| `beforeIndex`   | `(): void`                           | after auth, before query                 |
| `afterIndex`    | `(mixed $data): mixed`               | after query, before response             |
| `beforeShow`    | `(mixed $record): void`              | after auth, before response              |
| `afterShow`     | `(mixed $record): mixed`             | after `beforeShow`, before response      |
| `beforeStore`   | `(array $data): void`                | after auth + validation, before `create` |
| `afterStore`    | `(mixed $record): mixed`             | after `create`, before response          |
| `beforeUpdate`  | `(mixed $record, array $data): void` | after auth + validation, before `update` |
| `afterUpdate`   | `(mixed $record): mixed`             | after `update`, before response          |
| `beforeDestroy` | `(mixed $record): void`              | after auth, before `delete`              |
| `afterDestroy`  | `(): void`                           | after `delete`, before response          |

```php
class OrderController extends BaseApiController
{
    protected string $model = Order::class;

    // Send a notification after an order is created
    protected function afterStore(mixed $record): mixed
    {
        $record->notify(new OrderCreatedNotification());

        return $record;
    }

    // Prevent deletion of completed orders
    protected function beforeDestroy(mixed $record): void
    {
        abort_if($record->status === 'completed', 403, 'Completed orders cannot be deleted.');
    }
}
```

---

## Response Map

All responses go through [`raditzfarhan/laravel-api-response`](https://github.com/raditzfarhan/laravel-api-response) via the `response()->api()` macro.

| Method              | Response                                                             |
| ------------------- | -------------------------------------------------------------------- |
| `index` (paginated) | `response()->api()->collection($data)` - includes `meta` and `links` |
| `index` (full list) | `response()->api()->data($data)->success()`                          |
| `show`              | `response()->api()->data($record)->success()`                        |
| `store`             | `response()->api()->created($record)` - HTTP 201                     |
| `update`            | `response()->api()->data($record)->success()`                        |
| `destroy`           | `response()->api()->httpCode(204)->success()`                        |

---

## Full Example

A fully configured controller:

```php
class PostController extends BaseApiController
{
    protected string $model            = Post::class;
    protected ?string $resource        = PostResource::class;

    protected array $with              = ['author', 'category'];
    protected array $withShow          = ['author', 'category', 'tags', 'comments'];

    protected array $authorizedMethods = ['store', 'update', 'destroy'];

    protected ?int $perPage            = 15;
    protected int $maxPerPage          = 50;

    protected ?string $storeRequest    = StorePostRequest::class;
    protected ?string $updateRequest   = UpdatePostRequest::class;

    protected function query(): Builder
    {
        return $this->resolveModel()::query()
            ->with($this->with)
            ->where('published', true)
            ->latest();
    }
}
```

A minimal one - model resolved automatically from the class name:

```php
class TagController extends BaseApiController {}
```

---

## Extending Individual Methods

For most customisation, prefer lifecycle hooks - they keep the method intact and compose cleanly. When you need full control, any CRUD method can be overridden:

```php
class OrderController extends BaseApiController
{
    protected string $model = Order::class;

    public function store(Request $request)
    {
        $this->authorizeAction('create', $this->resolveModel());

        $data   = $this->resolveStoreData($request);
        $record = $this->resolveModel()::create($data);

        // full custom flow when hooks are not enough
        event(new OrderPlaced($record));

        return response()->api()->created($this->transform($record));
    }
}
```

Internal helpers `authorizeAction()`, `resolveStoreData()`, `resolveUpdateData()`, `resolveModel()`, and `transform()` are all `protected` and available in child classes.

---

## License

MIT
