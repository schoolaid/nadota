<?php

namespace SchoolAid\Nadota\Contracts;

use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

interface ResourceInterface
{
    public function fields(NadotaRequest $request);
    public function getQuery(NadotaRequest $request, Model $modelInstance = null);
    public function getPermissionsForResource(NadotaRequest $request, Model $resource): array;
    public function authorizedTo(NadotaRequest $request, string $action, $model = null): bool;
    public function title(): string;
    public function getKey(): string;
}
