# Custom Fields - Nadota

Guia para crear campos personalizados que extienden la funcionalidad base de Nadota.

---

## Navegacion Rapida

| [Cuando Usar](#cuando-usar-custom-fields) | [Estructura Basica](#estructura-basica) | [Validacion Anidada](#validacion-anidada) | [After Save](#after-save) | [Ejemplo Completo](#ejemplo-completo-inline-hasmany) |

---

## Cuando Usar Custom Fields

Usa campos custom cuando necesites:

1. **Formularios inline para relaciones HasMany** - Crear items relacionados durante la creacion del padre
2. **Logica de negocio especifica** - Transformaciones o validaciones complejas
3. **Componentes UI personalizados** - Campos que no se ajustan a los tipos existentes
4. **Integracion con servicios externos** - APIs, procesamiento de archivos, etc.

---

## Estructura Basica

Un custom field extiende la clase `Field`:

```php
<?php

namespace App\Nadota\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Field;

class MyCustomField extends Field
{
    public function __construct(?string $name, string $attribute)
    {
        parent::__construct(
            $name,           // Label del campo
            $attribute,      // Atributo en el request
            'custom',        // Tipo interno
            'my-component'   // Componente Vue/React
        );

        // Configurar visibilidad
        $this->showOnIndex = false;
        $this->showOnDetail = true;
        $this->showOnCreation = true;
        $this->showOnUpdate = true;
    }

    /**
     * Reglas de validacion para el campo principal.
     */
    public function getRules(): array
    {
        return ['required', 'array'];
    }

    /**
     * Llenar el modelo (antes de save).
     */
    public function fill(Request $request, Model $model): void
    {
        // Para campos que se manejan en afterSave, dejar vacio
    }

    /**
     * Resolver el valor para mostrar.
     */
    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        return $model->{$this->getAttribute()} ?? [];
    }
}
```

---

## Validacion Anidada

Para validar datos con estructura de array (ej: items de una relacion), implementa `getNestedRules()`:

### Metodo getNestedRules()

```php
class InternalsField extends Field
{
    /**
     * Reglas para el campo principal (el array).
     */
    public function getRules(): array
    {
        return ['required', 'array', 'min:1', 'max:10'];
    }

    /**
     * Reglas para cada item dentro del array.
     * Formato: 'atributo.*.campo' => reglas
     */
    public function getNestedRules(): array
    {
        $attribute = $this->getAttribute(); // ej: 'internals_data'

        return [
            "{$attribute}.*.name" => ['required', 'string', 'max:255'],
            "{$attribute}.*.document" => ['required', 'string', 'max:50'],
            "{$attribute}.*.phone" => ['nullable', 'string', 'max:20'],
            "{$attribute}.*.email" => ['nullable', 'email'],
        ];
    }
}
```

### Como Funciona

El sistema de validacion (`ProcessesFields::buildValidationRules`) detecta automaticamente si el campo tiene `getNestedRules()` y las agrega a las reglas de validacion:

```php
// Resultado final de validacion
[
    'internals_data' => ['required', 'array', 'min:1', 'max:10'],
    'internals_data.*.name' => ['required', 'string', 'max:255'],
    'internals_data.*.document' => ['required', 'string', 'max:50'],
    'internals_data.*.phone' => ['nullable', 'string', 'max:20'],
    'internals_data.*.email' => ['nullable', 'email'],
]
```

### Reglas Dinamicas

Puedes hacer las reglas dinamicas basadas en el request o configuracion:

```php
public function getNestedRules(): array
{
    $attribute = $this->getAttribute();
    $rules = [];

    // Reglas base
    $rules["{$attribute}.*.name"] = ['required', 'string'];

    // Regla condicional
    if ($this->requireDocument) {
        $rules["{$attribute}.*.document"] = ['required', 'string'];
    }

    // Regla con validacion de existencia
    $rules["{$attribute}.*.role_id"] = ['required', 'exists:roles,id'];

    return $rules;
}
```

---

## After Save

Para campos que necesitan ejecutar logica despues de guardar el modelo padre (ej: crear relaciones).

### Metodos Requeridos

```php
class InternalsField extends Field
{
    /**
     * Indica que este campo tiene logica post-save.
     */
    public function supportsAfterSave(): bool
    {
        return true;
    }

    /**
     * Ejecutado despues de $model->save().
     */
    public function afterSave(Request $request, Model $model): void
    {
        $items = $request->input($this->getAttribute(), []);

        // Tu logica aqui
        $model->internals()->delete();

        foreach ($items as $item) {
            $model->internals()->create($item);
        }
    }
}
```

### Flujo de Ejecucion

```
1. Validacion (getRules + getNestedRules)
   ↓
2. fill() de todos los campos
   ↓
3. $model->save()
   ↓
4. afterSave() de campos que lo soportan
   ↓
5. DB::commit()
```

### Estrategias de Sync

**Reemplazar todo:**
```php
public function afterSave(Request $request, Model $model): void
{
    $items = $request->input($this->getAttribute(), []);

    // Eliminar existentes y crear nuevos
    $model->internals()->delete();
    $model->internals()->createMany($items);
}
```

**Sync inteligente (update/create/delete):**
```php
public function afterSave(Request $request, Model $model): void
{
    $items = collect($request->input($this->getAttribute(), []));
    $existingIds = $model->internals()->pluck('id');

    // IDs que vienen en el request
    $incomingIds = $items->pluck('id')->filter();

    // Eliminar los que ya no estan
    $toDelete = $existingIds->diff($incomingIds);
    $model->internals()->whereIn('id', $toDelete)->delete();

    // Crear o actualizar
    foreach ($items as $item) {
        if (isset($item['id'])) {
            $model->internals()->where('id', $item['id'])->update($item);
        } else {
            $model->internals()->create($item);
        }
    }
}
```

---

## Ejemplo Completo: Inline HasMany

Campo para crear "internals" durante la creacion de un "Access":

### El Campo

```php
<?php

namespace App\Nadota\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Field;

class InternalsInlineField extends Field
{
    protected string $relationName = 'internals';

    public function __construct(?string $name, string $attribute)
    {
        parent::__construct($name, $attribute, 'inline-relation', 'internals-form');

        $this->showOnIndex = false;
        $this->showOnDetail = false;
        $this->showOnCreation = true;
        $this->showOnUpdate = true;
    }

    /**
     * Configurar el nombre de la relacion.
     */
    public function relation(string $name): static
    {
        $this->relationName = $name;
        return $this;
    }

    /**
     * Reglas del array principal.
     */
    public function getRules(): array
    {
        return ['required', 'array', 'min:1'];
    }

    /**
     * Reglas de cada item.
     */
    public function getNestedRules(): array
    {
        $attr = $this->getAttribute();

        return [
            "{$attr}.*.name" => ['required', 'string', 'max:255'],
            "{$attr}.*.document" => ['required', 'string', 'max:50'],
            "{$attr}.*.department_id" => ['required', 'exists:departments,id'],
            "{$attr}.*.phone" => ['nullable', 'string', 'max:20'],
            "{$attr}.*.observations" => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * No llenamos nada antes del save.
     */
    public function fill(Request $request, Model $model): void
    {
        // Se maneja en afterSave
    }

    /**
     * Soporta afterSave.
     */
    public function supportsAfterSave(): bool
    {
        return true;
    }

    /**
     * Crear/actualizar items despues del save.
     */
    public function afterSave(Request $request, Model $model): void
    {
        $items = collect($request->input($this->getAttribute(), []));
        $relation = $model->{$this->relationName}();

        // Obtener IDs existentes
        $existingIds = $relation->pluck('id');
        $incomingIds = $items->pluck('id')->filter();

        // Eliminar los que no vienen
        $relation->whereIn('id', $existingIds->diff($incomingIds))->delete();

        // Crear o actualizar
        foreach ($items as $item) {
            if (!empty($item['id'])) {
                $relation->where('id', $item['id'])->update(
                    collect($item)->except('id')->toArray()
                );
            } else {
                $relation->create($item);
            }
        }
    }

    /**
     * Resolver valor para mostrar (edit form).
     */
    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        if (!$model->exists) {
            return [];
        }

        return $model->{$this->relationName}
            ->map(fn($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'document' => $item->document,
                'department_id' => $item->department_id,
                'phone' => $item->phone,
                'observations' => $item->observations,
            ])
            ->toArray();
    }

    /**
     * Props para el componente frontend.
     */
    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'relationName' => $this->relationName,
            'fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['key' => 'document', 'label' => 'Documento', 'type' => 'text', 'required' => true],
                ['key' => 'department_id', 'label' => 'Departamento', 'type' => 'select', 'required' => true],
                ['key' => 'phone', 'label' => 'Telefono', 'type' => 'text'],
                ['key' => 'observations', 'label' => 'Observaciones', 'type' => 'textarea'],
            ],
        ]);
    }
}
```

### Uso en Resource

```php
<?php

namespace App\Nadota\AccessesQr;

use App\Nadota\Fields\InternalsInlineField;
use App\Nadota\Fields\GuestsInlineField;
use SchoolAid\Nadota\Http\Fields\DateTime;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Resource;

class AccessesQrResource extends Resource
{
    public string $model = Access::class;

    public function fields(Request $request): array
    {
        return [
            DateTime::make('fields.date', 'date')
                ->required(),

            Select::make('fields.type', 'type')
                ->options(AccessesQrEnum::options())
                ->required(),

            Input::make('fields.area', 'destinatary')
                ->required(),

            // Campo inline para crear internals durante create/update
            InternalsInlineField::make('fields.internals', 'internals_data')
                ->relation('internals'),

            // Campo inline para guests
            GuestsInlineField::make('fields.guests', 'guests_data')
                ->relation('guests'),

            // HasMany normal para ver en detail (read-only)
            HasMany::make('fields.internals', 'internals', AccessInternalResource::class)
                ->hideFromCreation()
                ->hideFromUpdate(),

            HasMany::make('fields.guests', 'guests', AccessGuestResource::class)
                ->hideFromCreation()
                ->hideFromUpdate(),
        ];
    }
}
```

### Request del Frontend

```json
{
  "date": "2025-01-15",
  "type": "internal",
  "destinatary": "Oficina Principal",
  "internals_data": [
    {
      "name": "Juan Perez",
      "document": "12345678",
      "department_id": 1,
      "phone": "555-1234"
    },
    {
      "name": "Maria Garcia",
      "document": "87654321",
      "department_id": 2
    }
  ],
  "guests_data": [
    {
      "name": "Visitante Externo",
      "company": "ACME Inc"
    }
  ]
}
```

---

## Props para Frontend

Define los props que necesita tu componente:

```php
protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
{
    return array_merge(parent::getProps($request, $model, $resource), [
        // Configuracion del campo
        'minItems' => $this->minItems,
        'maxItems' => $this->maxItems,

        // Definicion de campos para el formulario dinamico
        'fields' => $this->getFieldDefinitions(),

        // URLs si necesitas cargar datos
        'optionsUrl' => $this->optionsUrl,

        // Cualquier otro dato que necesite el frontend
        'allowReorder' => $this->allowReorder,
    ]);
}

protected function getFieldDefinitions(): array
{
    return [
        [
            'key' => 'name',
            'label' => 'Nombre',
            'type' => 'text',
            'required' => true,
            'placeholder' => 'Ingrese nombre',
        ],
        [
            'key' => 'department_id',
            'label' => 'Departamento',
            'type' => 'select',
            'required' => true,
            'optionsUrl' => '/api/departments/options',
        ],
    ];
}
```

---

## Respuesta API

El campo aparecera en la respuesta de create/edit:

```json
{
  "key": "internals_data",
  "label": "Internos",
  "attribute": "internals_data",
  "type": "inline-relation",
  "component": "internals-form",
  "value": [
    {
      "id": 1,
      "name": "Juan Perez",
      "document": "12345678",
      "department_id": 1
    }
  ],
  "showOnCreation": true,
  "showOnUpdate": true,
  "props": {
    "relationName": "internals",
    "minItems": 1,
    "maxItems": 10,
    "fields": [
      {"key": "name", "label": "Nombre", "type": "text", "required": true}
    ]
  }
}
```

---

## Errores de Validacion

Los errores de validacion anidada se devuelven con la ruta completa:

```json
{
  "message": "Validation failed",
  "errors": {
    "internals_data": ["El campo internals data es requerido."],
    "internals_data.0.name": ["El campo nombre es requerido."],
    "internals_data.1.document": ["El documento ya existe."],
    "internals_data.2.email": ["El formato del email no es valido."]
  }
}
```

El frontend puede mostrar estos errores junto a cada item del array.

---

## API para Frontend

Esta seccion documenta como el frontend debe consumir e interactuar con Custom Fields.

---

### Respuesta JSON del Campo

Cuando solicitas `/nadota-api/{resource}/resource/create` o `/nadota-api/{resource}/resource/{id}/edit`, el campo custom aparece en la lista de fields:

```json
{
  "success": true,
  "data": {
    "fields": [
      {
        "key": "internals_data",
        "label": "Internos",
        "attribute": "internals_data",
        "type": "inline-relation",
        "component": "internals-form",
        "value": [],
        "placeholder": null,
        "helpText": null,
        "readonly": false,
        "disabled": false,
        "required": true,
        "sortable": false,
        "searchable": false,
        "filterable": false,
        "showOnIndex": false,
        "showOnDetail": false,
        "showOnCreation": true,
        "showOnUpdate": true,
        "rules": ["required", "array", "min:1"],
        "props": {
          "relationName": "internals",
          "minItems": 1,
          "maxItems": 10,
          "allowReorder": true,
          "fields": [
            {
              "key": "name",
              "label": "Nombre",
              "type": "text",
              "required": true,
              "placeholder": "Ingrese nombre"
            },
            {
              "key": "document",
              "label": "Documento",
              "type": "text",
              "required": true
            },
            {
              "key": "department_id",
              "label": "Departamento",
              "type": "select",
              "required": true,
              "optionsUrl": "/nadota-api/departments/resource/options"
            },
            {
              "key": "phone",
              "label": "Telefono",
              "type": "text",
              "required": false
            }
          ]
        }
      }
    ]
  }
}
```

### Propiedades Clave

| Propiedad | Tipo | Descripcion |
|-----------|------|-------------|
| `key` | string | Identificador unico del campo |
| `attribute` | string | Nombre del campo en el request |
| `component` | string | Nombre del componente Vue/React a renderizar |
| `value` | array | Valor actual (vacio en create, con datos en edit) |
| `props.fields` | array | Definicion de campos para cada item |
| `props.minItems` | number | Minimo de items requeridos |
| `props.maxItems` | number | Maximo de items permitidos |

---

### Valor en Modo Edicion

Cuando editas un registro existente, `value` contiene los items actuales:

```json
{
  "key": "internals_data",
  "value": [
    {
      "id": 1,
      "name": "Juan Perez",
      "document": "12345678",
      "department_id": 1,
      "phone": "555-1234"
    },
    {
      "id": 2,
      "name": "Maria Garcia",
      "document": "87654321",
      "department_id": 2,
      "phone": null
    }
  ]
}
```

**Importante:** El `id` se incluye para items existentes. Esto permite al backend distinguir entre actualizar un item existente o crear uno nuevo.

---

### Enviar Datos al Backend

#### Endpoint Store
```
POST /nadota-api/{resource}/resource
Content-Type: application/json
```

#### Endpoint Update
```
PUT /nadota-api/{resource}/resource/{id}
Content-Type: application/json
```

#### Estructura del Request

```json
{
  "date": "2025-01-15",
  "type": "internal",
  "destinatary": "Oficina Principal",
  "internals_data": [
    {
      "name": "Juan Perez",
      "document": "12345678",
      "department_id": 1,
      "phone": "555-1234"
    },
    {
      "name": "Maria Garcia",
      "document": "87654321",
      "department_id": 2
    }
  ],
  "guests_data": [
    {
      "name": "Visitante Externo",
      "company": "ACME Inc"
    }
  ]
}
```

#### Request de Update (con IDs)

Para updates, incluye el `id` de items existentes:

```json
{
  "date": "2025-01-15",
  "internals_data": [
    {
      "id": 1,
      "name": "Juan Perez Actualizado",
      "document": "12345678",
      "department_id": 1
    },
    {
      "name": "Nuevo Interno",
      "document": "99999999",
      "department_id": 3
    }
  ]
}
```

**Comportamiento:**
- Items con `id`: Se actualizan
- Items sin `id`: Se crean
- Items existentes no incluidos: Se eliminan

---

### Respuesta Exitosa

#### Store (201 Created)
```json
{
  "message": "Resource created successfully",
  "data": {
    "id": 1,
    "date": "2025-01-15",
    "type": "internal",
    "destinatary": "Oficina Principal",
    "created_at": "2025-01-15T10:30:00Z",
    "updated_at": "2025-01-15T10:30:00Z"
  }
}
```

#### Update (200 OK)
```json
{
  "message": "Resource updated successfully",
  "data": {
    "id": 1,
    "date": "2025-01-15",
    "type": "internal",
    "destinatary": "Oficina Principal",
    "updated_at": "2025-01-15T11:00:00Z"
  }
}
```

---

### Manejo de Errores de Validacion

#### Respuesta de Error (422 Unprocessable Entity)

```json
{
  "message": "Validation failed",
  "errors": {
    "date": ["El campo fecha es requerido."],
    "internals_data": ["Debe agregar al menos 1 interno."],
    "internals_data.0.name": ["El campo nombre es requerido."],
    "internals_data.0.document": ["El documento ya existe."],
    "internals_data.1.email": ["El formato del email no es valido."],
    "internals_data.2.department_id": ["El departamento seleccionado no existe."]
  }
}
```

#### Estructura de Errores Anidados

| Key | Descripcion |
|-----|-------------|
| `internals_data` | Error del array principal |
| `internals_data.0.name` | Error del campo `name` del primer item (indice 0) |
| `internals_data.1.email` | Error del campo `email` del segundo item (indice 1) |

#### Parseando Errores en el Frontend

```typescript
interface ValidationErrors {
  [key: string]: string[];
}

interface ItemError {
  index: number;
  field: string;
  messages: string[];
}

function parseNestedErrors(errors: ValidationErrors, fieldKey: string): ItemError[] {
  const itemErrors: ItemError[] = [];
  const pattern = new RegExp(`^${fieldKey}\\.(\\d+)\\.(.+)$`);

  for (const [key, messages] of Object.entries(errors)) {
    const match = key.match(pattern);
    if (match) {
      itemErrors.push({
        index: parseInt(match[1]),
        field: match[2],
        messages
      });
    }
  }

  return itemErrors;
}

// Uso
const errors = parseNestedErrors(response.errors, 'internals_data');
// Resultado:
// [
//   { index: 0, field: 'name', messages: ['El campo nombre es requerido.'] },
//   { index: 0, field: 'document', messages: ['El documento ya existe.'] },
//   { index: 1, field: 'email', messages: ['El formato del email no es valido.'] }
// ]
```

---

### Ejemplo de Componente Vue

```vue
<template>
  <div class="inline-field">
    <label>{{ field.label }}</label>

    <!-- Lista de items -->
    <div v-for="(item, index) in items" :key="index" class="item-row">
      <div v-for="fieldDef in field.props.fields" :key="fieldDef.key" class="field-cell">
        <input
          v-if="fieldDef.type === 'text'"
          v-model="item[fieldDef.key]"
          :placeholder="fieldDef.placeholder"
          :class="{ 'error': hasError(index, fieldDef.key) }"
        />

        <select
          v-else-if="fieldDef.type === 'select'"
          v-model="item[fieldDef.key]"
          :class="{ 'error': hasError(index, fieldDef.key) }"
        >
          <option v-for="opt in getOptions(fieldDef)" :key="opt.value" :value="opt.value">
            {{ opt.label }}
          </option>
        </select>

        <!-- Mostrar errores -->
        <span v-if="hasError(index, fieldDef.key)" class="error-message">
          {{ getError(index, fieldDef.key) }}
        </span>
      </div>

      <!-- Boton eliminar -->
      <button @click="removeItem(index)" :disabled="items.length <= minItems">
        Eliminar
      </button>
    </div>

    <!-- Boton agregar -->
    <button @click="addItem" :disabled="items.length >= maxItems">
      Agregar {{ field.label }}
    </button>

    <!-- Error del array principal -->
    <span v-if="mainError" class="error-message">{{ mainError }}</span>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
  field: Object,
  modelValue: Array,
  errors: Object
});

const emit = defineEmits(['update:modelValue']);

const items = ref([...props.modelValue]);
const minItems = computed(() => props.field.props.minItems || 0);
const maxItems = computed(() => props.field.props.maxItems || Infinity);

// Error del array principal
const mainError = computed(() => {
  return props.errors?.[props.field.attribute]?.[0];
});

// Verificar si un campo tiene error
function hasError(index, fieldKey) {
  const key = `${props.field.attribute}.${index}.${fieldKey}`;
  return !!props.errors?.[key];
}

// Obtener mensaje de error
function getError(index, fieldKey) {
  const key = `${props.field.attribute}.${index}.${fieldKey}`;
  return props.errors?.[key]?.[0];
}

// Agregar item
function addItem() {
  if (items.value.length < maxItems.value) {
    const newItem = {};
    props.field.props.fields.forEach(f => {
      newItem[f.key] = f.default || null;
    });
    items.value.push(newItem);
  }
}

// Eliminar item
function removeItem(index) {
  if (items.value.length > minItems.value) {
    items.value.splice(index, 1);
  }
}

// Emitir cambios
watch(items, (newValue) => {
  emit('update:modelValue', newValue);
}, { deep: true });
</script>
```

---

### Ejemplo de Componente React

```tsx
import React, { useState, useEffect } from 'react';

interface FieldDefinition {
  key: string;
  label: string;
  type: 'text' | 'select' | 'number' | 'textarea';
  required?: boolean;
  placeholder?: string;
  optionsUrl?: string;
}

interface CustomFieldProps {
  field: {
    key: string;
    label: string;
    attribute: string;
    props: {
      fields: FieldDefinition[];
      minItems?: number;
      maxItems?: number;
    };
  };
  value: Record<string, any>[];
  errors: Record<string, string[]>;
  onChange: (value: Record<string, any>[]) => void;
}

export function InlineRelationField({ field, value, errors, onChange }: CustomFieldProps) {
  const [items, setItems] = useState(value || []);
  const { minItems = 0, maxItems = Infinity, fields } = field.props;

  useEffect(() => {
    onChange(items);
  }, [items]);

  const hasError = (index: number, fieldKey: string): boolean => {
    const key = `${field.attribute}.${index}.${fieldKey}`;
    return !!errors[key];
  };

  const getError = (index: number, fieldKey: string): string | undefined => {
    const key = `${field.attribute}.${index}.${fieldKey}`;
    return errors[key]?.[0];
  };

  const addItem = () => {
    if (items.length < maxItems) {
      const newItem: Record<string, any> = {};
      fields.forEach(f => {
        newItem[f.key] = null;
      });
      setItems([...items, newItem]);
    }
  };

  const removeItem = (index: number) => {
    if (items.length > minItems) {
      setItems(items.filter((_, i) => i !== index));
    }
  };

  const updateItem = (index: number, fieldKey: string, value: any) => {
    const newItems = [...items];
    newItems[index] = { ...newItems[index], [fieldKey]: value };
    setItems(newItems);
  };

  return (
    <div className="inline-field">
      <label>{field.label}</label>

      {/* Error principal */}
      {errors[field.attribute] && (
        <div className="error">{errors[field.attribute][0]}</div>
      )}

      {/* Items */}
      {items.map((item, index) => (
        <div key={index} className="item-row">
          {fields.map(fieldDef => (
            <div key={fieldDef.key} className="field-cell">
              <label>{fieldDef.label}</label>

              {fieldDef.type === 'text' && (
                <input
                  type="text"
                  value={item[fieldDef.key] || ''}
                  placeholder={fieldDef.placeholder}
                  onChange={(e) => updateItem(index, fieldDef.key, e.target.value)}
                  className={hasError(index, fieldDef.key) ? 'error' : ''}
                />
              )}

              {fieldDef.type === 'number' && (
                <input
                  type="number"
                  value={item[fieldDef.key] || ''}
                  onChange={(e) => updateItem(index, fieldDef.key, e.target.value)}
                  className={hasError(index, fieldDef.key) ? 'error' : ''}
                />
              )}

              {/* Error del campo */}
              {hasError(index, fieldDef.key) && (
                <span className="error-message">
                  {getError(index, fieldDef.key)}
                </span>
              )}
            </div>
          ))}

          <button
            type="button"
            onClick={() => removeItem(index)}
            disabled={items.length <= minItems}
          >
            Eliminar
          </button>
        </div>
      ))}

      {/* Agregar */}
      <button
        type="button"
        onClick={addItem}
        disabled={items.length >= maxItems}
      >
        Agregar {field.label}
      </button>
    </div>
  );
}
```

---

### Cargando Opciones Dinamicas

Si un campo tiene `optionsUrl`, carga las opciones al montar:

```typescript
async function loadOptions(fieldDef: FieldDefinition): Promise<Option[]> {
  if (!fieldDef.optionsUrl) return [];

  const response = await fetch(fieldDef.optionsUrl);
  const data = await response.json();

  return data.options; // [{ value: 1, label: 'Option 1' }, ...]
}

// En el componente
const [selectOptions, setSelectOptions] = useState<Record<string, Option[]>>({});

useEffect(() => {
  field.props.fields
    .filter(f => f.type === 'select' && f.optionsUrl)
    .forEach(async (f) => {
      const options = await loadOptions(f);
      setSelectOptions(prev => ({ ...prev, [f.key]: options }));
    });
}, []);
```

---

### Resumen de Endpoints

| Endpoint | Metodo | Descripcion |
|----------|--------|-------------|
| `/{resource}/resource/create` | GET | Obtener fields para crear (incluye custom fields) |
| `/{resource}/resource/{id}/edit` | GET | Obtener fields para editar (con valores actuales) |
| `/{resource}/resource` | POST | Crear recurso (enviar datos del custom field) |
| `/{resource}/resource/{id}` | PUT | Actualizar recurso |
| `/{related}/resource/options` | GET | Cargar opciones para selects |

---

## Ver Tambien

- [Fields README](./README.md) - Documentacion general de campos
- [Relations](../RELATIONS.md) - Campos de relacion
- [Hooks](../hooks/README.md) - Hooks del resource (beforeStore, afterStore, etc.)
- [API Responses](../API_RESPONSES.md) - Estructura de respuestas de la API
