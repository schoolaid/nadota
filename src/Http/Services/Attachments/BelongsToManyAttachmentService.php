<?php

namespace SchoolAid\Nadota\Http\Services\Attachments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class BelongsToManyAttachmentService extends AbstractAttachmentService
{
    /**
     * Get attachable items for a BelongsToMany field.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field|BelongsToMany $field
     * @return JsonResponse
     */
    public function getAttachableItems(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        $relationName = $field->getRelation();
        $relation = $parentModel->{$relationName}();

        // Get the related model class
        $relatedModel = $relation->getRelated();
        $relatedClass = get_class($relatedModel);
        $relatedKeyName = $relatedModel->getKeyName();

        // Get IDs of already attached items
        $attachedIds = $parentModel->{$relationName}()->pluck($relatedKeyName)->toArray();

        // Build query for attachable items (items not already attached)
        $query = $relatedClass::query();

        if (!empty($attachedIds)) {
            $query->whereNotIn($relatedKeyName, $attachedIds);
        }

        // Apply resource's optionsQuery if available
        if ($field->getResource()) {
            $resourceClass = $field->getResource();
            $resource = new $resourceClass;

            if (method_exists($resource, 'optionsQuery')) {
                $query = $resource->optionsQuery($query, $request, []);
            }
        }

        // Get pagination params
        $params = $this->getPaginationParams($request);

        // Apply search if provided
        if (!empty($params['search'])) {
            $searchFields = $this->getSearchableFields($field);
            $this->applySearch($query, $params['search'], $searchFields);
        }

        // Apply ordering
        if (method_exists($field, 'getOrderBy') && $field->getOrderBy()) {
            $direction = method_exists($field, 'getOrderDirection') ? $field->getOrderDirection() : 'asc';
            $query->orderBy($field->getOrderBy(), $direction);
        }

        // Paginate
        $paginated = $query->paginate($params['per_page']);

        // Format response
        $items = collect($paginated->items())->map(function ($item) use ($field) {
            return [
                'id' => $item->getKey(),
                'label' => $this->getItemLabel($item, $field),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'attached_count' => count($attachedIds),
            ]
        ]);
    }

    /**
     * Attach items to a BelongsToMany relationship.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field|BelongsToMany $field
     * @return JsonResponse
     */
    public function attach(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        $items = $this->getItemsFromRequest($request);

        if (empty($items)) {
            return $this->errorResponse('No items to attach');
        }

        $relationName = $field->getRelation();
        $relation = $parentModel->{$relationName}();

        // Check attachment limit
        if (method_exists($field, 'getAttachableLimit')) {
            $currentCount = $relation->count();
            $limitError = $this->checkAttachmentLimit($field, $currentCount, count($items));
            if ($limitError) {
                return $limitError;
            }
        }

        // Get pivot data
        $pivotData = $this->getPivotDataFromRequest($request);

        // Validate pivot data against pivot fields if defined
        $validatedPivot = $this->validatePivotData($field, $pivotData);

        // Filter out already attached items
        $relatedKeyName = $relation->getRelated()->getKeyName();
        $alreadyAttached = $relation->pluck($relatedKeyName)->toArray();
        $toAttach = array_diff($items, $alreadyAttached);

        if (empty($toAttach)) {
            return $this->successResponse('All items are already attached', [
                'attached' => [],
                'already_attached' => count($items),
            ]);
        }

        // Attach with pivot data
        if (!empty($validatedPivot)) {
            // If pivot data is keyed by item ID, use that structure
            if ($this->isPivotKeyedById($pivotData)) {
                $attachData = [];
                foreach ($toAttach as $id) {
                    $attachData[$id] = $pivotData[$id] ?? $validatedPivot;
                }
                $relation->attach($attachData);
            } else {
                // Same pivot data for all items
                $relation->attach($toAttach, $validatedPivot);
            }
        } else {
            $relation->attach($toAttach);
        }

        return $this->successResponse('Items attached successfully', [
            'attached' => array_values($toAttach),
            'count' => count($toAttach),
        ]);
    }

