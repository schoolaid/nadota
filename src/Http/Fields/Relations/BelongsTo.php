<?php

namespace SchoolAid\Nadota\Http\Fields\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Field;

class BelongsTo extends Field
{

    public function __construct(?string $name, string $attribute, ?string $relation)
    {
        parent::__construct($name, $attribute, FieldType::BELONGS_TO->value, config('nadota.fields.belongsTo.component', 'field'));
        $this->relation($relation);
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
        $value = $model->{$this->getRelation()};

        if ($value !== null) {
            $resourceKey = null;

            $label = $this->resolveDisplay($value);
            if ($label === null) {
                $commonAttributes = ['name', 'title', 'label', 'display_name', 'full_name', 'description'];
                foreach ($commonAttributes as $attr) {
                    if (isset($value->{$attr})) {
                        $label = $value->{$attr};
                        break;
                    }
                }


                if ($label === null) {
                    $label = $value->getKey();
                }
            }

            if($this->getResource()){
                $resourceKey = $this->getResource()::getKey();
            }

            return [
                'key' => $value->getKey(),
                'label' => $label,
                'resource' => $resourceKey,
            ];
        }

        if ($this->hasDefault()) {
            return $this->resolveDefault($request, $model, $resource);
        }

        return $value;
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
}
