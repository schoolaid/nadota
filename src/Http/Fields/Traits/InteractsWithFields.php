<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use Illuminate\Support\Collection;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

trait InteractsWithFields
{
    public function getAttributesForSelect($request): array
    {
        $attributes = [...collect($this->fields($request))
            ->filter(fn($field) => $field->isAppliedInShowQuery() && $field->getType() != FieldType::HAS_MANY->value)
            ->map(fn($field) => $field->getAttribute())
            ->toArray(), $this::$attributeKey];

        $attributesMorph = collect($this->fields($request))
            ->filter(fn($field) => $field->getType() == FieldType::MORPH_TO->value)
            ->map(fn($field) => $field->getMorphTypeAttribute())
            ->toArray();

        return [...$attributes, ...$attributesMorph];
    }

    public function getRelationAttributesForSelect($request)
    {
        return collect($this->fields($request))
            ->filter(fn($field) => $field->isAppliedInShowQuery() && $field->isRelationship())
            ->mapWithKeys(function ($field) use ($request) {
                // Get the relation name
                $relationName = $field->getRelation();

                // For regular relations, apply select constraints if resource is defined
                if($field->getResource()){
                    $resourceClass = $field->getResource();
                    $resource = new $resourceClass;
                    $fields = $resource->getAttributesForSelect($request);

                    return [$relationName => fn($query) => $query->select($fields)];
                }

                // Default case - just load the relation
                return [$relationName];
            })
            ->toArray();
    }


    public function fieldsForIndex(NadotaRequest $request): Collection
    {
        return collect($this->fields($request))
            ->filter(fn($field) => $field->isShowOnIndex())
            ->values();
    }

    public function transformForIndex($item, NadotaRequest $request, $actions, $fields): array
    {
        return [
            'id' => $item[$this::$attributeKey],
            'attributes' => $fields->map(function ($field) use ($item, $request) {
                return $field->toArray($request, $item, $this);
            }),
            'deletedAt' => $item->deleted_at ?? null,
            'permissions' => $this->getPermissionsForResource($request, $item),
            'actions' => $actions,
        ];
    }

    public function fieldsForShow(NadotaRequest $request, $action): Collection
    {
        return collect($this->fields($request))
            ->filter(fn($field) => $action == 'show' ? $field->isShowOnDetail() : $field->isShowOnUpdate())
            ->values();
    }

    public function fieldsForForm(NadotaRequest $request, $model = null): Collection
    {
        return collect($this->fields($request))
            ->filter(fn($field) => $model ? $field->isShowOnUpdate() : $field->isShowOnCreation())
            ->values();
    }
}
