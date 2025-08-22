<?php

namespace Said\Nadota\Http\Fields\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Said\Nadota\Contracts\ResourceInterface;
use Said\Nadota\Http\Fields\Field;
use Said\Nadota\Http\Requests\NadotaRequest;
use Said\Nadota\ResourceManager;

abstract class RelationField extends Field
{
    protected ?string $relatedModelClass = null;
    protected ?string $relatedResourceClass = null;
    protected array $options = [];

    public function __construct(?string $name, ?string $relation)
    {
        parent::__construct($name, $relation);
        $this->relation = $relation;

    }

    abstract protected function relationType(): string;

    public function relatedModel(string $modelClass): static
    {
        $this->relatedModelClass = $modelClass;
        return $this;
    }

    public function relatedResource(string $resourceClass): static
    {
        $this->relatedResourceClass = $resourceClass;
        $this->relatedModelClass = ResourceManager::getModelByResource($resourceClass);
        return $this;
    }

    public function resolve(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model, ?\Said\Nadota\Contracts\ResourceInterface $resource): mixed
    {
        $related = $model->{$this->getRelation()};

        if (!$related instanceof Model) {
            return null;
        }

        return [
            'key' => $related->{$this->getForeignKey()},
            'label' => $related->{$this->getAttributeForDisplay()},
        ];
    }

    public function toArray(NadotaRequest $request, ?Model $model = null, ?ResourceInterface $resource = null): array
    {
        $data = parent::toArray($request, $model, $resource);

        $data['attribute'] = $this->getRelation();
        $data['relationType'] = $this->relationType();
        if(!$request->route() || !str_contains($request->route()->getName(),'resource.index')){
            $data['options'] = $this->getOptions();
        }
        if ($this->relatedResourceClass) {
            $value = $data['value']['value'] ?? '';
            $data['apiUrl'] = $this->getApiUrl($value);
            $data['frontendUrl'] = $this->getFrontendUrl($value);
        }

        return $data;
    }


    protected function getApiUrl(string $value): ?string
    {
        return $this->relatedResourceClass
            ? $this->relatedResourceClass::make()->apiUrl() . '/' . $value
            : null;
    }

    protected function getFrontendUrl(string $value): ?string
    {
        return $this->relatedResourceClass
            ? $this->relatedResourceClass::make()->frontendUrl() . '/' . $value
            : null;
    }

    public function getRules(): array
    {
        if ($this->relatedModelClass) {
            $table = (new $this->relatedModelClass)->getTable();
            return [...parent::getRules(), "exists:{$table},{$this->getForeignKey()}"];
        }

        return parent::getRules();
    }

    public function applySorting(Builder $query, $sortDirection, $modelInstance): Builder
    {
        return $query;
    }

    /**
     * Get the attribute used for display in the relationship.
     */
    public function getAttributeForDisplay(): string
    {
        return $this->relationAttribute ?? 'name';
    }

    /**
     * Get the foreign key for the relationship.
     */
    public function getForeignKey(): string
    {
        return 'id';
    }

    /**
     * Get the relation name.
     */
    public function getRelation(): string
    {
        return $this->relation ?? $this->attribute;
    }

    /**
     * Get the related model class.
     */
    public function getRelatedModelClass(): ?string
    {
        return $this->relatedModelClass;
    }

    /**
     * Magic getter for accessing protected properties in tests.
     */
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        
        return parent::__get($name);
    }

    /**
     * Make getOptions method public for external access.
     */
    public function getOptions(): array
    {
        if (empty($this->options) && $this->relatedModelClass) {
            $this->options = $this->relatedModelClass::query()
                ->pluck($this->getAttributeForDisplay(), $this->getForeignKey())
                ->toArray();
        }

        return collect($this->options)
            ->map(fn($name, $id) => ['value' => $id, 'label' => $name])
            ->values()
            ->toArray();
    }
}
