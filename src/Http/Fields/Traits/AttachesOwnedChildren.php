<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/**
 * Attach/detach for one-to-many fields (HasMany, MorphMany), where the child row itself holds the
 * owner (its foreign key, plus the morph type). Attaching hands a child to this parent and
 * detaching leaves it without one, so the rules are stricter than for a pivot:
 *
 * - Off by default: turn it on per field with `->attachable()`.
 * - Attaching only adopts children without an owner. Taking a child away from another record
 *   needs `->reassignable()`.
 * - Detaching needs the owner columns to be nullable; otherwise it is not offered (the update
 *   would fail on the NOT NULL constraint).
 *
 * The using class needs `use ManagesAttachments` and a static `supportsAttachment()`.
 */
trait AttachesOwnedChildren
{
    /**
     * Whether attach may take a child that already belongs to another record.
     */
    protected bool $reassignable = false;

    /**
     * Nullability of the owner columns, per connection and table.
     *
     * @var array<string, bool>
     */
    protected static array $orphanableCache = [];

    /**
     * Let attach take children that belong to another record (it implies `attachable()`).
     */
    public function reassignable(bool $reassignable = true): static
    {
        $this->reassignable = $reassignable;

        if ($reassignable) {
            $this->attachable = true;
        }

        return $this;
    }

    public function isReassignable(): bool
    {
        return $this->attachable && $this->reassignable;
    }

    /**
     * The child columns that point at the parent, with this parent's values.
     *
     * @return array<string, mixed>
     */
    public static function ownerColumns(HasOneOrMany $relation): array
    {
        $columns = [$relation->getForeignKeyName() => $relation->getParentKey()];

        if ($relation instanceof MorphOneOrMany) {
            $columns[$relation->getMorphType()] = $relation->getMorphClass();
        }

        return $columns;
    }

    /**
     * Whether a child can be left without an owner: every owner column accepts null.
     */
    public static function childrenCanBeOrphaned(HasOneOrMany $relation): bool
    {
        $related = $relation->getRelated();
        $columns = array_keys(static::ownerColumns($relation));
        $cacheKey = ($related->getConnectionName() ?? 'default') . '|' . $related->getTable() . '|' . implode(',', $columns);

        if (!array_key_exists($cacheKey, static::$orphanableCache)) {
            try {
                $nullable = collect(Schema::connection($related->getConnectionName())->getColumns($related->getTable()))
                    ->filter(fn ($column) => in_array($column['name'], $columns, true))
                    ->mapWithKeys(fn ($column) => [$column['name'] => (bool) $column['nullable']]);

                static::$orphanableCache[$cacheKey] = $nullable->count() === count($columns) && $nullable->every(fn ($isNullable) => $isNullable);
            } catch (\Throwable $e) {
                static::$orphanableCache[$cacheKey] = false;
            }
        }

        return static::$orphanableCache[$cacheKey];
    }

    public static function flushOrphanableCache(): void
    {
        static::$orphanableCache = [];
    }

    /**
     * Resolve whether the current user can attach items to this relation. Uses the parent
     * resource's policy with field context (`attach{Field}()`, `attach()`, or `update()`).
     */
    protected function resolveCanAttach(Request $request, ?ResourceInterface $resource, ?Model $model): bool
    {
        if (!$this->attachable || !$model || !static::supportsAttachment($model, $this->getRelation())) {
            return false;
        }

        return $this->authorizedForAttachment($request, $resource, $model, 'attach');
    }

    /**
     * Resolve whether the current user can detach items from this relation.
     */
    protected function resolveCanDetach(Request $request, ?ResourceInterface $resource, ?Model $model): bool
    {
        if (!$this->attachable || !$model || !static::supportsAttachment($model, $this->getRelation())) {
            return false;
        }

        if (!static::childrenCanBeOrphaned($model->{$this->getRelation()}())) {
            return false;
        }

        return $this->authorizedForAttachment($request, $resource, $model, 'detach');
    }

    protected function authorizedForAttachment(Request $request, ?ResourceInterface $resource, Model $model, string $ability): bool
    {
        if (!$resource || !$request instanceof NadotaRequest) {
            return true;
        }

        return $resource->authorizedTo($request, $ability, $model, ['field' => $this->key()]);
    }

    /**
     * Attachment config for the frontend, with the resolved abilities.
     */
    protected function ownedChildrenAttachmentConfig(bool $canAttach, bool $canDetach): array
    {
        return array_merge($this->getAttachmentConfig(), [
            'reassignable' => $this->isReassignable(),
            'canAttach' => $canAttach,
            'canDetach' => $canDetach,
        ]);
    }
}
