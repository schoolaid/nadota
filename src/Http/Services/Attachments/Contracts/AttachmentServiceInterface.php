<?php

namespace SchoolAid\Nadota\Http\Services\Attachments\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

interface AttachmentServiceInterface
{
    /**
     * Get attachable items for a field.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field $field
     * @return JsonResponse
     */
    public function getAttachableItems(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse;

    /**
     * Attach items to a relationship.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field $field
     * @return JsonResponse
     */
    public function attach(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse;

    /**
     * Detach items from a relationship.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field $field
     * @return JsonResponse
     */
    public function detach(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse;

    /**
     * Check if this service supports the sync operation.
     *
     * @return bool
     */
    public function supportsSync(): bool;

    /**
     * Sync items in a relationship (replace all).
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field $field
     * @return JsonResponse
     */
    public function sync(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse;
}
