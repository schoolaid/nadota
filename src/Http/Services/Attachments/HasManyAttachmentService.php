<?php

namespace SchoolAid\Nadota\Http\Services\Attachments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class HasManyAttachmentService extends AbstractAttachmentService
{
    /**
     * Get attachable items for a HasMany field.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field|HasMany $field
     * @return JsonResponse
     */
    public function getAttachableItems(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        $relationName = $field->getRelation();

        // Get the related model class
        $relatedModel = $parentModel->{$relationName}()->getRelated();
        $relatedClass = get_class($relatedModel);

        // Get the foreign key from the relation
        $foreignKey = $parentModel->{$relationName}()->getForeignKeyName();

        // Build query for attachable items (items not already attached)
        $query = $relatedClass::query()
            ->where(function ($q) use ($foreignKey, $parentModel) {
                $q->whereNull($foreignKey)
                    ->orWhere($foreignKey, '!=', $parentModel->getKey());
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
     * Attach items to a HasMany relationship.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field|HasMany $field
     * @return JsonResponse
     */
    public function attach(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        $items = $this->getItemsFromRequest($request);

        if (empty($items)) {
            return $this->errorResponse('No items to attach');
        }

        $relationName = $field->getRelation();

        // Get the related model class
        $relatedModel = $parentModel->{$relationName}()->getRelated();
        $relatedClass = get_class($relatedModel);

        // Get the foreign key
        $foreignKey = $parentModel->{$relationName}()->getForeignKeyName();

        // Check attachment limit
        $currentCount = $parentModel->{$relationName}()->count();
        $limitError = $this->checkAttachmentLimit($field, $currentCount, count($items));
        if ($limitError) {
            return $limitError;
        }

        // Find items to attach (only those not already attached)
        $itemsToAttach = $relatedClass::query()
            ->whereIn($relatedModel->getKeyName(), $items)
            ->where(function ($q) use ($foreignKey, $parentModel) {
                $q->whereNull($foreignKey)
                    ->orWhere($foreignKey, '!=', $parentModel->getKey());
            })
            ->get();

        if ($itemsToAttach->isEmpty()) {
            return $this->successResponse('All items are already attached or not found', [
                'attached' => [],
                'count' => 0,
            ]);
        }

        // Attach items by setting the foreign key
        $attached = [];
        foreach ($itemsToAttach as $item) {
            $item->{$foreignKey} = $parentModel->getKey();
            $item->save();
            $attached[] = $item->getKey();
        }

        return $this->successResponse('Items attached successfully', [
            'attached' => $attached,
            'count' => count($attached),
        ]);
    }

    /**
     * Detach items from a HasMany relationship.
     *
     * @param NadotaRequest $request
     * @param Model $parentModel
     * @param Field|HasMany $field
     * @return JsonResponse
     */
    public function detach(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        $items = $this->getItemsFromRequest($request);

        if (empty($items)) {
            return $this->errorResponse('No items to detach');
        }

        $relationName = $field->getRelation();

        // Get the foreign key
        $foreignKey = $parentModel->{$relationName}()->getForeignKeyName();

        // Find and detach items by setting FK to null
        $detached = $parentModel->{$relationName}()
            ->whereIn($parentModel->{$relationName}()->getRelated()->getKeyName(), $items)
            ->update([$foreignKey => null]);

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
