<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Pipeline\Pipeline;
use SchoolAid\Nadota\Contracts\ResourceIndexInterface;
use SchoolAid\Nadota\Http\DataTransferObjects\IndexRequestDTO;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Resources\Index\IndexResource;

class ResourceIndexService implements ResourceIndexInterface
{
    public function handle(NadotaRequest $request): IndexResource
    {
        $request->authorized('viewAny');
        $resource = $request->getResource();

        $pipes = [
            Pipes\BuildQueryPipe::class,
            Pipes\ApplySearchPipe::class,
            Pipes\ApplyFiltersPipe::class,
            Pipes\ApplySortingPipe::class,
            Pipes\PaginateAndTransformPipe::class,
        ];

        return app(Pipeline::class)
            ->send(new IndexRequestDTO(
                $request,
                $resource,
            ))
            ->through($pipes)
            ->then(function ($data) {
                return new IndexResource($data);
            });
    }
}
