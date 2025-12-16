<?php

namespace SchoolAid\Nadota\Http\Services\Attachments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Relations\MorphToMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/**
 * Attachment service for MorphToMany relationships.
 *
 * Works the same as BelongsToMany but handles polymorphic pivot tables.
 * Laravel's morphToMany internally handles the morph type column.
 */
class MorphToManyAttachmentService extends BelongsToManyAttachmentService
{
    /**
     * Get attachable items for a MorphToMany field.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field|MorphToMany $field
     * @return JsonResponse
     */
    public function getAttachableItems(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        // MorphToMany works the same as BelongsToMany
        // Laravel handles the morph type internally
        return parent::getAttachableItems($request, $parentModel, $field);
    }

    /**
     * Attach items to a MorphToMany relationship.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field|MorphToMany $field
     * @return JsonResponse
     */
    public function attach(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        return parent::attach($request, $parentModel, $field);
    }

    /**
     * Detach items from a MorphToMany relationship.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field|MorphToMany $field
     * @return JsonResponse
     */
    public function detach(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        return parent::detach($request, $parentModel, $field);
    }

    /**
     * Sync items in a MorphToMany relationship.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field|MorphToMany $field
     * @return JsonResponse
     */
    public function sync(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        return parent::sync($request, $parentModel, $field);
    }
}
