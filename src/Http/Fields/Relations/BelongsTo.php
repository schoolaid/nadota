<?php

namespace SchoolAid\Nadota\Http\Fields\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Resources\RelationResource;

class BelongsTo extends Field
{
    /**
     * The foreign key attribute name.
     * If not set, it will be inferred from the relation name.
     */
    protected ?string $foreignKeyAttribute = null;

    /**
     * The related model class (for validation and options).
     */
    public ?string $relatedModelClass = null;

    /**
     * Custom fields to select from the related model.
     */
    protected ?array $customFields = null;

    /**
     * Create a new BelongsTo field.
     *
     * @param string|null $name Display the name for the field
     * @param string $relation The relation method name on the model (e.g., 'user', 'category')
     */
    public function __construct(?string $name, string $relation)
    {
        // Infer the foreign key attribute from the relation name (e.g., 'user' -> 'user_id')
        $attribute = Str::snake($relation) . '_id';

        parent::__construct($name, $attribute, FieldType::BELONGS_TO->value, config('nadota.fields.belongsTo.component', 'field'));
        $this->relation($relation);
        $this->isRelationship = true;
    }

    /**
     * Set a custom foreign key attribute if it doesn't follow the convention.
     *
     * @param string $foreignKey The foreign key column name (e.g., 'created_by_user_id')
     * @return static
     */
    public function foreignKey(string $foreignKey): static
    {
        $this->foreignKeyAttribute = $foreignKey;
        $this->fieldData->attribute = $foreignKey;
        return $this;
    }

    /**
     * Get the foreign key attribute name.
     *
     * @return string
     */
    public function getForeignKey(): string
    {
        return $this->foreignKeyAttribute ?? $this->getAttribute();
    }

    /**
     * Resolve the foreign key from the Eloquent relationship.
     *
     * @param string $modelClass The parent model class
     * @return string The actual foreign key column name
     */
    public function resolveForeignKeyFromModel(string $modelClass): string
    {
        // If a custom foreign key was set, use it
        if ($this->foreignKeyAttribute !== null) {
            return $this->foreignKeyAttribute;
        }

        try {
            $model = new $modelClass;
            $relationName = $this->getRelation();

            if (method_exists($model, $relationName)) {
                $relation = $model->{$relationName}();

                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
                    return $relation->getForeignKeyName();
                }
            }
        } catch (\Throwable $e) {
            // Fall back to the inferred attribute
        }

        return $this->getAttribute();
    }

    /**
     * Get the columns this field needs for SELECT queries.
     * Returns the resolved foreign key from the Eloquent relationship.
     *
     * @param string $modelClass The parent model class
     * @return array
     */
    public function getColumnsForSelect(string $modelClass): array
    {
        return [$this->resolveForeignKeyFromModel($modelClass)];
    }

    /**
     * Set custom fields to select from the related model.
     *
     * @param array $fields Array of Field instances
     * @return static
     */
    public function fields(array $fields): static
    {
        $this->customFields = $fields;
        return $this;
    }

    /**
     * Resolve the field value for display.
     *
     * @param Request $request
     * @param Model $model
     * @param ResourceInterface|null $resource
     * @return mixed
     */
    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $relatedModel = $model->{$this->getRelation()};

        if ($relatedModel === null) {
            if ($this->hasDefault()) {
                return $this->resolveDefault($request, $model, $resource);
            }
            return null;
        }

        // If we have a resource, format with fields
        if ($this->getResource()) {
            $resourceClass = $this->getResource();
            $relatedResource = new $resourceClass;
            return $this->formatWithResource($relatedModel, $relatedResource, $request);
        }

        // Otherwise, return basic formatting
        return $this->formatBasic($relatedModel);
    }

    /**
     * Format related model using resource fields.
     */
    protected function formatWithResource(Model $item, ResourceInterface $resource, Request $request): array
    {
        // Use custom fields if provided, otherwise use resource's index fields
        $fields = $this->customFields !== null
            ? collect($this->customFields)
            : collect($resource->fieldsForIndex($request));

        return RelationResource::make($fields, $resource, $this->exceptFieldKeys)
            ->withLabelResolver(fn($model, $res) => $this->resolveLabel($model, $res))
            ->withoutPermissions()
            ->formatItem($item, $request);
    }

    /**
     * Format related model without resource (basic formatting).
     */
    protected function formatBasic(Model $item): array
    {
        return [
            'key' => $item->getKey(),
            'label' => $this->resolveLabel($item, null),
            'resource' => null,
        ];
    }

    /**
     * Resolve display label for the related model.
     */
    protected function resolveLabel(Model $item, ?ResourceInterface $resource): mixed
    {
        // Priority 1: Display callback set on the field
        if ($this->hasDisplayCallback()) {
            return $this->resolveDisplay($item);
        }

        // Priority 2: Display attribute set on the field
        if ($this->displayAttribute) {
            return $item->{$this->displayAttribute} ?? $item->getKey();
        }

        // Priority 3: Resource's displayLabel method
        if ($resource && method_exists($resource, 'displayLabel')) {
            return $resource->displayLabel($item);
        }

        // Fallback: primary key
        return $item->getKey();
    }

    public function applySorting(Builder $query, $sortDirection, $modelInstance): Builder
    {
        $relation = $modelInstance->{$this->getRelation()}();
        $relatedTable = $relation->getRelated()->getTable();
        $modelTable = $modelInstance->getTable();
        $displayField = $this->getAttributeForDisplay();

        $foreignKey = $relation->getForeignKeyName();
        $relatedKey = $relation->getOwnerKeyName();
        $query->join($relatedTable, "$modelTable.$foreignKey", '=', "$relatedTable.$relatedKey");


        return $query
            ->orderBy("$relatedTable.$displayField", $sortDirection)
            ->select("$modelTable.*");
    }

    /**
     * Set the related model class.
     * Automatically adds exists validation rule.
     *
     * @param string $modelClass
     * @return static
     */
    public function relatedModel(string $modelClass): static
    {
        $this->relatedModelClass = $modelClass;
        $this->relatedModel = $modelClass;
        $this->isRelationship = true;

        // Auto-add exists validation rule
        $table = (new $modelClass)->getTable();
        $this->rules("exists:{$table},id");

        return $this;
    }

    /**
     * Set the display attribute for the relation.
     * Alias for displayAttribute() method for API consistency.
     *
     * @param string $attribute
     * @return static
     */
    public function relationAttribute(string $attribute): static
    {
        return $this->displayAttribute($attribute);
    }

    /**
     * Get the relation type.
     *
     * @return string
     */
    public function relationType(): string
    {
        return 'belongsTo';
    }

    /**
     * Get options for select component.
     * Fetches all records from the related model.
     *
     * @return array
     */
    public function getOptions(): array
    {
        if (!$this->relatedModelClass) {
            return [];
        }

        $displayAttribute = $this->displayAttribute ?? 'name';
        $modelClass = $this->relatedModelClass;

        return $modelClass::query()
            ->get()
            ->map(function ($item) use ($displayAttribute) {
                return [
                    'value' => $item->getKey(),
                    'label' => $item->{$displayAttribute} ?? $item->getKey(),
                ];
            })
            ->toArray();
    }
}
