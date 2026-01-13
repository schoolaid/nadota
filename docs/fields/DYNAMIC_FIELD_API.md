# DynamicField - Frontend API Reference

Technical documentation for frontend developers implementing the DynamicField component.

---

## Response Structure

### Field JSON

```json
{
  "key": "value",
  "label": "Value",
  "attribute": "value",
  "type": "dynamic",
  "component": "FieldDynamic",
  "required": true,
  "disabled": false,
  "readonly": false,
  "showOnIndex": true,
  "showOnDetail": true,
  "showOnCreation": true,
  "showOnUpdate": true,
  "value": "{\"answer\": \"Hello\"}",
  "dependencies": {
    "fields": ["formItem.type"]
  },
  "props": {
    "typeField": "formItem.type",
    "isDynamic": true,
    "types": {
      "1": { /* Field config for TEXT */ },
      "2": { /* Field config for BOOLEAN */ },
      "3": { /* Field config for SELECT */ },
      "4": { /* Field config for NUMBER */ },
      "5": { /* Field config for DATE */ },
      "6": { /* Field config for SIGNATURE */ },
      "7": { /* Field config for FILE */ }
    },
    "matchedType": 1,
    "matchedField": { /* Currently matched field config */ },
    "defaultField": { /* Fallback field config */ }
  }
}
```

---

## Props Reference

| Prop | Type | Description |
|------|------|-------------|
| `typeField` | `string` | Path to the field that determines the type (supports dot notation) |
| `isDynamic` | `boolean` | Always `true` for DynamicField |
| `types` | `Record<string\|number, FieldConfig>` | Map of type values to field configurations |
| `matchedType` | `string\|number\|null` | Currently matched type value (based on model) |
| `matchedField` | `FieldConfig\|null` | Full config of the matched field |
| `defaultField` | `FieldConfig\|null` | Fallback field when no type matches |

---

## TypeScript Definitions

```typescript
interface DynamicFieldProps {
  // Path to the field that determines which type to render
  // Supports dot notation for relations: "formItem.type"
  typeField: string;

  // Always true for DynamicField
  isDynamic: true;

  // Map of type values to field configurations
  // Keys can be strings or numbers
  types: Record<string | number, FieldConfig>;

  // Currently matched type value (from model data)
  matchedType?: string | number | null;

  // Full configuration of the matched field
  matchedField?: FieldConfig | null;

  // Fallback field when no type matches
  defaultField?: FieldConfig | null;
}

interface FieldConfig {
  key: string;
  label: string;
  attribute: string;
  type: string;
  component: string;
  value?: any;
  required?: boolean;
  disabled?: boolean;
  readonly?: boolean;
  props?: Record<string, any>;
  rules?: string[];
  dependencies?: DependencyConfig;
}

interface DynamicFieldData {
  field: FieldConfig & { props: DynamicFieldProps };
  formValues: Record<string, any>;
  modelValue: any;
}
```

---

## Implementation Guide

### Step 1: Get Current Type Value

Extract the type value from form data using the `typeField` path:

```typescript
function getTypeValue(formValues: Record<string, any>, typeField: string): any {
  // Handle dot notation for nested values
  return typeField.split('.').reduce((obj, key) => obj?.[key], formValues);
}

// Example:
// typeField = "formItem.type"
// formValues = { formItem: { type: 2 } }
// Result: 2
```

### Step 2: Resolve Field Configuration

```typescript
function resolveFieldConfig(
  props: DynamicFieldProps,
  formValues: Record<string, any>
): FieldConfig | null {
  const typeValue = getTypeValue(formValues, props.typeField);

  // Check if we have a mapping for this type
  if (typeValue != null && props.types[typeValue]) {
    return props.types[typeValue];
  }

  // Fallback to default field
  return props.defaultField || null;
}
```

### Step 3: Render Appropriate Component

```typescript
function getComponentName(fieldConfig: FieldConfig): string {
  // Use explicit component if provided
  if (fieldConfig.component) {
    return fieldConfig.component;
  }

  // Map field type to component
  const componentMap: Record<string, string> = {
    'text': 'FieldInput',
    'number': 'FieldNumber',
    'boolean': 'FieldToggle',
    'select': 'FieldSelect',
    'date': 'FieldDate',
    'datetime': 'FieldDateTime',
    'time': 'FieldTime',
    'file': 'FieldFile',
    'image': 'FieldImage',
    'textarea': 'FieldTextarea',
    'checkbox': 'FieldCheckbox',
    'radio': 'FieldRadio',
    'email': 'FieldEmail',
    'url': 'FieldUrl',
    'password': 'FieldPassword',
    'json': 'FieldJson',
    'code': 'FieldCode',
    'color': 'FieldColor',
    'hidden': 'FieldHidden',
  };

  return componentMap[fieldConfig.type] || 'FieldInput';
}
```

### Step 4: Handle Type Changes

```typescript
function onTypeChange(
  oldType: any,
  newType: any,
  clearValue: boolean = true
): void {
  if (oldType === newType) return;

  if (clearValue) {
    // Clear the value when type changes
    emit('update:modelValue', null);
  }

  // Optionally trigger validation reset
  emit('resetValidation');
}
```

