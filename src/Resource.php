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
use SchoolAid\Nadota\Http\Traits\ResourceExportable;
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
        ResourceSearchable,
        ResourceExportable;

    public string $model;
    protected bool $usesSoftDeletes = false;
    protected ?string $title;
    protected ?string $displayIcon = null;
    protected ResourceAuthorizationInterface $resourceAuthorization;

    /**
     * Default sorting for index queries.
     * Format: ['field' => 'column_name', 'direction' => 'asc|desc']
     */
    protected array $defaultSort = [];

    /**
     * Polling interval in seconds for automatic data refresh.
     * Set to null to disable polling.
     */
    protected ?int $pollingInterval = null;

    /**
     * Whether to include the 'id' field in API responses.
     * Set to false to exclude the id from index/show responses.
     */
    protected bool $includeIdInResponse = true;

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

    /**
     * Show checkbox on each row for bulk selection
     */
    protected bool $showRowCheckbox = false;

    /**
     * Whether delete is globally enabled for this resource.
     * Set to false to disable delete for all records.
     */
    protected bool $canDelete = true;

    /**
     * Whether force delete is globally enabled for this resource.
     * Set to false to disable force delete for all records.
     */
    protected bool $canForceDelete = true;

    /**
     * Whether restore is globally enabled for this resource.
     * Set to false to disable restore for all records.
     */
    protected bool $canRestore = true;

    /**
     * Show select all checkbox in table header
     */
    protected bool $showSelectAll = false;

    /**
     * Custom Laravel Resource class for show response.
     * When set, bypasses the default Nadota response format.
     */
    protected ?string $showResponseResource = null;

    /**
     * Custom Laravel Resource class for edit response.
     * When set, bypasses the default Nadota response format.
     */
    protected ?string $editResponseResource = null;

    /**
     * Width of the detail card in show view.
     * Values: 'sm', 'md', 'lg', 'xl', '2xl', 'full', or custom CSS value (e.g., '800px', '75%')
     */
    protected ?string $detailCardWidth = null;

    /**
     * Whether the main card on index is collapsible
     */
    protected bool $mainCardCollapsible = true;

    /**
     * Whether the main card on index is collapsed by default
     */
    protected bool $mainCardDefaultCollapsed = true;

    /**
     * Custom title for the main card on index
     */
    protected ?string $mainCardTitle = null;

    public function __construct(
        ResourceAuthorizationInterface $resourceAuthorization = null
    )
    {
        $this->resourceAuthorization = $resourceAuthorization ?? app(ResourceAuthorizationInterface::class);
    }
    public function authorizedTo(NadotaRequest $request, string $action, $model = null, array $context = []): bool
    {
        return $this->resourceAuthorization
            ->setModel($model ?? $this->model)
            ->authorizedTo($request, $action, $context);
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

    /**
     * Format a model instance for options endpoint response.
     * Override this method to add custom fields to options.
     *
     * @param Model $model
     * @return array
     *
     * @example
     * ```php
     * public function optionsFormat(Model $model): array
     * {
     *     return [
     *         'value' => $model->id,
     *         'label' => $this->displayLabel($model),
     *         'email' => $model->email,
     *         'avatar' => $model->avatar_url,
     *         'status' => $model->status,
     *     ];
     * }
     * ```
     */
    public function optionsFormat(mixed $item): array
    {
        $keyAttribute = static::$attributeKey ?? 'id';

        // Handle array or stdClass (e.g., from raw queries or external APIs)
        if (is_array($item)) {
            return [
                'value' => $item['id'] ?? null,
                'label' => $item['label'] ?? null ,
            ];
        }

        if (!$item instanceof Model) {
            // stdClass or other object
            return [
                'value' => $item->{$keyAttribute} ?? $item->id ?? null,
                'label' => $item->label ?? $item->name ?? $item->title ?? $item->{$keyAttribute} ?? '',
            ];
        }

        // Eloquent Model
        return [
            'value' => $item->{$keyAttribute},
            'label' => $this->displayLabel($item),
        ];
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

        // Check delete permission (property + method + policy)
        $canDelete = $this->canDelete($resource, $request) && $this->authorizedTo($request, 'delete', $resource);

        // Check force delete permission (property + method + policy)
        $canForceDelete = $this->canForceDelete($resource, $request) && $this->authorizedTo($request, 'forceDelete', $resource);

        // Check restore permission (property + method + policy)
        $canRestore = $this->canRestore($resource, $request) && $this->authorizedTo($request, 'restore', $resource);

        return [
            'view' => $this->authorizedTo($request, 'view', $resource),
            'update' => $this->authorizedTo($request, 'update', $resource),
            'delete' => $canDelete,
            'forceDelete' => $canForceDelete && $hasSoftDelete,
            'restore' => $canRestore && $hasSoftDelete && $trashed,
            'attach' => $this->authorizedTo($request, 'attach', $resource),
            'detach' => $this->authorizedTo($request, 'detach', $resource),
            'fields' => $this->getFieldPermissions($request, $resource),
        ];
    }

    /**
     * Determine if a record can be deleted.
     * Override this method for custom logic per record.
     *
     * @param Model $model
     * @param NadotaRequest $request
     * @return bool
     */
    public function canDelete(Model $model, NadotaRequest $request): bool
    {
        return $this->canDelete;
    }

    /**
     * Determine if a record can be force deleted.
     * Override this method for custom logic per record.
     *
     * @param Model $model
     * @param NadotaRequest $request
     * @return bool
     */
    public function canForceDelete(Model $model, NadotaRequest $request): bool
    {
        return $this->canForceDelete;
    }

    /**
     * Determine if a record can be restored.
     * Override this method for custom logic per record.
     *
     * @param Model $model
     * @param NadotaRequest $request
     * @return bool
     */
    public function canRestore(Model $model, NadotaRequest $request): bool
    {
        return $this->canRestore;
    }

    /**
     * Get field-specific permissions for attachable fields.
     *
     * @param NadotaRequest $request
     * @param Model $resource
     * @return array
     */
    protected function getFieldPermissions(NadotaRequest $request, Model $resource): array
    {
        $permissions = [];

        $fields = $this->flattenFields($request);

        foreach ($fields as $field) {
            if (!method_exists($field, 'isAttachable') || !$field->isAttachable()) {
                continue;
            }

            $fieldKey = $field->key();

            $permissions[$fieldKey] = [
                'attach' => $this->authorizedTo($request, 'attach', $resource, ['field' => $fieldKey]),
                'detach' => $this->authorizedTo($request, 'detach', $resource, ['field' => $fieldKey]),
            ];
        }

        return $permissions;
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

    /**
     * Customize the query used when fetching options for this resource.
     *
     * This method is called when:
     * - Fetching options via GET /{resource}/resource/options
     * - Fetching relation field options via GET /{resource}/resource/field/{field}/options
     *
     * Override this method to add scopes, filters, or conditions specific to options.
     *
     * @param Builder $query The base query
     * @param NadotaRequest $request The current request
     * @param array $params Additional parameters:
     *                      - 'search': string - Search term
     *                      - 'limit': int - Max results (capped at 100)
     *                      - 'exclude': array - IDs to exclude
     *                      - 'orderBy': string|null - Order field
     *                      - 'orderDirection': string - 'asc' or 'desc'
     * @return Builder
     *
     * @example
     * ```php
     * // Only show active records in options
     * public function optionsQuery(Builder $query, NadotaRequest $request, array $params = []): Builder
     * {
     *     return $query->where('is_active', true);
     * }
     *
     * // Filter by tenant
     * public function optionsQuery(Builder $query, NadotaRequest $request, array $params = []): Builder
     * {
     *     return $query->where('tenant_id', $request->user()->tenant_id);
     * }
     * ```
     */
    public function optionsQuery(Builder $query, NadotaRequest $request, array $params = []): Builder
    {
        return $query;
    }

    /**
     * Provide a custom search implementation for options (e.g., Meilisearch, Algolia, Scout).
     *
     * Return null to use the default database LIKE search.
     * When returning a collection, the system will format results using displayLabel().
     *
     * @param NadotaRequest $request The current request
     * @param array $params Parameters:
     *                      - 'search': string - Search term
     *                      - 'limit': int - Max results (capped at 100)
     *                      - 'exclude': array - IDs to exclude
     *                      - 'orderBy': string|null - Order field
     *                      - 'orderDirection': string - 'asc' or 'desc'
     * @return \Illuminate\Support\Collection|array|null Return models collection, or null for default DB search
     *
     * @example
     * ```php
     * // Using Laravel Scout with Meilisearch
     * public function optionsSearch(NadotaRequest $request, array $params = []): Collection|array|null
     * {
     *     $search = $params['search'] ?? '';
     *     $limit = $params['limit'] ?? 15;
     *     $exclude = $params['exclude'] ?? [];
     *
     *     if (empty($search)) {
     *         return null; // Fall back to default database query
     *     }
     *
     *     $query = Student::search($search)->take($limit);
     *
     *     if (!empty($exclude)) {
     *         $query->whereNotIn('id', $exclude);
     *     }
     *
     *     return $query->get();
     * }
     * ```
     */
    public function optionsSearch(NadotaRequest $request, array $params = []): \Illuminate\Support\Collection|array|null
    {
        return null;
    }
    public function tools(NadotaRequest $request): array
    {
        return [];
    }

    /**
     * Build the URL to fetch action events for a model.
     * Override this method to customize the URL generation.
     */
    public function buildActionEventsUrl(Model $model): string
    {
        $prefix = config('nadota.api.prefix', 'nadota-api');
        $resourceKey = static::getKey();
        $modelId = $model->getKey();

        return "/{$prefix}/{$resourceKey}/resource/{$modelId}/action-events";
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
     * Get the custom Laravel Resource class for show response.
     */
    public function getShowResponseResource(): ?string
    {
        return $this->showResponseResource;
    }

    /**
     * Get the custom Laravel Resource class for edit response.
     */
    public function getEditResponseResource(): ?string
    {
        return $this->editResponseResource;
    }

    /**
     * Get the detail card width for show view.
     */
    public function getDetailCardWidth(): ?string
    {
        return $this->detailCardWidth;
    }

    /**
     * Check if the main card on index is collapsible.
     */
    public function isMainCardCollapsible(): bool
    {
        return $this->mainCardCollapsible;
    }

    /**
     * Check if the main card on index is collapsed by default.
     */
    public function isMainCardDefaultCollapsed(): bool
    {
        return $this->mainCardDefaultCollapsed;
    }

    /**
     * Get the custom title for the main card on index.
     */
    public function getMainCardTitle(): ?string
    {
        return $this->mainCardTitle;
    }

    /**
     * Get the main card configuration for index.
     */
    public function getMainCardConfig(): array
    {
        return [
            'collapsible' => $this->mainCardCollapsible,
            'defaultCollapsed' => $this->mainCardDefaultCollapsed,
            'title' => $this->mainCardTitle,
        ];
    }

    /**
     * Get the resource info array for API responses.
     * Used by both /info and /config endpoints to ensure consistency.
     *
     * @param \Illuminate\Http\Request|NadotaRequest $request
     */
    public function toInfoArray(\Illuminate\Http\Request $request): array
    {
        return [
            'key' => static::getKey(),
            'title' => $this->title(),
            'description' => $this->description(),
            'perPage' => $this->getPerPage(),
            'allowedPerPage' => $this->getAllowedPerPage(),
            'allowedSoftDeletes' => $this->getUseSoftDeletes(),
            'canCreate' => $this->canCreate ?? false,
            'components' => $this->getComponents(),
            'detailCardWidth' => $this->getDetailCardWidth(),
            'mainCard' => $this->getMainCardConfig(),
            'search' => [
                'key' => $this->getSearchKey(),
                'enabled' => $this->isSearchable(),
            ],
            'selection' => $this->getSelectionConfig(),
            'pollingInterval' => $this->getPollingInterval(),
        ];
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
     * Check if row checkbox should be shown
     */
    public function showRowCheckbox(): bool
    {
        return $this->showRowCheckbox;
    }

    /**
     * Check if select all checkbox should be shown
     */
    public function showSelectAll(): bool
    {
        return $this->showSelectAll;
    }

    /**
     * Get selection configuration
     */
    public function getSelectionConfig(): array
    {
        return [
            'showRowCheckbox' => $this->showRowCheckbox,
            'showSelectAll' => $this->showSelectAll,
        ];
    }

    /**
     * Get the default sort configuration
     */
    public function getDefaultSort(): array
    {
        return $this->defaultSort;
    }

    /**
     * Get the polling interval in seconds.
     * Returns null if polling is disabled.
     */
    public function getPollingInterval(): ?int
    {
        return $this->pollingInterval;
    }

    /**
     * Check if the 'id' field should be included in API responses.
     */
    public function shouldIncludeId(): bool
    {
        return $this->includeIdInResponse;
    }

    /**
     * Disable including the 'id' field in API responses.
     */
    public function withoutId(): static
    {
        $this->includeIdInResponse = false;
        return $this;
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
     * Hook called when delete fails and transaction is rolled back.
     * Use this to clean up any external resources or log the failure.
     *
     * @param Model $model The model that failed to delete
     * @param NadotaRequest $request The current request
     * @param \Exception $exception The exception that caused the failure
     */
    public function onDeleteFailed(Model $model, NadotaRequest $request, \Exception $exception): void
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

    /**
     * Hook called before storing a new resource.
     * Override this method to perform custom logic before creation.
     *
     * @param Model $model The model being created (not yet saved)
     * @param NadotaRequest $request The current request
     * @return void
     */
    public function beforeStore(Model $model, NadotaRequest $request): void
    {
        // Override in child resource if needed
    }

    /**
     * Hook called after successfully storing a new resource.
     * Override this method to perform custom logic after creation.
     *
     * @param Model $model The created model (with ID)
     * @param NadotaRequest $request The current request
     * @return void
     */
    public function afterStore(Model $model, NadotaRequest $request): void
    {
        // Override in child resource if needed
    }

    /**
     * Hook called before updating a resource.
     * Override this method to perform custom logic before update.
     *
     * @param Model $model The model being updated
     * @param NadotaRequest $request The current request
     * @return void
     */
    public function beforeUpdate(Model $model, NadotaRequest $request): void
    {
        // Override in child resource if needed
    }

    /**
     * Hook called after successfully updating a resource.
     * Override this method to perform custom logic after update.
     *
     * @param Model $model The updated model
     * @param NadotaRequest $request The current request
     * @param array $originalData The original attributes before update
     * @return void
     */
    public function afterUpdate(Model $model, NadotaRequest $request, array $originalData): void
    {
        // Override in child resource if needed
    }
}
