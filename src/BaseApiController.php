<?php

namespace Laraditz\Crudless;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

abstract class BaseApiController extends Controller
{
    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    /**
     * The Eloquent model class this controller manages.
     * When not set, it is guessed from the controller name (e.g. UserController → App\Models\User).
     *
     * @var string|null
     */
    protected ?string $model = null;

    /**
     * Optional API Resource class for response transformation.
     * When set, all responses are wrapped through this resource.
     *
     * @var string|null
     */
    protected ?string $resource = null;

    /**
     * Relationships to eager load on index().
     *
     * @var array
     */
    protected array $with = [];

    /**
     * Relationships to eager load on show().
     * Falls back to $with if empty.
     *
     * @var array
     */
    protected array $withShow = [];

    /**
     * Methods that require policy authorization.
     * Remove a method name to make it publicly accessible.
     *
     * @var array
     */
    protected array $authorizedMethods = ['index', 'show', 'store', 'update', 'destroy'];

    /**
     * Records per page. Set to null to disable pagination.
     *
     * @var int|null
     */
    protected ?int $perPage = null;

    /**
     * Maximum records per page the client can request via ?per_page=.
     *
     * @var int
     */
    protected int $maxPerPage = 100;

    /**
     * Optional Filter class for index() query filtering via laraditz/model-filter.
     * When set, the model must use the Filterable trait.
     *
     * @var string|null
     */
    protected ?string $filter = null;

    /**
     * Form Request class for store().
     * When declared, takes priority over storeRules().
     *
     * @var string|null
     */
    protected ?string $storeRequest = null;

    /**
     * Form Request class for update().
     * When declared, takes priority over updateRules().
     *
     * @var string|null
     */
    protected ?string $updateRequest = null;

    // -------------------------------------------------------------------------
    // Hooks — override in child as needed
    // -------------------------------------------------------------------------

    /**
     * Base query for index(). Override to chain additional constraints.
     */
    protected function query(): Builder
    {
        $query = $this->resolveModel()::query()->with($this->with);

        if ($this->filter) {
            $query->filter(request()->all(), $this->filter);
        } elseif (method_exists($this->resolveModel(), 'scopeFilter')) {
            $query->filter(request()->all());
        }

        return $query;
    }

    /**
     * Ad-hoc validation rules for store().
     * Used only when $storeRequest is not declared.
     *
     * @return array
     */
    protected function storeRules(): array
    {
        return [];
    }

    /**
     * Ad-hoc validation rules for update().
     * Used only when $updateRequest is not declared.
     *
     * @return array
     */
    protected function updateRules(): array
    {
        return [];
    }

    // Before hooks — throw an exception to abort the action.

    protected function beforeIndex(): void {}
    protected function beforeShow(mixed $record): void {}
    protected function beforeStore(array $data): void {}
    protected function beforeUpdate(mixed $record, array $data): void {}
    protected function beforeDestroy(mixed $record): void {}

    // After hooks — return a replacement value to override what is sent in the response,
    // or return null / nothing to keep the original.

    protected function afterIndex(mixed $data): mixed { return $data; }
    protected function afterShow(mixed $record): mixed { return $record; }
    protected function afterStore(mixed $record): mixed { return $record; }
    protected function afterUpdate(mixed $record): mixed { return $record; }
    protected function afterDestroy(): void {}

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    protected function resolveModel(): string
    {
        if ($this->model) {
            return $this->model;
        }

        // Guess from controller class name: App\Http\Controllers\UserController → User
        $basename = class_basename(static::class);
        $name = str_ends_with($basename, 'Controller')
            ? substr($basename, 0, -strlen('Controller'))
            : $basename;

        foreach (['App\\Models\\' . $name, 'App\\' . $name] as $candidate) {
            if (class_exists($candidate)) {
                return $this->model = $candidate;
            }
        }

        throw new \RuntimeException(
            'Could not resolve model for [' . static::class . ']. ' .
            'Set the $model property explicitly.'
        );
    }

    protected function authorizeAction(string $ability, mixed $target): void
    {
        if (in_array($ability, $this->authorizedMethods)) {
            $this->authorize($ability, $target);
        }
    }

    protected function resolveStoreData(Request $request): array
    {
        if ($this->storeRequest) {
            return app($this->storeRequest)->validated();
        }

        $rules = $this->storeRules();

        return $rules
            ? $request->validate($rules)
            : $request->all();
    }

    protected function resolveUpdateData(Request $request): array
    {
        if ($this->updateRequest) {
            return app($this->updateRequest)->validated();
        }

        $rules = $this->updateRules();

        return $rules
            ? $request->validate($rules)
            : $request->all();
    }

    protected function transform(mixed $data): mixed
    {
        if (! $this->resource) {
            return $data;
        }

        return is_iterable($data)
            ? $this->resource::collection($data)
            : new $this->resource($data);
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    public function index()
    {
        $this->authorizeAction('viewAny', $this->resolveModel());

        $this->beforeIndex();

        $perPage = $this->perPage
            ? min(request()->integer('per_page', $this->perPage), $this->maxPerPage)
            : null;

        if ($perPage) {
            $data = $this->transform($this->afterIndex($this->query()->paginate($perPage)));

            return response()->api()->collection($data);
        }

        $data = $this->transform($this->afterIndex($this->query()->get()));

        return response()->api()->data($data)->success();
    }

    public function show($id)
    {
        $record = $this->resolveModel()::query()
            ->with($this->withShow ?: $this->with)
            ->findOrFail($id);

        $this->authorizeAction('view', $record);

        $this->beforeShow($record);

        $record = $this->afterShow($record);

        return response()->api()->data($this->transform($record))->success();
    }

    public function store(Request $request)
    {
        $this->authorizeAction('create', $this->resolveModel());

        $data = $this->resolveStoreData($request);

        $this->beforeStore($data);

        $record = $this->resolveModel()::create($data);

        $record = $this->afterStore($record);

        return response()->api()->created($this->transform($record));
    }

    public function update(Request $request, $id)
    {
        $record = $this->resolveModel()::findOrFail($id);

        $this->authorizeAction('update', $record);

        $data = $this->resolveUpdateData($request);

        $this->beforeUpdate($record, $data);

        $record->update($data);

        $record = $this->afterUpdate($record);

        return response()->api()->data($this->transform($record))->success();
    }

    public function destroy($id)
    {
        $record = $this->resolveModel()::findOrFail($id);

        $this->authorizeAction('delete', $record);

        $this->beforeDestroy($record);

        $record->delete();

        $this->afterDestroy();

        return response()->api()->httpCode(204)->success();
    }
}
