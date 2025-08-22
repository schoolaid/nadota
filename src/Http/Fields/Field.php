<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Contracts\FieldInterface;
use SchoolAid\Nadota\Http\Fields\DataTransferObjects\FieldDTO;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Traits\{DefaultValueTrait,
    FilterableTrait,
    RelationshipTrait,
    SearchableTrait,
    SortableTrait,
    ValidationTrait,
    VisibilityTrait};
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Traits\Makeable;

abstract class Field implements FieldInterface
{
    use RelationshipTrait;
    use DefaultValueTrait;
    use FilterableTrait;
    use Makeable;
    use SearchableTrait;
    use SortableTrait;
    use ValidationTrait;
    use VisibilityTrait;

    protected FieldDTO $fieldData;

    public function __construct(string $name, string $attribute)
    {
        $this->fieldData = new FieldDTO(
            name: $name,
            label: $name,
            id: str_replace(' ', '_', $name),
            attribute: $attribute,
            placeholder: $name,
            type: FieldType::TEXT,
            component: 'field'
        );
    }

    public function label(string $label): static
    {
        $this->fieldData->label = $label;
        return $this;
    }

    public function type(FieldType $type): static
    {
        $this->fieldData->type = $type;
        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->fieldData->placeholder = $placeholder;
        return $this;
    }

    public function component(string $component): static
    {
        $this->fieldData->component = $component;
        return $this;
    }

    public function key(): string
    {
        return str_replace(' ', '', strtolower($this->fieldData->name));
    }

    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $value = $model->{$this->getAttribute()};
        
        // If model has a value, use it; otherwise use default if available
        if ($value !== null) {
            return $value;
        }
        
        if ($this->hasDefault()) {
            return $this->resolveDefault($request, $model, $resource);
        }

        return $value; // null
    }

    public function getAttribute(): string
    {
        return $this->fieldData->attribute;
    }

    public function getType(): FieldType
    {
        return $this->fieldData->type;
    }

    public function getName(): string
    {
        return $this->fieldData->name;
    }

    public function getLabel(): string
    {
        return $this->fieldData->label;
    }

    public function getPlaceholder(): string
    {
        return $this->fieldData->placeholder;
    }

    public function getComponent(): string
    {
        return $this->fieldData->component;
    }

    public function getId(): string
    {
        return $this->fieldData->id;
    }

    public function toArray(NadotaRequest $request, ?Model $model = null, ?ResourceInterface $resource = null): array
    {
        $data = array_merge($this->fieldData->toArray(), [
            'key' => $this->key(),
            'required' => $this->isRequired(),
            'sortable' => $this->isSortable(),
            'searchable' => $this->isSearchable(),
            'filterable' => $this->isFilterable(),
            'showOnIndex' => $this->isShowOnIndex($request, $model),
            'showOnDetail' => $this->isShowOnDetail($request, $model),
            'showOnCreation' => $this->isShowOnCreation($request, $model),
            'showOnUpdate' => $this->isShowOnUpdate($request, $model),
            'props' => $this->getProps($request, $model, $resource),
            'rules' => $this->getRules(),
        ]);

        if ($model) {
            $data['value'] = $this->resolve($request, $model, $resource);
        }

        return $data;
    }

    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        $props = [];
        
        // Add search weight if available
        if (method_exists($this, 'getSearchWeight') && $this->getSearchWeight() !== null) {
            $props['searchWeight'] = $this->getSearchWeight();
        }
        
        return $props;
    }

    /**
     * Magic getter for accessing protected properties in tests.
     */
    public function __get($name)
    {
        if ($name === 'fieldData') {
            return $this->fieldData;
        }
        
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        
        throw new \InvalidArgumentException("Property {$name} does not exist on " . static::class);
    }
}
