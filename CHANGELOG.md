# Changelog

## [Unreleased]

### Added
- `BaseApiController` — abstract base with full CRUD, authorization, pagination, eager loading, validation, and resource transformation
- `$model` — declare the Eloquent model, get full CRUD instantly
- `$resource` — optional API Resource wrapping for all responses
- `$with` / `$withShow` — per-method eager loading control
- `$authorizedMethods` — granular policy authorization per method
- `$perPage` / `$maxPerPage` — pagination with client-controlled page size and abuse cap
- `$storeRequest` / `$updateRequest` — Form Request integration
- `storeRules()` / `updateRules()` — ad-hoc inline validation hooks
- `query()` — overridable base query hook for additional constraints
- `transform()` — internal resource transformation for single and collection responses
- Responses via `raditzfarhan/laravel-api-response` `response()->api()` macro
