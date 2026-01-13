# DynamicField

Campo que cambia su tipo/componente dinámicamente según el valor de otro campo. Ideal para formularios donde un campo puede ser de diferentes tipos según el contexto.

## Caso de Uso Principal

Formularios dinámicos donde el tipo de input depende de una configuración:

```
filled_form_items
├── form_item_id → form_items.type (TEXT, BOOLEAN, SELECT, etc.)
└── value (JSON) → Se renderiza según el tipo
```

---

## Uso Básico

```php
use SchoolAid\Nadota\Http\Fields\DynamicField;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Toggle;

DynamicField::make('Value', 'value')
    ->basedOn('item_type')  // Campo que determina el tipo
    ->types([
        1 => Input::make('Text', 'value'),
        2 => Number::make('Number', 'value'),
        3 => Toggle::make('Boolean', 'value'),
    ]);
```

---

## API

### basedOn(string $field)

Define qué campo determina el tipo a usar. Soporta dot notation para relaciones y **PHP Enums**.

```php
// Campo directo
->basedOn('type')

// Relación
->basedOn('formItem.type')

// Soporta PHP Enums automáticamente
// Si formItem.type es un BackedEnum, extrae el ->value
```

**Soporte de Enums**: Si el campo devuelve un `BackedEnum` (int o string), DynamicField automáticamente extrae el valor (`->value`). Para `UnitEnum` sin valor, usa el nombre (`->name`).

### types(array $types)

Define el mapeo de valores a campos.

```php
->types([
    'text' => Input::make('Text', 'value'),
    'number' => Number::make('Number', 'value'),
    'select' => Select::make('Options', 'value')->options([...]),
])
```

### when(mixed $value, Field|Closure $field)

Agrega un mapeo individual.

```php
->when(1, Input::make('Text', 'value'))
->when(2, Number::make('Number', 'value'))
->when(3, fn($model) => Select::make('Options', 'value')
    ->options($model->formItem->options ?? []))
```

### defaultType(Field $field)

Campo a usar cuando no hay coincidencia.

```php
->defaultType(Input::make('Default', 'value'))
```

### onlyMatchedType()

Solo incluye el campo coincidente en la respuesta (no todos los tipos).

```php
->onlyMatchedType()  // Reduce tamaño del JSON
```

---

## Closures para Campos Dinámicos

Usa closures cuando el campo necesita datos del modelo:

```php
DynamicField::make('Value', 'value')
    ->basedOn('formItem.type')
    ->types([
        'text' => Input::make('Text', 'value'),

        // Closure recibe el modelo
        'select' => fn($model, $request) => Select::make('Options', 'value')
            ->options($model->formItem->config['options'] ?? []),

        'number' => fn($model) => Number::make('Number', 'value')
            ->min($model->formItem->config['min'] ?? 0)
            ->max($model->formItem->config['max'] ?? 100),
    ]);
```

---

## Ejemplo Completo: FormBuilder

```php
// FilledFormItemResource.php
public function fields(Request $request): array
{
    return [
        BelongsTo::make('Form Item', 'formItem', FormItemResource::class)
            ->displayAttribute('label')
            ->hideFromIndex(),

        DynamicField::make('Value', 'value')
            ->basedOn('formItem.type')
            ->types([
                1 => Input::make('Text', 'value'),           // TEXT
                2 => Toggle::make('Boolean', 'value'),       // BOOLEAN
                3 => fn($model) => Select::make('Select', 'value')
                        ->options($model->formItem->config['options'] ?? []),
                4 => Number::make('Number', 'value'),        // NUMBER
                5 => Date::make('Date', 'value'),            // DATE
                6 => CustomComponent::make('Signature', 'SignaturePad')
                        ->withData(fn($m) => ['signature' => $m->value]),
                7 => File::make('File', 'value')             // FILE
                        ->disk('forms')
                        ->path('uploads'),
            ])
            ->defaultType(Input::make('Value', 'value'))
            ->required(),
    ];
}
```

---

## Salida JSON

```json
{
  "key": "value",
  "label": "Value",
  "type": "dynamic",
  "component": "FieldDynamic",
  "dependencies": {
    "fields": ["formItem.type"]
  },
  "props": {
    "typeField": "formItem.type",
    "isDynamic": true,
    "types": {
      "1": {
        "key": "value",
        "type": "text",
        "component": "field",
        "label": "Text"
      },
      "2": {
        "key": "value",
        "type": "boolean",
        "component": "FieldToggle",
        "label": "Boolean"
      },
      "3": {
        "key": "value",
        "type": "select",
        "component": "FieldSelect",
        "label": "Select",
        "props": {
          "options": [...]
        }
      }
    },
    "matchedType": 1,
    "matchedField": {
      "key": "value",
      "type": "text",
      "label": "Text"
    },
    "defaultField": {
      "key": "value",
      "type": "text",
      "label": "Value"
    }
  }
}
```