### Step 5: Handle Value Transformation

Different field types may need value transformation:

```typescript
function transformValue(value: any, fieldType: string): any {
  switch (fieldType) {
    case 'boolean':
      return Boolean(value);

    case 'number':
      return value != null ? Number(value) : null;

    case 'select':
      // Could be single value or array for multiple
      return value;

    case 'date':
    case 'datetime':
      // Ensure proper date format
      return value ? new Date(value).toISOString() : null;

    case 'json':
      // Parse if string, return as-is if object
      if (typeof value === 'string') {
        try {
          return JSON.parse(value);
        } catch {
          return value;
        }
      }
      return value;

    default:
      return value;
  }
}
```

---

## Vue.js Implementation

### Component: FieldDynamic.vue

```vue
<template>
  <div class="field-dynamic" v-if="resolvedField">
    <component
      :is="componentName"
      :field="resolvedField"
      :model-value="modelValue"
      :disabled="field.disabled"
      :readonly="field.readonly"
      @update:model-value="handleUpdate"
    />
  </div>
  <div v-else class="field-dynamic--empty">
    <span class="text-muted">Select a type first</span>
  </div>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue';
import type { FieldConfig, DynamicFieldProps } from '@/types';

// Component imports
import FieldInput from './FieldInput.vue';
import FieldNumber from './FieldNumber.vue';
import FieldToggle from './FieldToggle.vue';
import FieldSelect from './FieldSelect.vue';
import FieldDate from './FieldDate.vue';
import FieldFile from './FieldFile.vue';
// ... other components

const components: Record<string, any> = {
  FieldInput,
  FieldNumber,
  FieldToggle,
  FieldSelect,
  FieldDate,
  FieldFile,
  // ... register all field components
};

interface Props {
  field: FieldConfig & { props: DynamicFieldProps };
  formValues: Record<string, any>;
  modelValue: any;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  'update:modelValue': [value: any];
}>();

// Get current type from form values
const currentType = computed(() => {
  const typeField = props.field.props.typeField;
  return getNestedValue(props.formValues, typeField);
});

// Resolve which field config to use
const resolvedField = computed((): FieldConfig | null => {
  const { types, defaultField, matchedField } = props.field.props;

  // If we have a current type and it's in the types map
  if (currentType.value != null && types?.[currentType.value]) {
    return types[currentType.value];
  }

  // Use matched field from server if available
  if (matchedField) {
    return matchedField;
  }

  // Fallback to default
  return defaultField || null;
});

// Get component name for the resolved field
const componentName = computed(() => {
  if (!resolvedField.value) return null;

  const field = resolvedField.value;

  // Check component map
  const typeMap: Record<string, string> = {
    'text': 'FieldInput',
    'number': 'FieldNumber',
    'boolean': 'FieldToggle',
    'select': 'FieldSelect',
    'date': 'FieldDate',
    'datetime': 'FieldDateTime',
    'file': 'FieldFile',
    'image': 'FieldImage',
    'textarea': 'FieldTextarea',
  };

  return field.component || typeMap[field.type] || 'FieldInput';
});

// Watch for type changes
watch(currentType, (newType, oldType) => {
  if (oldType !== undefined && newType !== oldType) {
    // Clear value when type changes
    emit('update:modelValue', null);
  }
});

// Handle value updates
function handleUpdate(value: any) {
  emit('update:modelValue', value);
}

// Helper to get nested values
function getNestedValue(obj: any, path: string): any {
  return path.split('.').reduce((o, k) => o?.[k], obj);
}
</script>

<style scoped>
.field-dynamic--empty {
  padding: 1rem;
  background: #f5f5f5;
  border-radius: 4px;
  text-align: center;
}
</style>
```

---

## React Implementation

```tsx
import React, { useMemo, useEffect, useRef } from 'react';

interface DynamicFieldProps {
  field: FieldConfig & { props: DynamicFieldPropsType };
  formValues: Record<string, any>;
  value: any;
  onChange: (value: any) => void;
}

// Component map
const componentMap: Record<string, React.ComponentType<any>> = {
  FieldInput: InputField,
  FieldNumber: NumberField,
  FieldToggle: ToggleField,
  FieldSelect: SelectField,
  FieldDate: DateField,
  FieldFile: FileField,
  // ... other components
};

export function DynamicField({ field, formValues, value, onChange }: DynamicFieldProps) {
  const prevTypeRef = useRef<any>(null);

  // Get current type
  const currentType = useMemo(() => {
    return getNestedValue(formValues, field.props.typeField);
  }, [formValues, field.props.typeField]);

  // Resolve field config
  const resolvedField = useMemo(() => {
    const { types, defaultField, matchedField } = field.props;

    if (currentType != null && types?.[currentType]) {
      return types[currentType];
    }

    return matchedField || defaultField || null;
  }, [currentType, field.props]);

  // Get component
  const Component = useMemo(() => {
    if (!resolvedField) return null;

    const typeMap: Record<string, string> = {
      'text': 'FieldInput',
      'number': 'FieldNumber',
      'boolean': 'FieldToggle',
      'select': 'FieldSelect',
      'date': 'FieldDate',
      'file': 'FieldFile',
    };

    const componentName = resolvedField.component || typeMap[resolvedField.type] || 'FieldInput';
    return componentMap[componentName];
  }, [resolvedField]);

  // Clear value on type change
  useEffect(() => {
    if (prevTypeRef.current !== undefined && prevTypeRef.current !== currentType) {
      onChange(null);
    }
    prevTypeRef.current = currentType;
  }, [currentType, onChange]);

  if (!resolvedField || !Component) {
    return (
      <div className="field-dynamic--empty">
        <span>Select a type first</span>
      </div>
    );
  }

  return (
    <Component
      field={resolvedField}
      value={value}
      onChange={onChange}
    />
  );
}

function getNestedValue(obj: any, path: string): any {
  return path.split('.').reduce((o, k) => o?.[k], obj);
}
```

