<?php

namespace SchoolAid\Nadota\Http\Filters;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class SelectFilter extends Filter
{
    protected array $options = [];
    protected bool $translateLabels = false;

    public function __construct(string $name = null, string $field = null, string $type = null, string $component = null, $id = null)
    {
        parent::__construct($name, $field, $type ?? 'select', $component ?? 'FilterSelect', $id);
    }

    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    /**
     * Set whether the frontend should translate option labels.
     * Default is false for filters.
     */
    public function translateLabels(bool $translate = true): static
    {
        $this->translateLabels = $translate;
        return $this;
    }

    /**
     * Enable label translation on the frontend.
     */
    public function withTranslation(): static
    {
        return $this->translateLabels(true);
    }

    public function apply(NadotaRequest $request, $query, $value)
    {
        if (is_array($value)) {
            return $query->whereIn($this->field, $value);
        }

        return $query->where($this->field, $value);
    }

    public function resources(NadotaRequest $request): array
    {
        return $this->options;
    }

    public function props(): array
    {
        return array_merge(parent::props(), [
            'translateLabels' => $this->translateLabels,
        ]);
    }
}

