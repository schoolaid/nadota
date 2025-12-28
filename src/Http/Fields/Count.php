<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Count extends Field
{
    /**
     * The relation name to count.
     */
    protected string $countRelation;

    /**
     * Optional constraint callback for the count query.
     */
    protected ?\Closure $countConstraint = null;

    /**
     * Whether this field requires withCount on the query.
     */
    protected bool $requiresWithCount = true;

    /**
     * Create a new Count field.
     *
     * @param string $name Display name for the field
     * @param string $relation Relation method name to count
     */
    public function __construct(string $name, string $relation)
    {
        // The attribute will be {relation}_count as Laravel names it (snake_case)
        $attribute = Str::snake($relation) . '_count';

        parent::__construct($name, $attribute, FieldType::NUMBER->value, static::safeConfig('nadota.fields.count.component', 'FieldCount'));

        $this->countRelation = $relation;

        // Count fields are read-only and computed
        $this->computed = true;
        $this->readonly();

        // Show on index and detail by default
        $this->showOnIndex = true;
        $this->showOnDetail = true;
        $this->showOnCreation = false;
        $this->showOnUpdate = false;

        // Apply in queries to load the count
        $this->applyInIndexQuery = true;
        $this->applyInShowQuery = true;
    }

    /**
     * Add a constraint to the count query.
     *
     * @param \Closure $callback
     * @return static
     */
    public function constraint(\Closure $callback): static
    {
        $this->countConstraint = $callback;
        return $this;
    }

    /**
     * Get the relation name to count.
     *
     * @return string
     */
    public function getCountRelation(): string
    {
        return $this->countRelation;
    }

    /**
     * Get the count constraint callback.
     *
     * @return \Closure|null
     */
    public function getCountConstraint(): ?\Closure
    {
        return $this->countConstraint;
    }

    /**
     * Check if this field requires withCount.
     *
     * @return bool
     */
    public function requiresWithCount(): bool
    {
        return $this->requiresWithCount;
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
        // Laravel stores count as {relation}_count attribute (snake_case)
        $countAttribute = Str::snake($this->countRelation) . '_count';

        return $model->{$countAttribute} ?? 0;
    }

    /**
     * Count fields don't need columns from the model.
     * The withCount adds a virtual column automatically.
     *
     * @param string $modelClass
     * @return array
     */
    public function getColumnsForSelect(string $modelClass): array
    {
        // Return empty - withCount() automatically adds the count as a virtual column
        // We don't want to include it in SELECT as it's not a real table column
        return [];
    }

    /**
     * Override fill - count fields are read-only.
     */
    public function fill(Request $request, Model $model): void
    {
        // Count fields don't fill anything
    }

    /**
     * Get props for frontend component.
     */
    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'relation' => $this->countRelation,
        ]);
    }
}
