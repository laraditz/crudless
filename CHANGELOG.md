# Changelog

## [1.2.2] - 2026-04-15

### Added
- Auth routes are now named — names are derived from the prefix (e.g., `auth.register`, `auth.login`, `auth.logout` for the default `auth` prefix)

## [1.2.1] - 2026-04-15

### Added
- `Crudless::authRoutes()` now accepts a `$middleware` parameter — apply middleware to the entire auth route group via `authRoutes(middleware: ['throttle:60,1'])`

## [1.2.0] - 2026-04-11

### Added
- `BaseAuthController` — register, login, logout via Laravel Sanctum with lifecycle hooks and validation extensibility
- `$userModel` — configurable user model, defaults to `App\Models\User`
- `$registerRequest` / `$loginRequest` — optional Form Request classes for auth validation
- `registerRules()` / `loginRules()` — overridable inline validation rules
- Lifecycle hooks: `beforeRegister`, `afterRegister`, `beforeLogin`, `afterLogin`, `beforeLogout`, `afterLogout`
- `Crudless::authRoutes($prefix, $controller, $except)` — one-line route registration with configurable prefix, controller, and route exclusions
- `CrudlessManager`, `CrudlessServiceProvider`, `Crudless` facade for service container integration
- `laravel/sanctum`, `illuminate/auth`, `illuminate/hashing` as required dependencies

## [1.1.1] - 2026-04-11

### Changed
- Bumped `laraditz/model-filter` requirement to `^2.0`

## [1.1.0] - 2026-04-10

### Added
- `$filter` — optional Filter class for `index()` query filtering via `laraditz/model-filter`
- `query()` now automatically applies `->filter(request()->all())` when the model uses the `Filterable` trait, or `->filter(request()->all(), $filter)` when `$filter` is declared explicitly
- Added `laraditz/model-filter` as a required dependency

## [1.0.1] - 2026-04-09

### Changed
- Added Laravel 13 support (`^13.0`) to `illuminate/routing`, `illuminate/database`, and `laravel/framework` version constraints

## [1.0.0] - 2026-04-09

### Added
- `BaseApiController` — abstract base with full CRUD, authorization, pagination, eager loading, validation, and resource transformation
- `$model` — optional Eloquent model declaration; automatically resolved from the controller class name when not set (`UserController` → `App\Models\User` → `App\User`)
- `$resource` — optional API Resource wrapping for all responses
- `$with` / `$withShow` — per-method eager loading control
- `$authorizedMethods` — granular policy authorization per method
- `$perPage` / `$maxPerPage` — pagination with client-controlled page size and abuse cap
- `$storeRequest` / `$updateRequest` — Form Request integration
- `storeRules()` / `updateRules()` — ad-hoc inline validation hooks
- `query()` — overridable base query hook for `index()`
- Lifecycle hooks — `beforeIndex`, `afterIndex`, `beforeShow`, `afterShow`, `beforeStore`, `afterStore`, `beforeUpdate`, `afterUpdate`, `beforeDestroy`, `afterDestroy`
- Responses via `raditzfarhan/laravel-api-response` `response()->api()` macro
