<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Filters\BooleanFilter;
use SchoolAid\Nadota\Http\Filters\DateFilter;
use SchoolAid\Nadota\Http\Filters\DefaultFilter;
use SchoolAid\Nadota\Http\Filters\DynamicSelectFilter;
use SchoolAid\Nadota\Http\Filters\MorphToFilter;
use SchoolAid\Nadota\Http\Filters\NumberFilter;
use SchoolAid\Nadota\Http\Filters\RangeFilter;
use SchoolAid\Nadota\Http\Filters\SelectFilter;

trait FilterableTrait
{
    protected bool $filterable = false;
    protected string $filterableType = 'text';
    protected bool $filterAsRange = false;

    /**
     * Tipos de fields que son relaciones y no deben tener filtros automáticos
     * (excepto belongsTo que sí puede tener filtro automático)
     */
    protected array $relationTypes = [
        FieldType::HAS_MANY->value,
        FieldType::HAS_ONE->value,
        FieldType::BELONGS_TO_MANY->value,
        FieldType::MORPH_TO->value,
        FieldType::MORPH_MANY->value,
        FieldType::MORPH_ONE->value,
    ];

    public function filterable(): static
    {
        $this->filterable = true;
        $this->filterableType = $this->fieldData->type;
        return $this;
    }

    public function filterableRange(): static
    {
        $this->filterable = true;
        $this->filterAsRange = true;
        return $this;
    }

    public function isFilterable(): bool
    {
        return $this->filterable;
    }

    public function isFilterableRange(): bool
    {
        return $this->filterAsRange;
    }

    /**
     * Get the filter keys for this field.
     * For range filters, returns 'from' and 'to' keys.
     * For single filters, returns the attribute key.
     */
    public function getFilterKeys(): array
    {
        $attribute = $this->fieldData->attribute;

        if ($this->filterAsRange) {
            return [
                'from' => "{$attribute}_from",
                'to' => "{$attribute}_to",
            ];
        }

        return [
            'value' => $attribute,
        ];
    }

    public function filters(): array
    {
        $filters = [];
        
        if (!$this->filterable) {
            return $filters;
        }

        // No crear filtros para relaciones (excepto belongsTo y morphTo)
        if (in_array($this->fieldData->type, $this->relationTypes) && 
            $this->fieldData->type !== FieldType::MORPH_TO->value) {
            return $filters;
        }

        // MorphTo genera múltiples filtros
        if ($this->fieldData->type === FieldType::MORPH_TO->value) {
            return $this->createMorphToFilters();
        }

        $filter = $this->createFilterForFieldType();
        
        if ($filter) {
            $filters[] = $filter;
        }

        return $filters;
    }

    /**
     * Crea el filtro apropiado según el tipo de field
     */
    protected function createFilterForFieldType(): ?\SchoolAid\Nadota\Http\Filters\Filter
    {
        $type = $this->fieldData->type;
        $label = $this->fieldData->label;
        $attribute = $this->fieldData->attribute;

        // Si se especificó explícitamente como range
        if ($this->filterAsRange) {
            return $this->createRangeFilter($type, $label, $attribute);
        }

        // Mapeo de tipos de fields a filtros
        return match ($type) {
            // Relaciones belongsTo - DynamicSelectFilter
            FieldType::BELONGS_TO->value => $this->createDynamicSelectFilter($label, $attribute, $type),

            // Campos de texto - DefaultFilter
            FieldType::TEXT->value,
            FieldType::TEXTAREA->value,
            FieldType::EMAIL->value,
            FieldType::URL->value,
            FieldType::PASSWORD->value => new DefaultFilter($label, $attribute, $type),

            // Campos numéricos - NumberFilter (por defecto exacto, puede ser range)
            FieldType::NUMBER->value => new NumberFilter($label, $attribute, $type, null, null, false),

            // Campos de fecha/hora - DateFilter (por defecto single, puede ser range)
            FieldType::DATE->value,
            FieldType::DATETIME->value,
            FieldType::TIME->value => new DateFilter($label, $attribute, $type, null, null, false),

            // Campos booleanos - BooleanFilter
            FieldType::BOOLEAN->value,
            FieldType::CHECKBOX->value => $this->createBooleanFilter($label, $attribute, $type),

            // Campos de selección - SelectFilter
            FieldType::SELECT->value,
            FieldType::RADIO->value,
            FieldType::CHECKBOX_LIST->value => $this->createSelectFilter($label, $attribute, $type),

            // Campos que no deberían ser filtrables por defecto
            FieldType::HIDDEN->value,
            FieldType::FILE->value,
            FieldType::IMAGE->value,
            FieldType::JSON->value,
            FieldType::CODE->value,
            FieldType::CUSTOM_COMPONENT->value,
            FieldType::KEY_VALUE->value,
            FieldType::ARRAY->value,
            FieldType::HTML->value => null,

            // Por defecto, usar DefaultFilter
            default => new DefaultFilter($label, $attribute, $type),
        };
    }

