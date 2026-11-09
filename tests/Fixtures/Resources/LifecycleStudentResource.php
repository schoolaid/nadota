<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use SchoolAid\Nadota\Http\Fields\Email;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Http\Fields\Toggle;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * Route key: "lifecycle-student".
 *
 * A small Student resource that overrides every record lifecycle hook so the
 * HTTP tests can observe their order and inject failures.
 *
 * Observing: bind an ArrayObject as "lifecycle-hooks.log" in the container;
 * every hook appends ['hook', 'name', 'exists', 'trashed', 'original_name'].
 *
 * Failing: set config('lifecycle-hooks.fail') = ['hook' => <hook>, 'with' => <kind>]
 *   exception  -> RuntimeException
 *   validation -> ValidationException on "name"
 *   abort403   -> abort(403)
 *   error      -> \Error (not an \Exception)
 *   false      -> perform* hooks return false
 *
 * Writing inside a hook: set config('lifecycle-hooks.write_note_in') = <hook>
 * and the hook creates an fx_notes row, to prove the write is rolled back.
 *
 * Both the binding and the config live in the per-test application, so
 * nothing leaks between tests.
 *
 * Fields: name (required), email (unique, ignoring the record itself through
 * the :id placeholder), age (default 7), status (Select, default "draft"),
 * is_active (Toggle), website (readonly), favorite_color (disabled).
 */
class LifecycleStudentResource extends Resource
{
    public string $model = Student::class;

    protected array $searchableAttributes = ['name'];

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')->required()->sortable(),
            Email::make('Email', 'email')->rules(['nullable', 'email', 'unique:fx_students,email,:id']),
            Number::make('Age', 'age')->default(7),
            Select::make('Status', 'status')->options(StudentResource::STATUSES)->default('draft'),
            Toggle::make('Active', 'is_active'),
            Input::make('Website', 'website')->readonly(),
            Input::make('Favorite Color', 'favorite_color')->disabled(),
        ];
    }

    public function beforeStore(Model $model, NadotaRequest $request): void
    {
        $this->hook('beforeStore', $model);
    }

    public function afterStore(Model $model, NadotaRequest $request): void
    {
        $this->hook('afterStore', $model);
    }

    public function beforeUpdate(Model $model, NadotaRequest $request): void
    {
        $this->hook('beforeUpdate', $model);
    }

    public function afterUpdate(Model $model, NadotaRequest $request, array $originalData): void
    {
        $this->hook('afterUpdate', $model, $originalData);
    }

    public function beforeDelete(Model $model, NadotaRequest $request): void
    {
        $this->hook('beforeDelete', $model);
    }

    public function performDelete(Model $model, NadotaRequest $request): bool
    {
        return $this->hook('performDelete', $model) && parent::performDelete($model, $request);
    }

    public function afterDelete(Model $model, NadotaRequest $request): void
    {
        $this->hook('afterDelete', $model);
    }

    public function onDeleteFailed(Model $model, NadotaRequest $request, \Exception $exception): void
    {
        $this->log('onDeleteFailed', $model, ['exception' => $exception->getMessage()]);
    }

    public function beforeRestore(Model $model, NadotaRequest $request): void
    {
        $this->hook('beforeRestore', $model);
    }

    public function performRestore(Model $model, NadotaRequest $request): bool
    {
        return $this->hook('performRestore', $model) && parent::performRestore($model, $request);
    }

    public function afterRestore(Model $model, NadotaRequest $request): void
    {
        $this->hook('afterRestore', $model);
    }

    public function beforeForceDelete(Model $model, NadotaRequest $request): void
    {
        $this->hook('beforeForceDelete', $model);
    }

    public function performForceDelete(Model $model, NadotaRequest $request): bool
    {
        return $this->hook('performForceDelete', $model) && parent::performForceDelete($model, $request);
    }

    public function afterForceDelete(Model $model, NadotaRequest $request): void
    {
        $this->hook('afterForceDelete', $model);
    }

    /**
     * Log the hook, then apply the configured write / failure. Returns false
     * when a perform* hook was told to report failure.
     */
    protected function hook(string $hook, Model $model, ?array $originalData = null): bool
    {
        $this->log($hook, $model, $originalData === null ? [] : ['original_name' => $originalData['name'] ?? null]);

        if (config('lifecycle-hooks.write_note_in') === $hook) {
            Note::create(['body' => "written in {$hook}"]);
        }

        $fail = config('lifecycle-hooks.fail');

        if (! is_array($fail) || ($fail['hook'] ?? null) !== $hook) {
            return true;
        }

        return match ($fail['with'] ?? 'exception') {
            'validation' => throw ValidationException::withMessages(['name' => "{$hook} rejected"]),
            'abort403' => abort(403, "{$hook} forbidden"),
            'error' => throw new \Error("{$hook} crashed"),
            'false' => false,
            default => throw new \RuntimeException("{$hook} failed"),
        };
    }

    protected function log(string $hook, Model $model, array $extra = []): void
    {
        if (! app()->bound('lifecycle-hooks.log')) {
            return;
        }

        app('lifecycle-hooks.log')->append([
            'hook' => $hook,
            'name' => $model->getAttribute('name'),
            'exists' => $model->exists,
            'trashed' => method_exists($model, 'trashed') ? $model->trashed() : false,
        ] + $extra);
    }
}
