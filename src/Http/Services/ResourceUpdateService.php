<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Contracts\ResourceUpdateInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class ResourceUpdateService extends AbstractResourcePersistService implements ResourceUpdateInterface
{
    /**
     * Handle the update operation.
     *
     * Overrides parent to accept $id parameter as required by interface.
     *
     * @param NadotaRequest $request
     * @param mixed|null $id
     * @return JsonResponse
     */
    public function handle(NadotaRequest $request, mixed $id = null): JsonResponse
    {
        return parent::handle($request, $id);
    }

    /**
     * {@inheritdoc}
     */
    protected function getModel(NadotaRequest $request, ResourceInterface $resource, $id): Model
    {
        $query = $resource->getQuery($request);
        return $resource->queryIndex($request, $query)->findOrFail($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthorizationAction(): string
    {
        return 'update';
    }

    /**
     * {@inheritdoc}
     */
    protected function filterFields(ResourceInterface $resource, NadotaRequest $request): Collection
    {
        return $this->filterFieldsForUpdate($resource, $request);
    }

    /**
     * {@inheritdoc}
     */
    protected function callBeforeHook(ResourceInterface $resource, Model $model, NadotaRequest $request): void
    {
        if (method_exists($resource, 'beforeUpdate')) {
            $resource->beforeUpdate($model, $request);
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
        if (method_exists($resource, 'afterUpdate')) {
            $resource->afterUpdate($model, $request, $originalData ?? []);
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
        $this->trackUpdate($model, $request, $validatedData, $originalData);
    }

    /**
     * {@inheritdoc}
     */
    protected function isUpdate(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function getSuccessMessage(): string
    {
        return 'Resource updated successfully';
    }

    /**
     * {@inheritdoc}
     */
    protected function getSuccessStatusCode(): int
    {
        return 200;
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorMessage(): string
    {
        return 'Failed to update resource';
    }
}