    /**
     * Detach items from a BelongsToMany relationship.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field|BelongsToMany $field
     * @return JsonResponse
     */
    public function detach(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        $items = $this->getItemsFromRequest($request);

        if (empty($items)) {
            return $this->errorResponse('No items to detach');
        }

        $relationName = $field->getRelation();
        $relation = $parentModel->{$relationName}();

        // Detach items
        $detached = $relation->detach($items);

        return $this->successResponse('Items detached successfully', [
            'detached' => $detached,
        ]);
    }

    /**
     * BelongsToMany supports sync.
     *
     * @return bool
     */
    public function supportsSync(): bool
    {
        return true;
    }

    /**
     * Sync items in a BelongsToMany relationship.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field|BelongsToMany $field
     * @return JsonResponse
     */
    public function sync(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        $items = $this->getItemsFromRequest($request);

        if ($items === null) {
            return $this->errorResponse('Invalid items format');
        }

        $relationName = $field->getRelation();
        $relation = $parentModel->{$relationName}();

        // Check attachment limit for sync
        if (method_exists($field, 'getAttachableLimit')) {
            $limit = $field->getAttachableLimit();
            if ($limit !== null && count($items) > $limit) {
                return $this->errorResponse(
                    "Attachment limit exceeded. Maximum allowed: {$limit}",
                    422,
                    ['limit' => $limit, 'attempting' => count($items)]
                );
            }
        }

        // Get pivot data
        $pivotData = $this->getPivotDataFromRequest($request);

        // Check if detaching is enabled (default true for sync)
        $detaching = $request->get('detaching', true);

        // Build sync data
        if (!empty($pivotData) && $this->isPivotKeyedById($pivotData)) {
            // Pivot data keyed by ID
            $syncData = [];
            foreach ($items as $id) {
                $syncData[$id] = $pivotData[$id] ?? [];
            }
            $changes = $relation->sync($syncData, $detaching);
        } elseif (!empty($pivotData)) {
            // Same pivot data for all
            $syncData = [];
            foreach ($items as $id) {
                $syncData[$id] = $pivotData;
            }
            $changes = $relation->sync($syncData, $detaching);
        } else {
            // No pivot data
            $changes = $relation->sync($items, $detaching);
        }

        return $this->successResponse('Items synced successfully', [
            'attached' => $changes['attached'] ?? [],
            'detached' => $changes['detached'] ?? [],
            'updated' => $changes['updated'] ?? [],
        ]);
    }

    /**
     * Check if pivot data is keyed by item ID.
     *
     * @param array $pivotData
     * @return bool
     */
    protected function isPivotKeyedById(array $pivotData): bool
    {
        if (empty($pivotData)) {
            return false;
        }

        // Check if first key is numeric (item ID) and value is array
        $firstKey = array_key_first($pivotData);
        $firstValue = $pivotData[$firstKey] ?? null;

        return is_numeric($firstKey) && is_array($firstValue);
    }

    /**
     * Validate pivot data against field's pivot fields.
     *
     * @param Field|BelongsToMany $field
     * @param array $pivotData
     * @return array Validated and filtered pivot data
     */
    protected function validatePivotData(Field $field, array $pivotData): array
    {
        if (empty($pivotData)) {
            return [];
        }

        // If field has pivotColumns defined, only allow those
        if (method_exists($field, 'getPivotColumns')) {
            $allowedColumns = $field->getPivotColumns();

            if (!empty($allowedColumns)) {
                // Handle keyed by ID structure
                if ($this->isPivotKeyedById($pivotData)) {
                    $validated = [];
                    foreach ($pivotData as $id => $data) {
                        $validated[$id] = array_intersect_key($data, array_flip($allowedColumns));
                    }
                    return $validated;
                }

                // Simple structure
                return array_intersect_key($pivotData, array_flip($allowedColumns));
            }
        }

        return $pivotData;
    }
}
