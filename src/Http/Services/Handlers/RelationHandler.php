<?php

namespace Said\Nadota\Http\Services\Handlers;

use Illuminate\Database\Eloquent\Model;
use Said\Nadota\Http\Fields\Field;

class RelationHandler
{
    public function handleRelations(Model $model, array &$validatedData): void
    {
        foreach ($validatedData as $attribute => $value) {
            $field = $this->getField($model, $attribute);

            if ($field instanceof Field) {
                $relationType = $field->getRelationType();

                match ($relationType) {
                    'hasOne' => $this->handleHasOne($model, $field, $value),
                    'hasMany' => $this->handleHasMany($model, $field, $value),
                    'belongsTo' => $this->handleBelongsTo($model, $field, $value),
                    default => $model->{$attribute} = $value,
                };
            } else {
                $model->{$attribute} = $value;
            }
        }
    }

    protected function handleHasOne(Model $model, Field $field, mixed $value): void
    {
        $relatedModel = $model->{$field->getAttribute()}()->firstOrNew([]);
        $relatedModel->fill($value)->save();
        $model->setRelation($field->getAttribute(), $relatedModel);
    }

    protected function handleHasMany(Model $model, Field $field, array $values): void
    {
        $model->{$field->getAttribute()}()->delete();
        foreach ($values as $value) {
            $model->{$field->getAttribute()}()->create($value);
        }
    }

    protected function handleBelongsTo(Model $model, Field $field, mixed $value): void
    {
        $foreignKey = $model->{$field->getAttribute()}()->getForeignKeyName();
        $model->{$foreignKey} = $value;
    }

    protected function getField(Model $model, string $attribute): ?Field
    {
        return method_exists($model, $attribute) ? $model->{$attribute}() : null;
    }
}
