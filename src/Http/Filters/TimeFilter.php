<?php

namespace SchoolAid\Nadota\Http\Filters;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class TimeFilter extends Filter
{
    protected bool $isRange = false;

    /**
     * Create a new filter instance.
     *
     * @param string|null $name
     * @param string|null $field
     * @param string|null $type
     * @param string|null $component
     * @param mixed|null $id
     * @param bool $isRange
     */
    public function __construct(string $name = null, string $field = null, string $type = 'time', string $component = null, $id = null, bool $isRange = false)
    {
        $this->isRange = $isRange;
        $componentName = $component ?? ($isRange ? 'FilterTimeRange' : 'FilterTimeField');
        parent::__construct($name, $field, $type, $componentName, $id);
    }

    /**
     * Set the filter to range mode.
     *
     * @param bool $isRange
     * @return static
     */
    public function range(bool $isRange = true): static
    {
        $this->isRange = $isRange;
        $this->component = $isRange ? 'FilterTimeRange' : 'FilterTimeField';
        return $this;
    }

    /**
     * Apply the filter to the given query.
     *
     * @param NadotaRequest $request
     * @param mixed $query
     * @param mixed $value
     * @return mixed
     */
    public function apply(NadotaRequest $request, $query, $value)
    {
        if ($this->isRange && is_array($value)) {
            $start = $value['start'] ?? $value[0] ?? null;
            $end = $value['end'] ?? $value[1] ?? null;

            if ($start && $end) {
                return $query->whereBetween($this->field, [$start, $end]);
            } elseif ($start) {
                return $query->where($this->field, '>=', $start);
            } elseif ($end) {
                return $query->where($this->field, '<=', $end);
            }
        } else {
            // Single time filter
            if ($value) {
                return $query->whereTime($this->field, $value);
            }
        }

        return $query;
    }

    /**
     * Get the properties for the filter.
     *
     * @return array
     */
    public function props(): array
    {
        return array_merge(parent::props(), [
            'isRange' => $this->isRange,
        ]);
    }

    /**
     * Check if the filter is in range mode.
     *
     * @return bool
     */
    public function isRange(): bool
    {
        return $this->isRange;
    }

    /**
     * Get the filter keys for this filter.
     *
     * @return array
     */
    public function getFilterKeys(): array
    {
        if ($this->isRange) {
            return [
                'from' => "{$this->field}_from",
                'to' => "{$this->field}_to",
            ];
        }

        return parent::getFilterKeys();
    }
}
