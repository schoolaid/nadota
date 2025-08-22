<?php

namespace SchoolAid\Nadota\Http\Controllers;

use Illuminate\Routing\Controller;
use SchoolAid\Nadota\Contracts\ResourceIndexInterface;
use SchoolAid\Nadota\Contracts\ResourceCreateInterface;
use SchoolAid\Nadota\Contracts\ResourceStoreInterface;
use SchoolAid\Nadota\Contracts\ResourceShowInterface;
use SchoolAid\Nadota\Contracts\ResourceEditInterface;
use SchoolAid\Nadota\Contracts\ResourceUpdateInterface;
use SchoolAid\Nadota\Contracts\ResourceDestroyInterface;
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
