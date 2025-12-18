<?php

namespace SchoolAid\Nadota\Http\Services\Attachments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class MorphManyAttachmentService extends AbstractAttachmentService
{
    /**
     * Get attachable items for a MorphMany field.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field|MorphMany $field
     * @return JsonResponse
     */
    public function getAttachableItems(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        $relationName = $field->getRelation();

        // Get the relation
        $relation = $parentModel->{$relationName}();

        // Get the related model class
        $relatedModel = $relation->getRelated();
        $relatedClass = get_class($relatedModel);

        // Get the morph columns from the relation
        $foreignKey = $relation->getForeignKeyName();
        $morphType = $relation->getMorphType();
        $morphClass = $relation->getMorphClass();

        // Build query for attachable items (items not already attached to this parent)
        $query = $relatedClass::query()
            ->where(function ($q) use ($foreignKey, $morphType, $morphClass, $parentModel) {
                // Items with null FK (unassigned)
                $q->whereNull($foreignKey)
                    // Or items assigned to a different parent
                    ->orWhere(function ($q2) use ($foreignKey, $morphType, $morphClass, $parentModel) {
                        $q2->where($morphType, '!=', $morphClass)
                            ->orWhere($foreignKey, '!=', $parentModel->getKey());
                    });
            });

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

        // Apply custom query callback if provided
        if (method_exists($field, 'getAttachableQueryCallback')) {
            $callback = $field->getAttachableQueryCallback();
            if ($callback) {
                $callback($query);
            }
        }

        // Apply ordering
        if (method_exists($field, 'getOrderBy') && $field->getOrderBy()) {
            $direction = method_exists($field, 'getOrderDirection') ? $field->getOrderDirection() : 'asc';
            $query->orderBy($field->getOrderBy(), $direction);
        }

        // Paginate
        $paginated = $query->paginate($params['per_page']);

        // Format response
        $items = collect($paginated->items())->map(function ($item) use ($field, $request) {
            return [
                'id' => $item->getKey(),
                'label' => $this->getItemLabel($item, $field),
                'meta' => $this->getItemMeta($item, $field, $request),
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
                'attachable_limit' => method_exists($field, 'getAttachableLimit') ? $field->getAttachableLimit() : null,
            ]
        ]);
    }

    /**
     * Attach items to a MorphMany relationship.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field|MorphMany $field
     * @return JsonResponse
     */
    public function attach(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        $items = $this->getItemsFromRequest($request);

        if (empty($items)) {
            return $this->errorResponse('No items to attach');
        }

        $relationName = $field->getRelation();

        // Get the relation
        $relation = $parentModel->{$relationName}();

        // Get the related model class
        $relatedModel = $relation->getRelated();
        $relatedClass = get_class($relatedModel);

        // Get the morph columns
        $foreignKey = $relation->getForeignKeyName();
        $morphType = $relation->getMorphType();
        $morphClass = $relation->getMorphClass();

        // Check attachment limit
        $currentCount = $relation->count();
        $limitError = $this->checkAttachmentLimit($field, $currentCount, count($items));
        if ($limitError) {
            return $limitError;
        }

        // Find items to attach (only those not already attached to this parent)
        $itemsToAttach = $relatedClass::query()
            ->whereIn($relatedModel->getKeyName(), $items)
            ->where(function ($q) use ($foreignKey, $morphType, $morphClass, $parentModel) {
                $q->whereNull($foreignKey)
                    ->orWhere(function ($q2) use ($foreignKey, $morphType, $morphClass, $parentModel) {
                        $q2->where($morphType, '!=', $morphClass)
                            ->orWhere($foreignKey, '!=', $parentModel->getKey());
                    });
            })
            ->get();

        if ($itemsToAttach->isEmpty()) {
            return $this->successResponse('All items are already attached or not found', [
                'attached' => [],
                'count' => 0,
            ]);
        }

        // Attach items by setting the morph columns
        $attached = [];
        foreach ($itemsToAttach as $item) {
            $item->{$foreignKey} = $parentModel->getKey();
            $item->{$morphType} = $morphClass;
            $item->save();
            $attached[] = $item->getKey();
        }

        return $this->successResponse('Items attached successfully', [
            'attached' => $attached,
            'count' => count($attached),
        ]);
    }

    /**
     * Detach items from a MorphMany relationship.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field|MorphMany $field
     * @return JsonResponse
     */
    public function detach(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        $items = $this->getItemsFromRequest($request);

        if (empty($items)) {
            return $this->errorResponse('No items to detach');
        }

        $relationName = $field->getRelation();

        // Get the relation
        $relation = $parentModel->{$relationName}();
        $foreignKey = $relation->getForeignKeyName();
        $morphType = $relation->getMorphType();

        // Find and detach items by setting morph columns to null
        $detached = $relation
            ->whereIn($relation->getRelated()->getKeyName(), $items)
            ->update([
                $foreignKey => null,
                $morphType => null,
            ]);

        return $this->successResponse('Items detached successfully', [
            'detached' => $detached,
        ]);
    }

    /**
     * Get additional metadata for an attachable item.
     *
     * @param Model $item
     * @param Field $field
     * @param NadotaRequest $request
     * @return array
     */
    protected function getItemMeta(Model $item, Field $field, NadotaRequest $request): array
    {
        $meta = [];

        // If we have custom fields defined, use those
        if (method_exists($field, 'getFieldsForSelect')) {
            $fields = $field->getFieldsForSelect($request);

            if ($fields && is_array($fields)) {
                foreach ($fields as $fieldName) {
                    if (isset($item->{$fieldName})) {
                        $meta[$fieldName] = $item->{$fieldName};
                    }
                }
            }
        }

        return $meta;
    }
}