---

## Frontend Implementation

```vue
<template>
  <component
    :is="getComponentForType(currentType)"
    :field="matchedField || defaultField"
    :value="modelValue"
    @update:value="$emit('update:modelValue', $event)"
  />
</template>

<script setup>
import { computed, watch } from 'vue';

const props = defineProps({
  field: Object,
  formValues: Object,
  modelValue: [String, Number, Boolean, Array, Object],
});

const emit = defineEmits(['update:modelValue']);

// Get current type from form values
const currentType = computed(() => {
  const typeField = props.field.props.typeField;
  return getNestedValue(props.formValues, typeField);
});

// Get matched field config
const matchedField = computed(() => {
  const types = props.field.props.types || {};
  return types[currentType.value] || null;
});

const defaultField = computed(() => props.field.props.defaultField);

// Clear value when type changes (optional)
watch(currentType, (newType, oldType) => {
  if (oldType !== undefined && newType !== oldType) {
    emit('update:modelValue', null);
  }
});

function getComponentForType(type) {
  const field = matchedField.value || defaultField.value;
  if (!field) return 'input';

  // Map field type to component
  const componentMap = {
    'text': 'FieldInput',
    'number': 'FieldNumber',
    'boolean': 'FieldToggle',
    'select': 'FieldSelect',
    'date': 'FieldDate',
    'file': 'FieldFile',
  };

  return componentMap[field.type] || field.component || 'FieldInput';
}

function getNestedValue(obj, path) {
  return path.split('.').reduce((o, k) => o?.[k], obj);
}
</script>
```

---

## Uso con PHP Enums

Si tu modelo usa un Enum para el tipo, DynamicField lo maneja automáticamente:

```php
// Tu Enum
enum FormItemType: int
{
    case TEXT = 1;
    case BOOLEAN = 2;
    case SELECT = 3;
    case NUMBER = 4;
    case DATE = 5;
    case SIGNATURE = 6;
    case FILE = 7;
}

// En tu modelo
protected $casts = [
    'type' => FormItemType::class,
];

// En tu Resource - usa los valores del enum como keys
DynamicField::make('Value', 'value')
    ->basedOn('formItem.type')
    ->types([
        FormItemType::TEXT->value => Input::make('Text', 'value'),
        FormItemType::BOOLEAN->value => Toggle::make('Boolean', 'value'),
        FormItemType::SELECT->value => fn($model) => Select::make('Select', 'value')
            ->options($model->formItem->config['options'] ?? []),
        FormItemType::NUMBER->value => Number::make('Number', 'value'),
        FormItemType::DATE->value => Date::make('Date', 'value'),
        FormItemType::FILE->value => File::make('File', 'value'),
    ])
    ->defaultType(Input::make('Value', 'value'));

// O simplemente usa los valores numéricos directamente
->types([
    1 => Input::make('Text', 'value'),
    2 => Toggle::make('Boolean', 'value'),
    // ...
]);
```

DynamicField automáticamente convierte `FormItemType::TEXT` a `1` cuando compara con los keys del type map.

---

## Integración con dependsOn

DynamicField automáticamente agrega `dependsOn` para el campo tipo:

```php
DynamicField::make('Value', 'value')
    ->basedOn('item_type')  // Automáticamente hace dependsOn('item_type')
```

Puedes agregar más dependencias:

```php
DynamicField::make('Value', 'value')
    ->basedOn('item_type')
    ->showWhenHasValue('form_item_id')  // Visible solo si hay form_item
    ->requiredWhenTruthy('is_required')
```

---

## Validación

El campo base puede tener reglas de validación. Las reglas del campo resuelto se combinan:

```php
DynamicField::make('Value', 'value')
    ->basedOn('type')
    ->required()  // Siempre requerido
    ->rules('max:10000')  // Regla base
    ->types([
        'email' => Input::make('Email', 'value')
            ->rules('email'),  // Se suma a las reglas base
        'url' => Input::make('URL', 'value')
            ->rules('url'),
    ]);
```

---

## Hooks (beforeSave, afterSave)

Los hooks se delegan al campo resuelto:

```php
->types([
    'file' => File::make('File', 'value')
        ->disk('forms'),  // afterSave manejará el upload
])
```

---

## Best Practices

1. **Siempre define un defaultType** para manejar tipos desconocidos

2. **Usa closures** cuando necesites datos del modelo para configurar el campo

3. **Considera onlyMatchedType()** para reducir el tamaño del JSON en listas grandes

4. **El typeField debe estar disponible** en el modelo cuando se resuelve

5. **Para relaciones**, asegúrate de eager load:
   ```php
   public static array $with = ['formItem'];
   ```

6. **Valida en ambos lados** - frontend para UX, backend para seguridad
