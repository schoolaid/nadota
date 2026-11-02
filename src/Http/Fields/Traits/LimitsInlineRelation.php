<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * limit() / orderBy() for to-many relation fields rendered inline (not paginated).
 *
 * Expects the using field to declare $limit, $orderBy, $orderDirection and $paginated.
 */
trait LimitsInlineRelation
{
    /**
     * Whether the last resolveInlineItems() call trimmed rows beyond the limit.
     */
    protected bool $hasMoreItems = false;

    /**
     * Apply orderBy() and limit() to the relation query, eager or lazy.
     *
     * Loads one row past the limit so hasMore can tell "exactly limit rows" from "more".
     * Inside an eager load Laravel applies the limit per parent.
     */
    public function applyInlineConstraints($query): void
    {
        if ($this->paginated) {
            return;
        }

        if ($this->orderBy !== null) {
            // Qualify so pivot relations don't hit ambiguous columns like created_at
            $column = str_contains($this->orderBy, '.')
                ? $this->orderBy
                : $query->getRelated()->qualifyColumn($this->orderBy);

            $query->orderBy($column, $this->orderDirection);
        }

        if ($this->limit !== null) {
            $query->limit($this->limit + 1);
        }
    }

    /**
     * The related rows to render, trimmed to the limit.
     *
     * Uses the eager-loaded relation when present (it may have been loaded elsewhere
     * without these constraints, hence the trim), otherwise queries it with them.
     */
    protected function resolveInlineItems(Model $model, string $relationName): mixed
    {
        $items = $model->relationLoaded($relationName)
            ? $model->getRelation($relationName)
            : tap($model->{$relationName}(), fn ($query) => $this->applyInlineConstraints($query))->get();

        $this->hasMoreItems = $items instanceof Collection
            && $this->limit !== null
            && $items->count() > $this->limit;

        return $this->hasMoreItems ? $items->take($this->limit)->values() : $items;
    }
}
