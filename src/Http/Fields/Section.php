<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Traits\VisibilityTrait;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Traits\Makeable;

class Section
{
    use Makeable;
    use VisibilityTrait;

    protected string $title;
    protected array $fields;
    protected ?string $icon = null;
    protected ?string $description = null;
    protected bool $collapsible = false;
    protected bool $collapsed = false;

    public function __construct(string $title, array $fields = [])
    {
        $this->title = $title;
        $this->fields = $fields;
    }

    /**
     * Set the section icon.
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * Set the section description.
     */
    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Make the section collapsible.
     */
    public function collapsible(bool $collapsible = true): static
    {
        $this->collapsible = $collapsible;
        return $this;
    }

    /**
     * Set the section to start collapsed.
     * Automatically enables collapsible.
     */
    public function collapsed(bool $collapsed = true): static
    {
        $this->collapsed = $collapsed;
        if ($collapsed) {
            $this->collapsible = true;
        }
        return $this;
    }

    /**
     * Get the section title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get the section fields.
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Get the section icon.
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * Get the section description.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Check if section is collapsible.
     */
    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }

    /**
     * Check if section starts collapsed.
     */
    public function isCollapsed(): bool
    {
        return $this->collapsed;
    }

    /**
     * Check if this is a Section instance.
     */
    public function isSection(): bool
    {
        return true;
    }

    /**
     * Filter fields by visibility method and return visible fields.
     */
    public function getVisibleFields(string $visibilityMethod): array
    {
        return collect($this->fields)
            ->filter(fn($field) => $field->{$visibilityMethod}())
            ->values()
            ->toArray();
    }

    /**
     * Transform section to array for JSON response.
     */
    public function toArray(
        NadotaRequest $request,
        ?Model $model = null,
        ?ResourceInterface $resource = null,
        ?string $visibilityMethod = null
    ): array {
        $fields = $visibilityMethod
            ? $this->getVisibleFields($visibilityMethod)
            : $this->fields;

        return [
            'type' => 'section',
            'title' => $this->title,
            'icon' => $this->icon,
            'description' => $this->description,
            'collapsible' => $this->collapsible,
            'collapsed' => $this->collapsed,
            'fields' => collect($fields)->map(function ($field) use ($request, $model, $resource) {
                return $field->toArray($request, $model, $resource);
            })->values()->toArray(),
        ];
    }
}
