<?php

namespace Said\Nadota\Http\Services;

use Illuminate\Pipeline\Pipeline;
use Said\Nadota\Contracts\ResourceIndexInterface;
use Said\Nadota\Http\DataTransferObjects\IndexRequestDTO;
use Said\Nadota\Http\Requests\NadotaRequest;
use Said\Nadota\Http\Resources\Index\IndexResource;

class ResourceIndexService implements ResourceIndexInterface
{
    public function handle(NadotaRequest $request): IndexResource
    {
        $request->authorized('viewAny');
        $resource = $request->getResource();

        $pipes = [
            Pipes\BuildQueryPipe::class,
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
