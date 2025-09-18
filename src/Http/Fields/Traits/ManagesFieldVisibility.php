<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use Illuminate\Support\Collection;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

trait ManagesFieldVisibility
{
    /**
     * Get fields visible in the index /list view.
     */
    public function fieldsForIndex(NadotaRequest $request): Collection
    {
        return $this->getFieldsFilteredByVisibility($request, 'isShowOnIndex');
    }

    /**
     * Get fields visible in the show / detail view.
     */
    public function fieldsForShow(NadotaRequest $request, string $action): Collection
    {
        $method = $action === 'show' ? 'isShowOnDetail' : 'isShowOnUpdate';
        return $this->getFieldsFilteredByVisibility($request, $method);
    }

    /**
     * Get fields visible in create/edit forms.
     */
    public function fieldsForForm(NadotaRequest $request, $model = null): Collection
    {
        $method = $model ? 'isShowOnUpdate' : 'isShowOnCreation';
        return $this->getFieldsFilteredByVisibility($request, $method);
    }

    /**
     * Filter fields by visibility method.
     */
    protected function getFieldsFilteredByVisibility(NadotaRequest $request, string $visibilityMethod): Collection
    {
        return collect($this->fields($request))
            ->filter(fn($field) => $field->{$visibilityMethod}())
            ->values();
    }
}