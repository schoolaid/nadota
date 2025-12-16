<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Contracts\ResourceStoreInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class ResourceStoreService extends AbstractResourcePersistService implements ResourceStoreInterface
{
    /**
     * {@inheritdoc}
     */
    protected function getModel(NadotaRequest $request, ResourceInterface $resource, $id): Model
    {
        return new $resource->model;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthorizationAction(): string
    {
        return 'create';
    }

    /**
     * {@inheritdoc}
     */
    protected function filterFields(ResourceInterface $resource, NadotaRequest $request): Collection
    {
        return $this->filterFieldsForStore($resource, $request);
    }

    /**
     * {@inheritdoc}
     */
    protected function callBeforeHook(ResourceInterface $resource, Model $model, NadotaRequest $request): void
    {
        if (method_exists($resource, 'beforeStore')) {
            $resource->beforeStore($model, $request);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function callAfterHook(
        ResourceInterface $resource,
        Model $model,
        NadotaRequest $request,
        ?array $originalData
    ): void {
        if (method_exists($resource, 'afterStore')) {
            $resource->afterStore($model, $request);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function trackAction(
        Model $model,
        NadotaRequest $request,
        array $validatedData,
        ?array $originalData
    ): void {
        $this->trackCreate($model, $request, $validatedData);
    }

    /**
     * {@inheritdoc}
     */
    protected function isUpdate(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function getSuccessMessage(): string
    {
        return 'Resource created successfully';
    }

    /**
     * {@inheritdoc}
     */
    protected function getSuccessStatusCode(): int
    {
        return 201;
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorMessage(): string
    {
        return 'Failed to create resource';
    }
}
