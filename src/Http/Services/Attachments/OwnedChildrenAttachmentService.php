<?php

namespace SchoolAid\Nadota\Http\Services\Attachments;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/**
 * Attach/detach for one-to-many relations (HasMany, MorphMany): the child row holds its owner, so
 * attaching writes the owner columns on the child and detaching nulls them. The rules (see
 * AttachesOwnedChildren on the field):
 *
 * - The attachable list and attach use the same candidates: children without an owner, or of any
 *   other owner when the field is `reassignable()`, scoped by the related resource's
 *   `optionsQuery()` and the field's `attachableQuery()`. An id outside them rejects the request.
 * - Detach is refused when the owner columns are NOT NULL.
 * - Each child is saved through its model (events and observers run), in one transaction, between
 *   the resource's before/after Attach/Detach hooks.
 */
abstract class OwnedChildrenAttachmentService extends AbstractAttachmentService
{
    /**
     * Whether the parent's relation is the kind this service writes.
     */
    abstract protected function supportsRelation(Model $parentModel, Field $field): bool;

    abstract protected function unsupportedMessage(): string;

    public function getAttachableItems(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        if (!$this->supportsRelation($parentModel, $field)) {
            return $this->errorResponse($this->unsupportedMessage());
        }

        $query = $this->candidatesQuery($request, $parentModel, $field);
        $params = $this->getPaginationParams($request);

        if (!empty($params['search'])) {
            $this->applySearch($query, $params['search'], $this->getSearchableFields($field));
        }

        if (method_exists($field, 'getOrderBy') && $field->getOrderBy()) {
            $direction = method_exists($field, 'getOrderDirection') ? $field->getOrderDirection() : 'asc';
            $query->orderBy($field->getOrderBy(), $direction);
        }

        $paginated = $query->paginate($params['per_page']);

        $items = collect($paginated->items())->map(fn (Model $item) => [
            'id' => $item->getKey(),
            'label' => $this->getItemLabel($item, $field),
            'meta' => $this->getItemMeta($item, $field, $request),
        ]);

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'attachable_limit' => method_exists($field, 'getAttachableLimit') ? $field->getAttachableLimit() : null,
            ],
        ]);
    }

    public function attach(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        if (!$this->supportsRelation($parentModel, $field)) {
            return $this->errorResponse($this->unsupportedMessage());
        }

        $items = $this->requestedIds($request);

        if (empty($items)) {
            return $this->errorResponse('No items to attach');
        }

        $relation = $this->relation($parentModel, $field);
        $keyName = $relation->getRelated()->getKeyName();
        $qualifiedKey = $relation->getRelated()->qualifyColumn($keyName);

        $alreadyAttached = $this->relation($parentModel, $field)
            ->whereIn($qualifiedKey, $items)
            ->pluck($keyName)
            ->map(fn ($id) => (string) $id)
            ->all();

        $children = $this->candidatesQuery($request, $parentModel, $field)
            ->whereIn($qualifiedKey, $items)
            ->get();

        $rejected = array_values(array_diff($items, $alreadyAttached, $children->map(fn (Model $child) => (string) $child->getKey())->all()));

        if (!empty($rejected)) {
            return $this->errorResponse('Some items cannot be attached: they belong to another record or are not available', 422, [
                'rejected' => $rejected,
            ]);
        }

        if ($children->isEmpty()) {
            return $this->successResponse('All items are already attached', [
                'attached' => [],
                'count' => 0,
            ]);
        }

        $limitError = $this->checkAttachmentLimit($field, $relation->count(), $children->count());
        if ($limitError) {
            return $limitError;
        }

        $owner = $field::ownerColumns($relation);
        $connection = $relation->getRelated()->getConnectionName();

        // Between the resource's beforeAttach/afterAttach hooks, in one transaction.
        $attached = $this->withHooks($request, 'Attach', $parentModel, $field, $children->modelKeys(), function () use ($children, $owner, $connection) {
            DB::connection($connection)->transaction(function () use ($children, $owner) {
                $children->each(fn (Model $child) => $child->forceFill($owner)->save());
            });

            return $children->modelKeys();
        });

        $this->trackAttachmentAction('attach', $parentModel, $request, $field, ['attached' => $attached]);

        return $this->successResponse('Items attached successfully', [
            'attached' => $attached,
            'count' => count($attached),
        ]);
    }

    public function detach(NadotaRequest $request, Model $parentModel, Field $field): JsonResponse
    {
        if (!$this->supportsRelation($parentModel, $field)) {
            return $this->errorResponse($this->unsupportedMessage());
        }

        $items = $this->requestedIds($request);

        if (empty($items)) {
            return $this->errorResponse('No items to detach');
        }

        $relation = $this->relation($parentModel, $field);

        if (!$field::childrenCanBeOrphaned($relation)) {
            return $this->errorResponse('These items cannot be detached: each one needs its parent record');
        }

        $children = $relation
            ->whereIn($relation->getRelated()->qualifyColumn($relation->getRelated()->getKeyName()), $items)
            ->get();

        $orphan = array_fill_keys(array_keys($field::ownerColumns($relation)), null);

        $connection = $relation->getRelated()->getConnectionName();

        // Between the resource's beforeDetach/afterDetach hooks, in one transaction.
        $detached = $this->withHooks($request, 'Detach', $parentModel, $field, $children->modelKeys(), function () use ($children, $orphan, $connection) {
            DB::connection($connection)->transaction(function () use ($children, $orphan) {
                $children->each(fn (Model $child) => $child->forceFill($orphan)->save());
            });

            return $children->modelKeys();
        });

        $this->trackAttachmentAction('detach', $parentModel, $request, $field, ['detached' => $detached]);

        return $this->successResponse('Items detached successfully', [
            'detached' => count($detached),
        ]);
    }

    /**
     * Children this parent may take: without an owner, or (reassignable) of any other owner.
     */
    protected function candidatesQuery(NadotaRequest $request, Model $parentModel, Field $field): Builder
    {
        $relation = $this->relation($parentModel, $field);
        $related = $relation->getRelated();
        $foreignKey = $related->qualifyColumn($relation->getForeignKeyName());
        $owner = collect($field::ownerColumns($relation))
            ->mapWithKeys(fn ($value, $column) => [$related->qualifyColumn($column) => $value])
            ->all();

        $query = $related->newQuery();

        if (method_exists($field, 'isReassignable') && $field->isReassignable()) {
            $query->where(fn (Builder $q) => $q->whereNull($foreignKey)->orWhereNot(fn (Builder $w) => $w->where($owner)));
        } else {
            $query->whereNull($foreignKey);
        }

        if ($resourceClass = $field->getResource()) {
            $resource = new $resourceClass;

            if (method_exists($resource, 'optionsQuery')) {
                $query = $resource->optionsQuery($query, $request, []);
            }
        }

        if (method_exists($field, 'getAttachableQueryCallback') && ($callback = $field->getAttachableQueryCallback())) {
            $callback($query);
        }

        return $query;
    }

    protected function relation(Model $parentModel, Field $field): HasOneOrMany
    {
        return $parentModel->{$field->getRelation()}();
    }

    /**
     * Requested ids as unique strings (ids arrive as strings or numbers).
     *
     * @return string[]
     */
    protected function requestedIds(NadotaRequest $request): array
    {
        return array_values(array_unique(array_map('strval', $this->getItemsFromRequest($request) ?? [])));
    }

    /**
     * Additional metadata for an attachable item: the field's select fields.
     */
    protected function getItemMeta(Model $item, Field $field, NadotaRequest $request): array
    {
        $meta = [];

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