    /**
     * Crea un filtro de rango según el tipo
     */
    protected function createRangeFilter(string $type, string $name, string $attribute): ?\SchoolAid\Nadota\Http\Filters\Filter
    {
        return match ($type) {
            FieldType::NUMBER->value => new NumberFilter($name, $attribute, $type, null, null, true),
            FieldType::DATE->value,
            FieldType::DATETIME->value,
            FieldType::TIME->value => new DateFilter($name, $attribute, $type, null, null, true),
            default => new RangeFilter($name, $attribute, $type),
        };
    }

    /**
     * Crea un BooleanFilter, intentando obtener los valores true/false del field si están disponibles
     */
    protected function createBooleanFilter(string $name, string $attribute, string $type): BooleanFilter
    {
        $filter = new BooleanFilter($name, $attribute, $type);

        // Intentar obtener los valores true/false del field usando reflexión
        try {
            $reflection = new \ReflectionClass($this);
            
            if ($reflection->hasProperty('trueValue')) {
                $property = $reflection->getProperty('trueValue');
                $property->setAccessible(true);
                $trueValue = $property->getValue($this);
                if ($trueValue !== null) {
                    $filter->trueValue($trueValue);
                }
            }
            
            if ($reflection->hasProperty('falseValue')) {
                $property = $reflection->getProperty('falseValue');
                $property->setAccessible(true);
                $falseValue = $property->getValue($this);
                if ($falseValue !== null) {
                    $filter->falseValue($falseValue);
                }
            }
        } catch (\ReflectionException $e) {
            // Si falla la reflexión, usar valores por defecto
        }

        return $filter;
    }

    /**
     * Crea un SelectFilter, intentando obtener las opciones del field si están disponibles
     */
    protected function createSelectFilter(string $name, string $attribute, string $type): SelectFilter
    {
        $filter = new SelectFilter($name, $attribute, $type);

        // Primero intentar usar el método getOptions() del field
        $options = $this->getOptions();
        
        // Si no hay opciones, intentar obtenerlas de la propiedad usando reflexión
        if (empty($options)) {
            try {
                $reflection = new \ReflectionClass($this);
                
                if ($reflection->hasProperty('options')) {
                    $property = $reflection->getProperty('options');
                    $property->setAccessible(true);
                    $options = $property->getValue($this);
                }
            } catch (\ReflectionException $e) {
                // Si falla la reflexión, continuar sin opciones
            }
        }

        if (!empty($options)) {
            $filter->options($options);
        }

        return $filter;
    }

