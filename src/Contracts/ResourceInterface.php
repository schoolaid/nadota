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
    public static function getKey(): string;

    // Lifecycle hooks for delete operations
    public function beforeDelete(Model $model, NadotaRequest $request): void;
    public function performDelete(Model $model, NadotaRequest $request): bool;
    public function afterDelete(Model $model, NadotaRequest $request): void;

    // Lifecycle hooks for restore operations
    public function beforeRestore(Model $model, NadotaRequest $request): void;
    public function performRestore(Model $model, NadotaRequest $request): bool;
    public function afterRestore(Model $model, NadotaRequest $request): void;
}