---

## Edge Cases

### 1. Type Field Not Yet Selected

When creating a new record, the type field may not have a value yet.

```typescript
if (currentType === null || currentType === undefined) {
  // Show placeholder or default field
  return defaultField || <SelectTypePrompt />;
}
```

### 2. Type Value Not in Map

The type value exists but there's no mapping for it.

```typescript
if (types[currentType] === undefined) {
  console.warn(`No field mapping for type: ${currentType}`);
  return defaultField;
}
```

### 3. Relation Not Loaded

When using dot notation (`formItem.type`), the relation may not be loaded.

```typescript
const typeValue = getNestedValue(formValues, 'formItem.type');

if (typeValue === undefined) {
  // Relation not loaded - wait or use matchedField from server
  return props.field.props.matchedField;
}
```

### 4. Value Type Mismatch

The stored value may not match the expected type.

```typescript
function coerceValue(value: any, fieldType: string): any {
  if (value === null || value === undefined) return value;

  switch (fieldType) {
    case 'boolean':
      if (typeof value === 'string') {
        return ['true', '1', 'yes'].includes(value.toLowerCase());
      }
      return Boolean(value);

    case 'number':
      const num = Number(value);
      return isNaN(num) ? null : num;

    case 'date':
      const date = new Date(value);
      return isNaN(date.getTime()) ? null : date.toISOString().split('T')[0];

    default:
      return value;
  }
}
```

### 5. Validation Per Type

Each type may have different validation rules.

```typescript
function getValidationRules(resolvedField: FieldConfig): string[] {
  const baseRules = field.rules || [];
  const typeRules = resolvedField.rules || [];

  return [...new Set([...baseRules, ...typeRules])];
}
```

---

## Form Integration

### With Formik (React)

```tsx
<Formik>
  {({ values, setFieldValue }) => (
    <DynamicField
      field={dynamicFieldConfig}
      formValues={values}
      value={values.value}
      onChange={(val) => setFieldValue('value', val)}
    />
  )}
</Formik>
```

### With VeeValidate (Vue)

```vue
<Field name="value" v-slot="{ value, handleChange }">
  <FieldDynamic
    :field="dynamicFieldConfig"
    :form-values="formValues"
    :model-value="value"
    @update:model-value="handleChange"
  />
</Field>
```

---

## Server Communication

### Storing Value

The value is stored in the same attribute regardless of type:

```typescript
// All types use the same attribute
const payload = {
  form_item_id: formItemId,
  value: serializedValue,  // JSON string
};
```

### Value Serialization

```typescript
function serializeValue(value: any, fieldType: string): string {
  if (value === null || value === undefined) {
    return JSON.stringify(null);
  }

  // Most types can be JSON serialized directly
  return JSON.stringify(value);
}
```

### Value Deserialization

```typescript
function deserializeValue(jsonString: string, fieldType: string): any {
  try {
    const value = JSON.parse(jsonString);
    return coerceValue(value, fieldType);
  } catch {
    return jsonString; // Return as-is if not valid JSON
  }
}
```

---

## Performance Considerations

### 1. Lazy Load Components

Only load components when needed:

```typescript
const componentMap = {
  FieldInput: () => import('./FieldInput.vue'),
  FieldNumber: () => import('./FieldNumber.vue'),
  // ...
};
```

### 2. Use `onlyMatchedType()`

If you don't need all type configs on the frontend:

```php
DynamicField::make('Value', 'value')
    ->basedOn('type')
    ->types([...])
    ->onlyMatchedType();  // Only sends matchedField, not all types
```

### 3. Memoize Resolved Field

Avoid recalculating on every render:

```typescript
const resolvedField = useMemo(() => {
  // Calculation here
}, [currentType, types, defaultField]);
```

---

## Summary

| Responsibility | Frontend |
|----------------|----------|
| Determine current type | Read from `formValues` using `typeField` path |
| Resolve field config | Match type against `types` map |
| Render component | Use `component` or map `type` to component |
| Handle type change | Clear value, reset validation |
| Transform value | Coerce to expected type |
| Validate | Combine base + type-specific rules |
