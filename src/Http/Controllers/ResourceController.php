<?php

namespace Said\Nadota\Http\Controllers;

use Illuminate\Routing\Controller;
use Said\Nadota\Contracts\ResourceIndexInterface;
use Said\Nadota\Contracts\ResourceCreateInterface;
use Said\Nadota\Contracts\ResourceStoreInterface;
use Said\Nadota\Contracts\ResourceShowInterface;
use Said\Nadota\Contracts\ResourceEditInterface;
use Said\Nadota\Contracts\ResourceUpdateInterface;
use Said\Nadota\Contracts\ResourceDestroyInterface;
use Said\Nadota\Http\Requests\NadotaRequest;

class ResourceController extends Controller
{
    public function __construct(
        protected readonly ResourceIndexInterface $indexService,
        protected readonly ResourceCreateInterface $createService,
        protected readonly ResourceStoreInterface $storeService,
        protected readonly ResourceShowInterface $showService,
        protected readonly ResourceEditInterface $editService,
        protected readonly ResourceUpdateInterface $updateService,
        protected readonly ResourceDestroyInterface $destroyService
    ) {
    }

    public function index(NadotaRequest $request)
    {
        return $this->indexService->handle($request);
    }

    public function create(NadotaRequest $request)
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
}
