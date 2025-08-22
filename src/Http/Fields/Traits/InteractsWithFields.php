<?php

namespace Said\Nadota\Http\Fields\Traits;

use Illuminate\Support\Collection;
use Said\Nadota\Http\Requests\NadotaRequest;

trait InteractsWithFields
{
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

    public function fieldsForForm(NadotaRequest $request, $model = null): Collection
    {
        return collect($this->fields($request))
            ->filter(fn($field) => $model ? $field->isShowOnUpdate() : $field->isShowOnCreation())
            ->values();
    }
}
