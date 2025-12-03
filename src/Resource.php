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
        VisibleWhen,
        InteractsWithFields,
        ResourceFrontUtils,
        ResourceRelatable,
        ResourceSearchable;

    public string $model;
    protected bool $usesSoftDeletes = false;
    protected ?string $title;
    protected ?string $displayIcon = null;
    protected ResourceAuthorizationInterface $resourceAuthorization;

    /**
     * Relations to an eager load on index queries
     */
    protected array $withOnIndex = [];

    /**
     * Relations to an eager load on show/detail queries
     */
    protected array $withOnShow = [];

    /**
     * Custom component names for different views
     */
    protected string $indexComponent = 'ResourceIndex';
    protected string $showComponent = 'ResourceShow';
    protected string $createComponent = 'ResourceCreate';
    protected string $updateComponent = 'ResourceUpdate';
    protected string $deleteComponent = 'ResourceDelete';
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
        return $this->title ?? Str::plural(Str::title(Str::snake(class_basename(get_called_class()), ' ')));
    }

    /**
     * Get the display label for a model instance.
     * Override this method to customize how models are displayed in relationships.
     *
     * @param Model $model
     * @return string
     */
    public function displayLabel(Model $model): string
    {
        // Try common attributes by default
        $commonAttributes = ['name', 'title', 'label', 'display_name', 'full_name', 'description'];

        foreach ($commonAttributes as $attr) {
            if (isset($model->{$attr}) && $model->{$attr} !== null) {
                return (string) $model->{$attr};
            }
        }

        // Fallback to primary key
        return (string) $model->getKey();
    }

    public static function getKey(): string
    {
        return Helpers::toUri(get_called_class());
    }
    public function displayIcon(): string|null
    {
        return $this->displayIcon;
    }
    public function getUseSoftDeletes(): bool
    {
        return $this->usesSoftDeletes;
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
            'attach' => $this->authorizedTo($request, 'attach', $resource),
            'detach' => $this->authorizedTo($request, 'detach', $resource),
        ];
    }
    public function usesSoftDeletes(): bool
    {
        return $this->usesSoftDeletes;
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
     * Get relations to an eager load on index
     */
    public function getWithOnIndex(): array
    {
        return $this->withOnIndex;
    }

    /**
     * Get relations to an eager load on show/detail
     */
    public function getWithOnShow(): array
    {
        return $this->withOnShow;
    }

    /**
     * Get the component names for all views
     */
    public function getComponents(): array
    {
        return [
            'index' => $this->indexComponent,
            'show' => $this->showComponent,
            'create' => $this->createComponent,
            'update' => $this->updateComponent,
            'delete' => $this->deleteComponent,
        ];
    }

    /**
     * Get the index component name
     */
    public function getIndexComponent(): string
    {
        return $this->indexComponent;
    }

    /**
     * Get the show component name
     */
    public function getShowComponent(): string
    {
        return $this->showComponent;
    }

    /**
     * Get the create component name
     */
    public function getCreateComponent(): string
    {
        return $this->createComponent;
    }

    /**
     * Get the update component name
     */
    public function getUpdateComponent(): string
    {
        return $this->updateComponent;
    }

    /**
     * Get the delete component name
     */
    public function getDeleteComponent(): string
    {
        return $this->deleteComponent;
    }

    /**
     * Set custom components for views
     */
    public function setComponents(array $components): static
    {
        if (isset($components['index'])) {
            $this->indexComponent = $components['index'];
        }
        if (isset($components['show'])) {
            $this->showComponent = $components['show'];
        }
        if (isset($components['create'])) {
            $this->createComponent = $components['create'];
        }
        if (isset($components['update'])) {
            $this->updateComponent = $components['update'];
        }
        if (isset($components['delete'])) {
            $this->deleteComponent = $components['delete'];
        }
        return $this;
    }

    /**
     * Hook called before deleting a resource
     * Override this method to perform custom logic before deletion
     */
    public function beforeDelete(Model $model, NadotaRequest $request): void
    {
        // Override in child resource if needed
    }

    /**
     * Perform the actual delete operation
     * Override this method to customize the delete behavior
     *
     * @return bool True if delete was successful, false otherwise
     */
    public function performDelete(Model $model, NadotaRequest $request): bool
    {
        if ($this->usesSoftDeletes()) {
            return $model->delete();
        }
        return $model->delete();
    }

    /**
     * Hook called after successfully deleting a resource
     * Override this method to perform custom logic after deletion
     */
    public function afterDelete(Model $model, NadotaRequest $request): void
    {
        // Override in child resource if needed
    }

    /**
     * Hook called before restoring a soft-deleted resource
     * Override this method to perform custom logic before restoration
     */
    public function beforeRestore(Model $model, NadotaRequest $request): void
    {
        // Override in child resource if needed
    }

    /**
     * Perform the actual restore operation
     * Override this method to customize the restore behavior
     *
     * @return bool True if restore was successful, false otherwise
     */
    public function performRestore(Model $model, NadotaRequest $request): bool
    {
        return $model->restore();
    }

    /**
     * Hook called after successfully restoring a resource
     * Override this method to perform custom logic after restoration
     */
    public function afterRestore(Model $model, NadotaRequest $request): void
    {
        // Override in child resource if needed
    }
}
