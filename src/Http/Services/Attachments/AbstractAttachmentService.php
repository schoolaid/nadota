<?php

namespace SchoolAid\Nadota\Http\Services\Attachments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\Attachments\Contracts\AttachmentServiceInterface;
use SchoolAid\Nadota\Http\Services\FieldOptions\OptionsConfig;

abstract class AbstractAttachmentService implements AttachmentServiceInterface
{
    /**
     * Maximum items per page for attachable queries.
     */
    protected int $defaultPerPage = 25;

    /**
     * Maximum per page limit.
     */
    protected int $maxPerPage = 100;

    /**
     * Get a label for an attachable item.
     *
     * @param Model $item
     * @param Field $field
     * @return string
     */
    protected function getItemLabel(Model $item, Field $field): string
    {
        // Try field's resolveDisplay first
        if (method_exists($field, 'resolveDisplay')) {
            $label = $field->resolveDisplay($item);
            if ($label !== null && $label !== '') {
                return (string) $label;
            }
        }

        // Try resource's displayLabel
        if ($field->getResource()) {
            $resourceClass = $field->getResource();
            $resource = new $resourceClass;

            if (method_exists($resource, 'displayLabel')) {
                return $resource->displayLabel($item);
            }
        }

        // Try common display attributes
        $displayAttributes = OptionsConfig::FALLBACK_LABEL_ATTRIBUTES;

        foreach ($displayAttributes as $attr) {
            if (isset($item->{$attr}) && $item->{$attr} !== null) {
                return (string) $item->{$attr};
            }
        }

        // Fallback to ID
        return "Item #{$item->getKey()}";
    }

    /**
     * Get searchable fields for the related resource.
     *
     * @param Field $field
     * @return array
     */
    protected function getSearchableFields(Field $field): array
    {
        // Try attachable search fields first
        if (method_exists($field, 'getAttachableSearchFields')) {
            $fields = $field->getAttachableSearchFields();
            if (!empty($fields) && $fields !== ['id']) {
                return $fields;
            }
        }

        // Try resource's searchable attributes
        if ($field->getResource()) {
            $resourceClass = $field->getResource();
            $resource = new $resourceClass;

            if (method_exists($resource, 'getSearchableAttributes')) {
                $searchable = $resource->getSearchableAttributes();
                if (!empty($searchable)) {
                    return $searchable;
                }
            }
        }

        // Fallback
        return OptionsConfig::FALLBACK_SEARCH_ATTRIBUTES;
    }

    /**
     * Apply search to query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @param array $searchFields
     * @return void
     */
    protected function applySearch($query, string $search, array $searchFields): void
    {
        $query->where(function ($q) use ($searchFields, $search) {
            foreach ($searchFields as $searchField) {
                // Handle relation.attribute format
                if (str_contains($searchField, '.')) {
                    $parts = explode('.', $searchField);
                    $relation = implode('.', array_slice($parts, 0, -1));
                    $attribute = end($parts);

                    $q->orWhereHas($relation, function ($relationQuery) use ($attribute, $search) {
                        $relationQuery->where($attribute, 'like', '%' . $search . '%');
                    });
                } else {
                    $q->orWhere($searchField, 'like', '%' . $search . '%');
                }
            }
        });
    }

    /**
     * Get pagination parameters from request.
     *
     * @param NadotaRequest $request
     * @return array
     */
    protected function getPaginationParams(NadotaRequest $request): array
    {
        $perPage = (int) $request->get('per_page', $this->defaultPerPage);
        $perPage = min($perPage, $this->maxPerPage);

        return [
            'per_page' => $perPage,
            'page' => (int) $request->get('page', 1),
            'search' => $request->get('search', ''),
        ];
    }

    /**
     * Validate items array from request.
     *
     * @param NadotaRequest $request
     * @return array|null Returns null if validation fails
     */
    protected function getItemsFromRequest(NadotaRequest $request): ?array
    {
        $items = $request->get('items', []);

        if (!is_array($items)) {
            return null;
        }

        // Filter to valid IDs
        return array_filter($items, fn($id) => is_numeric($id) || is_string($id));
    }

    /**
     * Get pivot data from request.
     *
     * @param NadotaRequest $request
     * @return array
     */
    protected function getPivotDataFromRequest(NadotaRequest $request): array
    {
        $pivot = $request->get('pivot', []);

        return is_array($pivot) ? $pivot : [];
    }

    /**
     * Create success response.
     *
     * @param string $message
     * @param array $data
     * @return JsonResponse
     */
    protected function successResponse(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge([
            'success' => true,
            'message' => $message,
        ], $data));
    }

    /**
     * Create error response.
     *
     * @param string $message
     * @param int $status
     * @param array $data
     * @return JsonResponse
     */
    protected function errorResponse(string $message, int $status = 422, array $data = []): JsonResponse
    {
        return response()->json(array_merge([
            'success' => false,
            'message' => $message,
        ], $data), $status);
    }

    /**
     * Check attachment limit.
     *
     * @param Field $field
     * @param int $currentCount
     * @param int $newCount
     * @return JsonResponse|null Returns error response if limit exceeded, null otherwise
     */
    protected function checkAttachmentLimit(Field $field, int $currentCount, int $newCount): ?JsonResponse
    {
        if (!method_exists($field, 'getAttachableLimit')) {
            return null;
        }

        $limit = $field->getAttachableLimit();

        if ($limit === null) {
            return null;
        }

        if ($currentCount + $newCount > $limit) {
            return $this->errorResponse(
                "Attachment limit exceeded. Maximum allowed: {$limit}",
                422,
                [
                    'current' => $currentCount,
                    'limit' => $limit,
                    'attempting' => $newCount,
                ]
            );
        }

        return null;
    }

    /**
     * By default, services don't support sync.
     * Override in services that do (BelongsToMany, MorphToMany).
     *
     * @return bool
     */
    public function supportsSync(): bool
    {
        return false;
    }

    /**
     * Default sync implementation throws error.
     * Override in services that support it.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field $field
     * @return JsonResponse
     */
    public function sync(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        return $this->errorResponse('Sync operation not supported for this relation type', 422);
    }
}
