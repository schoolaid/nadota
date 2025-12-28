<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Exists extends Field
{
    /**
     * The relation name to check existence.
     */
    protected string $existsRelation;

    /**
     * Optional constraint callback for the exists query.
     */
    protected ?\Closure $existsConstraint = null;

    /**
     * Whether this field requires withExists on the query.
     */
    protected bool $requiresWithExists = true;

    /**
     * Create a new Exists field.
     *
     * @param string $name Display name for the field
     * @param string $relation Relation method name to check
     */
    public function __construct(string $name, string $relation)
    {
        // The attribute will be {relation}_exists as Laravel names it (snake_case)
        $attribute = Str::snake($relation) . '_exists';

        parent::__construct($name, $attribute, FieldType::BOOLEAN->value, static::safeConfig('nadota.fields.exists.component', 'FieldExists'));

        $this->existsRelation = $relation;

        // Exists fields are read-only and computed
        $this->computed = true;
        $this->readonly();

        // Show on index and detail by default
        $this->showOnIndex = true;
        $this->showOnDetail = true;
        $this->showOnCreation = false;
        $this->showOnUpdate = false;

        // Apply in queries to load the exists check
        $this->applyInIndexQuery = true;
        $this->applyInShowQuery = true;
    }

    /**
     * Add a constraint to the exists query.
     *
     * @param \Closure $callback
     * @return static
     */
    public function constraint(\Closure $callback): static
    {
        $this->existsConstraint = $callback;
        return $this;
    }

    /**
     * Get the relation name to check.
     *
     * @return string
     */
    public function getExistsRelation(): string
    {
        return $this->existsRelation;
    }

    /**
     * Get the exists constraint callback.
     *
     * @return \Closure|null
     */
    public function getExistsConstraint(): ?\Closure
    {
        return $this->existsConstraint;
    }

    /**
     * Check if this field requires withExists.
     *
     * @return bool
     */
    public function requiresWithExists(): bool
    {
        return $this->requiresWithExists;
    }

    /**
     * Resolve the field value.
     *
     * @param Request $request
     * @param Model $model
     * @param ResourceInterface|null $resource
     * @return mixed
     */
    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        // Laravel stores exists as {relation}_exists attribute (snake_case)
        $existsAttribute = Str::snake($this->existsRelation) . '_exists';

        return (bool) ($model->{$existsAttribute} ?? false);
    }

    /**
     * Exists fields don't need columns from the model.
     * The withExists adds a virtual column automatically.
     *
     * @param string $modelClass
     * @return array
     */
    public function getColumnsForSelect(string $modelClass): array
    {
        // Return empty - withExists() automatically adds the exists as a virtual column
        return [];
    }

    /**
     * Override fill - exists fields are read-only.
     */
    public function fill(Request $request, Model $model): void
    {
        // Exists fields don't fill anything
    }

    /**
     * Get props for frontend component.
     */
    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'relation' => $this->existsRelation,
        ]);
    }

    /**
     * Apply filter to query.
     * Filters by existence (true) or non-existence (false) of the relation.
     *
     * @param Builder $query
     * @param mixed $value The filter value (true/false, 1/0, "true"/"false")
     * @param Model $modelInstance
     * @return Builder
     */
    public function applyFilter(Builder $query, $value, $modelInstance): Builder
    {
        // Normalize value to boolean
        $exists = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        $relation = $this->existsRelation;
        $constraint = $this->existsConstraint;

        if ($exists) {
            // Filter where relation exists
            return $query->whereHas($relation, $constraint);
        } else {
            // Filter where relation does not exist
            return $query->whereDoesntHave($relation, $constraint);
        }
    }
}
