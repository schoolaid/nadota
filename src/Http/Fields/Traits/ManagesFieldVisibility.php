<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use Illuminate\Support\Collection;
use SchoolAid\Nadota\Http\Fields\Section;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

trait ManagesFieldVisibility
{
    /**
     * Get fields visible in the index /list view.
     * Note: Sections are not used in index, only flat fields.
     */
    public function fieldsForIndex(NadotaRequest $request): Collection
    {
        return $this->getFlatFieldsFilteredByVisibility($request, 'isShowOnIndex');
    }

    /**
     * Get fields visible in the show / detail view.
     * Returns flat collection of fields (without section structure).
     */
    public function fieldsForShow(NadotaRequest $request, string $action): Collection
    {
        $method = $action === 'show' ? 'isShowOnDetail' : 'isShowOnUpdate';
        return $this->getFlatFieldsFilteredByVisibility($request, $method);
    }

    /**
     * Get fields and sections with structure for show / detail view.
     * Returns collection preserving section hierarchy.
     */
    public function sectionsForShow(NadotaRequest $request, string $action): Collection
    {
        $method = $action === 'show' ? 'isShowOnDetail' : 'isShowOnUpdate';
        return $this->getStructuredFieldsFilteredByVisibility($request, $method);
    }

    /**
     * Get fields and sections with structure for forms.
     * Returns collection preserving section hierarchy.
     */
    public function sectionsForForm(NadotaRequest $request, $model = null): Collection
    {
        $method = $model ? 'isShowOnUpdate' : 'isShowOnCreation';
        return $this->getStructuredFieldsFilteredByVisibility($request, $method);
    }

    /**
     * Get fields visible in create/edit forms.
     * Returns flat collection of fields (without section structure).
     */
    public function fieldsForForm(NadotaRequest $request, $model = null): Collection
    {
        $method = $model ? 'isShowOnUpdate' : 'isShowOnCreation';
        return $this->getFlatFieldsFilteredByVisibility($request, $method);
    }

    /**
     * Extract all fields from fields array, flattening sections.
     * Uses memoization to avoid repeated flattening operations.
     */
    public function flattenFields(NadotaRequest $request): Collection
    {
        // Use memoization if available (from MemoizesFields trait)
        if (method_exists($this, 'getMemoizedFlattenedFields')) {
            return $this->getMemoizedFlattenedFields($request);
        }

        // Fallback to direct computation if memoization not available
        return collect($this->fields($request))
            ->flatMap(function ($item) {
                if ($item instanceof Section) {
                    return $item->getFields();
                }
                return [$item];
            })
            ->values();
    }

    /**
     * Filter flat fields by visibility method.
     * Sections are flattened - their fields are extracted.
     */
    protected function getFlatFieldsFilteredByVisibility(NadotaRequest $request, string $visibilityMethod): Collection
    {
        return $this->flattenFields($request)
            ->filter(fn($field) => $field->{$visibilityMethod}())
            ->values();
    }

    /**
     * Filter fields/sections by visibility, preserving structure.
     * Sections with no visible fields are excluded.
     * Loose fields are grouped into a default section.
     */
    protected function getStructuredFieldsFilteredByVisibility(NadotaRequest $request, string $visibilityMethod): Collection
    {
        // Use memoized fields if available to avoid repeated fields() calls
        $fields = method_exists($this, 'getMemoizedFields') 
            ? $this->getMemoizedFields($request) 
            : $this->fields($request);
        
        $items = collect($fields);
        $result = collect();
        $looseFields = collect();

        foreach ($items as $item) {
            if ($item instanceof Section) {
                // Flush loose fields before section
                if ($looseFields->isNotEmpty()) {
                    $result->push([
                        'type' => 'default',
                        'fields' => $looseFields->values()->toArray(),
                    ]);
                    $looseFields = collect();
                }

                // Check if section itself is visible
                if (!$item->{$visibilityMethod}()) {
                    continue;
                }

                // Filter visible fields within section
                $visibleFields = collect($item->getFields())
                    ->filter(fn($field) => $field->{$visibilityMethod}())
                    ->values();

                // Only include section if it has visible fields
                if ($visibleFields->isNotEmpty()) {
                    $result->push([
                        'type' => 'section',
                        'title' => $item->getTitle(),
                        'icon' => $item->getIcon(),
                        'description' => $item->getDescription(),
                        'collapsible' => $item->isCollapsible(),
                        'collapsed' => $item->isCollapsed(),
                        'fields' => $visibleFields->toArray(),
                    ]);
                }
            } else {
                // Regular field - check visibility
                if ($item->{$visibilityMethod}()) {
                    $looseFields->push($item);
                }
            }
        }

        // Flush remaining loose fields
        if ($looseFields->isNotEmpty()) {
            $result->push([
                'type' => 'default',
                'fields' => $looseFields->values()->toArray(),
            ]);
        }

        return $result;
    }

    /**
     * Get sections layout configuration for a specific view context.
     * Returns only metadata and field keys (no values).
     * Used by /config endpoint.
     *
     * @param NadotaRequest $request
     * @param string $context 'detail', 'create', or 'update'
     * @return array
     */
    public function getSectionsLayout(NadotaRequest $request, string $context = 'detail'): array
    {
        $visibilityMethod = match ($context) {
            'create' => 'isShowOnCreation',
            'update' => 'isShowOnUpdate',
            default => 'isShowOnDetail',
        };

        // Use memoized fields if available to avoid repeated fields() calls
        $fields = method_exists($this, 'getMemoizedFields') 
            ? $this->getMemoizedFields($request) 
            : $this->fields($request);
        
        $items = collect($fields);
        $result = [];
        $looseFieldKeys = [];

        foreach ($items as $item) {
            if ($item instanceof Section) {
                // Flush loose fields before section
                if (!empty($looseFieldKeys)) {
                    $result[] = [
                        'type' => 'default',
                        'fieldKeys' => $looseFieldKeys,
                    ];
                    $looseFieldKeys = [];
                }

                // Check if section itself is visible
                if (!$item->{$visibilityMethod}()) {
                    continue;
                }

                // Get visible field keys within section
                $visibleFieldKeys = collect($item->getFields())
                    ->filter(fn($field) => $field->{$visibilityMethod}())
                    ->map(fn($field) => $field->key())
                    ->values()
                    ->toArray();

                // Only include section if it has visible fields
                if (!empty($visibleFieldKeys)) {
                    $result[] = [
                        'type' => 'section',
                        'title' => $item->getTitle(),
                        'icon' => $item->getIcon(),
                        'description' => $item->getDescription(),
                        'collapsible' => $item->isCollapsible(),
                        'collapsed' => $item->isCollapsed(),
                        'fieldKeys' => $visibleFieldKeys,
                    ];
                }
            } else {
                // Regular field - check visibility
                if ($item->{$visibilityMethod}()) {
                    $looseFieldKeys[] = $item->key();
                }
            }
        }

        // Flush remaining loose fields
        if (!empty($looseFieldKeys)) {
            $result[] = [
                'type' => 'default',
                'fieldKeys' => $looseFieldKeys,
            ];
        }

        return $result;
    }
}