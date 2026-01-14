<?php

namespace SchoolAid\Nadota\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\Attachments\BelongsToManyAttachmentService;
use SchoolAid\Nadota\Http\Services\Attachments\Contracts\AttachmentServiceInterface;
use SchoolAid\Nadota\Http\Services\Attachments\HasManyAttachmentService;
use SchoolAid\Nadota\Http\Services\Attachments\MorphManyAttachmentService;
use SchoolAid\Nadota\Http\Services\Attachments\MorphToManyAttachmentService;

class AttachmentController extends Controller
{
    public function __construct(
        private HasManyAttachmentService $hasManyService,
        private BelongsToManyAttachmentService $belongsToManyService,
        private MorphToManyAttachmentService $morphToManyService,
        private MorphManyAttachmentService $morphManyService
    ) {}

    /**
     * Get attachable items for a field.
     */
    public function attachable(NadotaRequest $request, string $resourceKey, string $id, string $field): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();

        // Find the parent model
        $model = $resource->getQuery($request)->findOrFail($id);

        // Get the field
        $fieldInstance = $this->findField($request, $resource, $field);

        if (!$fieldInstance) {
            return response()->json(['success' => false, 'message' => 'Field not found'], 404);
        }

        if (!$fieldInstance->isAttachable()) {
            return response()->json(['success' => false, 'message' => 'Field is not attachable'], 422);
        }

        // Get the service for this field type
        $service = $this->getServiceForField($fieldInstance);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not supported for this field type: ' . $fieldInstance->getType()
            ], 422);
        }

        return $service->getAttachableItems($request, $model, $fieldInstance);
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

        // Get the field first to pass in authorization context
        $fieldInstance = $this->findField($request, $resource, $field);

        if (!$fieldInstance) {
            return response()->json(['success' => false, 'message' => 'Field not found'], 404);
        }

        // Check permissions with field context
        $authContext = [
            'field' => $field,
            'items' => $request->get('items', []),
            'pivot' => $request->get('pivot', []),
        ];

        if (!$resource->authorizedTo($request, 'attach', $model, $authContext)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (!$fieldInstance->isAttachable()) {
            return response()->json(['success' => false, 'message' => 'Field is not attachable'], 422);
        }

        // Get the service for this field type
        $service = $this->getServiceForField($fieldInstance);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not supported for this field type: ' . $fieldInstance->getType()
            ], 422);
        }

        return $service->attach($request, $model, $fieldInstance);
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

        // Get the field first to pass in authorization context
        $fieldInstance = $this->findField($request, $resource, $field);

        if (!$fieldInstance) {
            return response()->json(['success' => false, 'message' => 'Field not found'], 404);
        }

        // Check permissions with field context
        $authContext = [
            'field' => $field,
            'items' => $request->get('items', []),
        ];

        if (!$resource->authorizedTo($request, 'detach', $model, $authContext)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (!$fieldInstance->isAttachable()) {
            return response()->json(['success' => false, 'message' => 'Field is not attachable'], 422);
        }

        // Get the service for this field type
        $service = $this->getServiceForField($fieldInstance);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Detachment not supported for this field type: ' . $fieldInstance->getType()
            ], 422);
        }

        return $service->detach($request, $model, $fieldInstance);
    }

    /**
     * Sync items in a relationship (replace all).
     */
    public function sync(NadotaRequest $request, string $resourceKey, string $id, string $field): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();

        // Find the parent model
        $model = $resource->getQuery($request)->findOrFail($id);

        // Get the field first to pass in authorization context
        $fieldInstance = $this->findField($request, $resource, $field);

        if (!$fieldInstance) {
            return response()->json(['success' => false, 'message' => 'Field not found'], 404);
        }

        // Check permissions (use attach permission for sync) with field context
        $authContext = [
            'field' => $field,
            'items' => $request->get('items', []),
            'pivot' => $request->get('pivot', []),
            'detaching' => $request->get('detaching', true),
        ];

        if (!$resource->authorizedTo($request, 'attach', $model, $authContext)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (!$fieldInstance->isAttachable()) {
            return response()->json(['success' => false, 'message' => 'Field is not attachable'], 422);
        }

        // Get the service for this field type
        $service = $this->getServiceForField($fieldInstance);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Sync not supported for this field type: ' . $fieldInstance->getType()
            ], 422);
        }

        if (!$service->supportsSync()) {
            return response()->json([
                'success' => false,
                'message' => 'Sync operation not supported for this relation type'
            ], 422);
        }

        return $service->sync($request, $model, $fieldInstance);
    }

    /**
     * Find a field by name in the resource.
     */
    protected function findField(NadotaRequest $request, $resource, string $fieldName)
    {
        $fields = $resource->flattenFields($request);

        // Try by key first
        $field = $fields->firstWhere(fn($f) => $f->key() === $fieldName);

        // Try by relation name
        if (!$field) {
            $field = $fields->first(function ($f) use ($fieldName) {
                return method_exists($f, 'getRelation') && $f->getRelation() === $fieldName;
            });
        }

        return $field;
    }

    /**
     * Get the appropriate service for a field type.
     */
    protected function getServiceForField($field): ?AttachmentServiceInterface
    {
        $type = $field->getType();

        return match ($type) {
            FieldType::HAS_MANY->value => $this->hasManyService,
            FieldType::BELONGS_TO_MANY->value => $this->belongsToManyService,
            FieldType::MORPH_TO_MANY->value => $this->morphToManyService,
            FieldType::MORPH_MANY->value => $this->morphManyService,
            // MorphedByMany uses the same service as MorphToMany
            'morphedByMany' => $this->morphToManyService,
            default => null,
        };
    }
}
