<?php

namespace SchoolAid\Nadota;

use AllowDynamicProperties;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use SchoolAid\Nadota\Contracts\ResourceAuthorizationInterface;
use SchoolAid\Nadota\Http\Fields\Traits\InteractsWithFields;
use SchoolAid\Nadota\Http\Fields\Traits\ResourceFrontUtils;
use SchoolAid\Nadota\Http\Helpers\Helpers;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Traits\ResourceMenuOptions;
use SchoolAid\Nadota\Http\Traits\ResourcePagination;
use SchoolAid\Nadota\Http\Traits\ResourceRelatable;
use SchoolAid\Nadota\Http\Traits\ResourceSearchable;
use SchoolAid\Nadota\Http\Traits\VisibleWhen;

#[AllowDynamicProperties] abstract class Resource implements Contracts\ResourceInterface
{
    use ResourcePagination,
        ResourceMenuOptions,
        ResourceSearchable,
        VisibleWhen,
        InteractsWithFields,
        ResourceFrontUtils,
        ResourceRelatable;

    public string $model;
    public array $with = [];
    protected bool $softDelete = false;
    protected ResourceAuthorizationInterface $resourceAuthorization;
    public function __construct(
        ResourceAuthorizationInterface $resourceAuthorization = null
    )
    {
        $this->resourceAuthorization = $resourceAuthorization ?? app(ResourceAuthorizationInterface::class);
    }
    public function authorizedTo(NadotaRequest $request, string $action, $model = null): bool
    {
        return $this->resourceAuthorization
            ->setModel($model ?? $this->model)
            ->authorizedTo($request, $action);
    }
    public function title(): string
    {
        return Str::plural(Str::title(Str::snake(class_basename(get_called_class()), ' ')));
    }
    public static function getKey(): string
    {
        return Helpers::toUri(get_called_class());
    }
    abstract public function fields(NadotaRequest $request);
    public function getQuery(NadotaRequest $request, Model $modelInstance = null): Builder
    {
        if ($modelInstance) {
            return $modelInstance->newQuery();
        }

        return (new $this->model)->newQuery();
    }
    public function getPermissionsForResource(NadotaRequest $request, Model $resource): array
    {
        $hasSoftDelete = array_key_exists('deleted_at', $resource->getAttributes());
        $trashed = $resource->deleted_at !== null;

        $forceDelete = $this->authorizedTo($request, 'forceDelete', $resource);
        $restore = $this->authorizedTo($request, 'restore', $resource);

        return [
            'view' => $this->authorizedTo($request, 'view', $resource),
            'update' => $this->authorizedTo($request, 'update', $resource),
            'delete' => $this->authorizedTo($request, 'delete', $resource),
            'forceDelete' => $forceDelete && $hasSoftDelete,
            'restore' => $restore && $hasSoftDelete && $trashed,
        ];
    }
    public function getUseSoftDeletes(): bool
    {
        return $this->softDelete;
    }
    public function actions(NadotaRequest $request): array
    {
        return [];
    }
    public function filters(NadotaRequest $request): array
    {
        return [];
    }
    public function queryIndex(NadotaRequest $request, $query)
    {
        return $query;
    }
    public function tools(NadotaRequest $request): array
    {
        return [];
    }

    /**
     * Perform custom delete logic for the resource.
     * Override this method to customize delete behavior.
     *
     * @param Model $model
     * @param NadotaRequest $request
     * @return bool
     */
    public function performDelete(Model $model, NadotaRequest $request): bool
    {
        return $model->delete();
    }

    /**
     * Perform custom force delete logic for the resource.
     * Override this method to customize force delete behavior.
     *
     * @param Model $model
     * @param NadotaRequest $request
     * @return bool
     */
    public function performForceDelete(Model $model, NadotaRequest $request): bool
    {
        return $model->forceDelete();
    }

    /**
     * Perform custom restore logic for the resource.
     * Override this method to customize restore behavior.
     *
     * @param Model $model
     * @param NadotaRequest $request
     * @return bool
     */
    public function performRestore(Model $model, NadotaRequest $request): bool
    {
        return $model->restore();
    }

    /**
     * Hook called before deleting a resource.
     * Override to add custom logic before deletion.
     *
     * @param Model $model
     * @param NadotaRequest $request
     * @return void
     */
    public function beforeDelete(Model $model, NadotaRequest $request): void
    {
        // Override in child resources to add custom logic
    }

    /**
     * Hook called after deleting a resource.
     * Override to add custom logic after deletion.
     *
     * @param Model $model
     * @param NadotaRequest $request
     * @return void
     */
    public function afterDelete(Model $model, NadotaRequest $request): void
    {
        // Override in child resources to add custom logic
    }

    /**
     * Hook called before force deleting a resource.
     *
     * @param Model $model
     * @param NadotaRequest $request
     * @return void
     */
    public function beforeForceDelete(Model $model, NadotaRequest $request): void
    {
        // Override in child resources to add custom logic
    }

    /**
     * Hook called after force deleting a resource.
     *
     * @param Model $model
     * @param NadotaRequest $request
     * @return void
     */
    public function afterForceDelete(Model $model, NadotaRequest $request): void
    {
        // Override in child resources to add custom logic
    }

    /**
     * Hook called before restoring a resource.
     *
     * @param Model $model
     * @param NadotaRequest $request
     * @return void
     */
    public function beforeRestore(Model $model, NadotaRequest $request): void
    {
        // Override in child resources to add custom logic
    }

    /**
     * Hook called after restoring a resource.
     *
     * @param Model $model
     * @param NadotaRequest $request
     * @return void
     */
    public function afterRestore(Model $model, NadotaRequest $request): void
    {
        // Override in child resources to add custom logic
    }
}
