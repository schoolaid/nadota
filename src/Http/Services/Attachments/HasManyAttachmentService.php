<?php

namespace SchoolAid\Nadota\Http\Services\Attachments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class HasManyAttachmentService
{
    /**
     * Get attachable items for a HasMany field.
     */
    public function getAttachableItems(NadotaRequest $request, Model $parentModel, HasMany $field): JsonResponse
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

        // Apply search if provided
        if ($search = $request->get('search')) {
            $searchFields = $field->getAttachableSearchFields();

            // If we have a resource, use its searchable fields
            if ($field->getResource()) {
                $resourceClass = $field->getResource();
                $resource = new $resourceClass;

                if (property_exists($resource, 'searchableAttributes')) {
                    $searchFields = $resource->searchableAttributes;
                }
            }

            $query->where(function ($q) use ($searchFields, $search) {
                foreach ($searchFields as $searchField) {
                    $q->orWhere($searchField, 'like', '%' . $search . '%');
                }
            });
        }

        // Apply custom query callback if provided
        if ($callback = $field->getAttachableQueryCallback()) {
            $callback($query);
        }

        // Apply pagination
        $perPage = $request->get('per_page', 25);
        $paginated = $query->paginate($perPage);

        // Format response
        $items = $paginated->map(function ($item) use ($field, $request) {
            $label = $this->getItemLabel($item, $field);

            return [
                'id' => $item->getKey(),
                'label' => $label,
                'meta' => $this->getItemMeta($item, $field, $request),
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'attachable_limit' => $field->getAttachableLimit(),
            ]
        ]);
    }

    /**
     * Attach items to a HasMany relationship.
     */
    public function attach(NadotaRequest $request, Model $parentModel, HasMany $field): JsonResponse
    {
        $relationName = $field->getRelation();
        $itemIds = $request->get('items', []);

        if (empty($itemIds)) {
            return response()->json(['message' => 'No items to attach'], 422);
        }

        // Get the related model class
        $relatedModel = $parentModel->{$relationName}()->getRelated();
        $relatedClass = get_class($relatedModel);

        // Get the foreign key
        $foreignKey = $parentModel->{$relationName}()->getForeignKeyName();

        // Check attachment limit
        if ($limit = $field->getAttachableLimit()) {
            $currentCount = $parentModel->{$relationName}()->count();
            $newCount = count($itemIds);

            if ($currentCount + $newCount > $limit) {
                return response()->json([
                    'message' => "Attachment limit exceeded. Maximum allowed: {$limit}",
                    'current' => $currentCount,
                    'limit' => $limit
                ], 422);
            }
        }

        // Find items to attach
        $itemsToAttach = $relatedClass::query()
            ->whereIn($relatedModel->getKeyName(), $itemIds)
            ->where(function ($q) use ($foreignKey, $parentModel) {
                $q->whereNull($foreignKey)
                    ->orWhere($foreignKey, '!=', $parentModel->getKey());
            })
            ->get();

        // Attach items by setting the foreign key
        $attached = [];
        foreach ($itemsToAttach as $item) {
            $item->{$foreignKey} = $parentModel->getKey();
            $item->save();
            $attached[] = $item->getKey();
        }

        return response()->json([
            'message' => 'Items attached successfully',
            'attached' => $attached,
            'count' => count($attached)
        ]);
    }

    /**
     * Detach items from a HasMany relationship.
     */
    public function detach(NadotaRequest $request, Model $parentModel, HasMany $field): JsonResponse
    {
        $relationName = $field->getRelation();
        $itemIds = $request->get('items', []);

        if (empty($itemIds)) {
            return response()->json(['message' => 'No items to detach'], 422);
        }

        // Get the foreign key
        $foreignKey = $parentModel->{$relationName}()->getForeignKeyName();
        
        // Find and detach items
        $detached = $parentModel->{$relationName}()
            ->whereIn($parentModel->{$relationName}()->getRelated()->getKeyName(), $itemIds)
            ->update([$foreignKey => null]);

        return response()->json([
            'message' => 'Items detached successfully',
            'detached' => $detached
        ]);
    }

    /**
     * Get a label for an attachable item.
     */
    protected function getItemLabel(Model $item, HasMany $field): string
    {
        // Try common display attributes
        $displayAttributes = ['name', 'title', 'label', 'display_name', 'email'];

        foreach ($displayAttributes as $attr) {
            if (isset($item->{$attr})) {
                return $item->{$attr};
            }
        }

        // Fallback to ID
        return "Item #{$item->getKey()}";
    }

    /**
     * Get additional metadata for an attachable item.
     */
    protected function getItemMeta(Model $item, HasMany $field, NadotaRequest $request): array
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