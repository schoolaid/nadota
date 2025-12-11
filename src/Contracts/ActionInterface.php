<?php

namespace SchoolAid\Nadota\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

interface ActionInterface
{
    /**
     * Get the unique key/identifier for this action.
     */
    public static function getKey(): string;

    /**
     * Get the displayable name of the action.
     */
    public function name(): string;

    /**
     * Execute the action on the given models.
     *
     * @param Collection<int, Model> $models
     * @param NadotaRequest $request
     * @return mixed
     */
    public function handle(Collection $models, NadotaRequest $request): mixed;

    /**
     * Get the fields available for this action.
     */
    public function fields(NadotaRequest $request): array;

    /**
     * Determine if this action is available for the given request.
     */
    public function authorizedToRun(NadotaRequest $request, Model $model): bool;

    /**
     * Determine if this action should be available on the resource index.
     */
    public function showOnIndex(): bool;

    /**
     * Determine if this action should be available on the resource detail view.
     */
    public function showOnDetail(): bool;

    /**
     * Determine if this action is a destructive action.
     */
    public function isDestructive(): bool;

    /**
     * Get the confirmation text for the action.
     */
    public function confirmText(): ?string;

    /**
     * Get the confirm button text.
     */
    public function confirmButtonText(): string;

    /**
     * Get the cancel button text.
     */
    public function cancelButtonText(): string;

    /**
     * Convert the action to array representation.
     */
    public function toArray(NadotaRequest $request): array;
}