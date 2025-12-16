<?php

namespace SchoolAid\Nadota\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use SchoolAid\Nadota\Contracts\ResourceIndexInterface;
use SchoolAid\Nadota\Contracts\ResourceCreateInterface;
use SchoolAid\Nadota\Contracts\ResourceStoreInterface;
use SchoolAid\Nadota\Contracts\ResourceShowInterface;
use SchoolAid\Nadota\Contracts\ResourceEditInterface;
use SchoolAid\Nadota\Contracts\ResourceUpdateInterface;
use SchoolAid\Nadota\Contracts\ResourceDestroyInterface;
use SchoolAid\Nadota\Contracts\ResourceForceDeleteInterface;
use SchoolAid\Nadota\Contracts\ResourceRestoreInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class ResourceController extends Controller
{
    public function __construct(
        protected readonly ResourceIndexInterface $indexService,
        protected readonly ResourceCreateInterface $createService,
        protected readonly ResourceStoreInterface $storeService,
        protected readonly ResourceShowInterface $showService,
        protected readonly ResourceEditInterface $editService,
        protected readonly ResourceUpdateInterface $updateService,
        protected readonly ResourceDestroyInterface $destroyService,
        protected readonly ResourceForceDeleteInterface $forceDeleteService,
        protected readonly ResourceRestoreInterface $restoreService
    ) {
    }

    public function index(NadotaRequest $request)
    {
        return $this->indexService->handle($request);
    }

    public function create(NadotaRequest $request): \Illuminate\Http\JsonResponse
    {
        return $this->createService->handle($request);
    }

    public function store(NadotaRequest $request)
    {
        return $this->storeService->handle($request);
    }

    public function show(NadotaRequest $request, $resource, $id)
    {
        return $this->showService->handle($request, $id);
    }

    public function edit(NadotaRequest $request, $resource, $id)
    {
        return $this->editService->handle($request, $id);
    }

    public function update(NadotaRequest $request, $resource, $id)
    {
        return $this->updateService->handle($request, $id);
    }

    public function destroy(NadotaRequest $request, $resource, $id)
    {
        return $this->destroyService->handle($request, $id);
    }

    public function forceDelete(NadotaRequest $request, $resource, $id)
    {
        return $this->forceDeleteService->handle($request, $id);
    }

    public function restore(NadotaRequest $request, $resource, $id)
    {
        return $this->restoreService->handle($request, $id);
    }

    /**
     * Get permissions and URLs for a specific resource record.
     */
    public function permissions(NadotaRequest $request, string $resourceKey, int $id): JsonResponse
    {
        $resource = $request->getResource();
        $model = $resource->getQuery($request)->findOrFail($id);

        $request->authorized('view', $model);

        $permissions = $resource->getPermissionsForResource($request, $model);
        $prefix = config('nadota.api.prefix', 'nadota-api');

        $urls = [
            'show' => $permissions['view'] ? "/{$prefix}/{$resourceKey}/resource/{$id}" : null,
            'edit' => $permissions['update'] ? "/{$prefix}/{$resourceKey}/resource/{$id}/edit" : null,
            'update' => $permissions['update'] ? "/{$prefix}/{$resourceKey}/resource/{$id}" : null,
            'delete' => $permissions['delete'] ? "/{$prefix}/{$resourceKey}/resource/{$id}" : null,
            'forceDelete' => $permissions['forceDelete'] ? "/{$prefix}/{$resourceKey}/resource/{$id}/force" : null,
            'restore' => $permissions['restore'] ? "/{$prefix}/{$resourceKey}/resource/{$id}/restore" : null,
            'actionEvents' => $permissions['view'] ? $resource->buildActionEventsUrl($model) : null,
        ];

        return response()->json([
            'data' => [
                'id' => $model->getKey(),
                'permissions' => $permissions,
                'urls' => $urls,
            ],
        ]);
    }
}
