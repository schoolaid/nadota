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
    protected ?string $displayIcon = null;
    protected ResourceAuthorizationInterface $resourceAuthorization;

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
        return Str::plural(Str::title(Str::snake(class_basename(get_called_class()), ' ')));
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
}
