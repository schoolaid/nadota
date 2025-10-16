<?php

namespace SchoolAid\Nadota\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\Attachments\HasManyAttachmentService;

class AttachmentController extends Controller
{
    public function __construct(
        private HasManyAttachmentService $hasManyService
    ) {}

    /**
     * Get attachable items for a field.
     */
    public function attachable(NadotaRequest $request, string $id, string $field): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();

        // Find the parent model
        $model = $resource->getQuery($request)->findOrFail($id);

        // Get the field
        $fields = collect($resource->fields($request));
        $fieldInstance = $fields->firstWhere(fn($f) => $f->key() === $field);

        if (!$fieldInstance) {
            return response()->json(['message' => 'Field not found'], 404);
        }

        if (!$fieldInstance->isAttachable()) {
            return response()->json(['message' => 'Field is not attachable'], 422);
        }

        // Get attachable items based on field type
        $relationType = $fieldInstance->getType();

        switch ($relationType) {
            case \SchoolAid\Nadota\Http\Fields\Enums\FieldType::HAS_MANY->value:
                return $this->hasManyService->getAttachableItems($request, $model, $fieldInstance);
            default:
                return response()->json(['message' => 'Attachment not supported for this field type: ' . $relationType], 422);
        }
    }

    /**
     * Attach items to a relationship.
     */
    public function attach(NadotaRequest $request, string $resourceKey, string $resourceId, string $field): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();

        // Find the parent model
        $model = $resource->getQuery($request)->findOrFail($resourceId);

        // Check permissions
        if (!$resource->authorizedTo($request, 'update', $model)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get the field
        $fields = collect($resource->fields($request));
        $fieldInstance = $fields->firstWhere(fn($f) => $f->key() === $field);

        if (!$fieldInstance) {
            return response()->json(['message' => 'Field not found'], 404);
        }

        if (!$fieldInstance->isAttachable()) {
            return response()->json(['message' => 'Field is not attachable'], 422);
        }

        // Attach based on a field type
        $relationType = $fieldInstance->getType();

        switch ($relationType) {
            case \SchoolAid\Nadota\Http\Fields\Enums\FieldType::HAS_MANY->value:
                return $this->hasManyService->attach($request, $model, $fieldInstance);
            default:
                return response()->json(['message' => 'Attachment not supported for this field type: ' . $relationType], 422);
        }
    }

    /**
     * Detach items from a relationship.
     */
    public function detach(NadotaRequest $request, string $resourceKey, string $id, string $field): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();

        // Find the parent model
        $model = $resource->getQuery($request)->findOrFail($id);

        // Check permissions
        if (!$resource->authorizedTo($request, 'update', $model)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get the field
        $fields = collect($resource->fields($request));
        $fieldInstance = $fields->firstWhere(fn($f) => $f->key() === $field);

        if (!$fieldInstance) {
            return response()->json(['message' => 'Field not found'], 404);
        }

        if (!$fieldInstance->isAttachable()) {
            return response()->json(['message' => 'Field is not attachable'], 422);
        }

        // Detach based on a field type
        $relationType = $fieldInstance->getType();

        switch ($relationType) {
            case \SchoolAid\Nadota\Http\Fields\Enums\FieldType::HAS_MANY->value:
                return $this->hasManyService->detach($request, $model, $fieldInstance);
            default:
                return response()->json(['message' => 'Detachment not supported for this field type: ' . $relationType], 422);
        }
    }
}