    /**
     * Crea un DynamicSelectFilter para relaciones belongsTo, obteniendo información de la relación del field
     */
    protected function createDynamicSelectFilter(string $name, string $attribute, string $type): DynamicSelectFilter
    {
        // Para BelongsTo, necesitamos resolver la FK real del modelo
        // porque el atributo puede no coincidir con la FK real
        // Ejemplo: BelongsTo::make('Family', 'family') puede usar 'user_id' como FK, no 'family_id'
        $filterAttribute = $attribute;

        // Intentar resolver la FK real si este es un BelongsTo
        if (method_exists($this, 'resolveForeignKeyFromModel')) {
            try {
                // Necesitamos obtener el modelo del recurso padre
                // Usamos el key del field como fallback para el filter attribute
                $filterAttribute = $this->key();
            } catch (\Throwable $e) {
                // Usar attribute original
            }
        }

        $filter = new DynamicSelectFilter($name, $filterAttribute, $type);

        // Intentar obtener información de la relación usando reflexión
        try {
            $reflection = new \ReflectionClass($this);

            // Obtener el nombre de la relación para whereHas
            if ($reflection->hasProperty('relation')) {
                $property = $reflection->getProperty('relation');
                $property->setAccessible(true);
                $relation = $property->getValue($this);
                if ($relation) {
                    $filter->relation($relation);
                }
            }

            // Obtener el atributo de display para labelField
            if (method_exists($this, 'getAttributeForDisplay')) {
                $displayAttribute = $this->getAttributeForDisplay();
                if ($displayAttribute) {
                    $filter->labelField($displayAttribute);
                }
            }

            // Nota: NO establecemos resourceKey aquí.
            // El endpoint se construirá dinámicamente usando request->getResource()
            // que obtiene el recurso ACTUAL (ej: student), no el relacionado (ej: family).
            // La ruta correcta es: /nadota-api/{currentResource}/resource/field/{fieldKey}/options
        } catch (\ReflectionException $e) {
            // Si falla la reflexión, continuar con valores por defecto
        }

        // Habilitar búsqueda por defecto para belongsTo
        $filter->searchable(true);

        return $filter;
    }

    /**
     * Crea los filtros para relaciones MorphTo (tipo + entidad)
     */
    protected function createMorphToFilters(): array
    {
        try {
            $reflection = new \ReflectionClass($this);
            
            // Obtener información de morph
            $morphTypeAttribute = null;
            $morphIdAttribute = $this->fieldData->attribute;
            $morphTypes = [];
            $resourceKey = null;

            // Obtener morphTypeAttribute
            if ($reflection->hasProperty('morphTypeAttribute')) {
                $property = $reflection->getProperty('morphTypeAttribute');
                $property->setAccessible(true);
                $morphTypeAttribute = $property->getValue($this);
            }

            // Obtener morphTypes
            if ($reflection->hasProperty('morphModels')) {
                $property = $reflection->getProperty('morphModels');
                $property->setAccessible(true);
                $morphModels = $property->getValue($this);
                
                // Obtener morphResources para labels
                $morphResources = [];
                if ($reflection->hasProperty('morphResources')) {
                    $property = $reflection->getProperty('morphResources');
                    $property->setAccessible(true);
                    $morphResources = $property->getValue($this);
                }

                // Formatear morphTypes
                foreach ($morphModels as $alias => $modelClass) {
                    $label = null;
                    if (isset($morphResources[$alias])) {
                        $resourceClass = $morphResources[$alias];
                        if (method_exists($resourceClass, 'label')) {
                            $label = $resourceClass::label();
                        }
                    }
                    
                    if (!$label) {
                        $label = ucfirst(str_replace(['_', '-'], ' ', $alias));
                    }

                    $morphTypes[$alias] = [
                        'model' => $modelClass,
                        'label' => $label,
                        'resource' => $morphResources[$alias] ?? null,
                    ];
                }
            }

            // Obtener resourceKey del resource padre
            if (method_exists($this, 'getResource')) {
                $parentResource = $this->getResource();
                if ($parentResource && method_exists($parentResource, 'getKey')) {
                    $resourceKey = $parentResource::getKey();
                }
            }

            if (!$morphTypeAttribute || empty($morphTypes)) {
                return [];
            }

            // Crear MorphToFilter y generar los dos filtros
            $morphFilter = new MorphToFilter(
                $this->fieldData->label,
                $morphTypeAttribute,
                $morphIdAttribute,
                $morphTypes,
                $resourceKey
            );

            return $morphFilter->generateFilters();

        } catch (\ReflectionException $e) {
            // Si falla la reflexión, no generar filtros
            return [];
        }
    }
}
