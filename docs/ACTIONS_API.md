# API de Acciones - Nadota

Documentación completa de la API de Acciones para implementaciones frontend.

## Tabla de Contenidos

- [Descripción General](#descripción-general)
- [Endpoints](#endpoints)
- [Estructura de una Acción](#estructura-de-una-acción)
- [Tipos de Respuesta](#tipos-de-respuesta)
- [Flujo de Ejecución](#flujo-de-ejecución)
- [Acciones con Campos](#acciones-con-campos)
- [Acciones Destructivas](#acciones-destructivas)
- [Acciones Standalone](#acciones-standalone)
- [Ejemplos de Implementación](#ejemplos-de-implementación)

---

## Descripción General

Las acciones permiten ejecutar operaciones personalizadas sobre uno o varios recursos seleccionados. Pueden ser:

- **Acciones normales**: Requieren recursos seleccionados
- **Acciones standalone**: Pueden ejecutarse sin selección
- **Acciones destructivas**: Requieren confirmación (estilo visual de peligro)

---

## Endpoints

### 1. Listar Acciones

```http
GET /nadota-api/{resourceKey}/resource/actions
```

**Query Parameters:**

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `context` | string | `index` | `index` para lista, `detail` para detalle |

**Response:**
```json
{
  "actions": [
    {
      "key": "school-aid-nadota-http-actions-send-email-action",
      "name": "Enviar Email",
      "fields": [],
      "showOnIndex": true,
      "showOnDetail": true,
      "destructive": false,
      "standalone": false,
      "confirmText": null,
      "confirmButtonText": "Run Action",
      "cancelButtonText": "Cancel"
    }
  ]
}
```

---

### 2. Obtener Campos de Acción

```http
GET /nadota-api/{resourceKey}/resource/actions/{actionKey}/fields
```

Obtiene los campos del formulario para una acción específica.

**Response:**
```json
{
  "fields": [
    {
      "label": "Asunto",
      "attribute": "subject",
      "type": "text",
      "component": "FieldInput",
      "rules": ["required", "string", "max:255"],
      "props": {}
    },
    {
      "label": "Mensaje",
      "attribute": "message",
      "type": "textarea",
      "component": "FieldTextarea",
      "rules": ["required"],
      "props": {
        "rows": 5
      }
    }
  ]
}
```

---

### 3. Ejecutar Acción

```http
POST /nadota-api/{resourceKey}/resource/actions/{actionKey}
```

**Request Body:**
```json
{
  "resources": [1, 2, 3],
  "subject": "Notificación importante",
  "message": "Este es el contenido del mensaje"
}
```

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `resources` | array | Sí* | IDs de recursos seleccionados |
| `...fields` | mixed | Depende | Campos definidos por la acción |

*No requerido si la acción es `standalone`.

---

## Estructura de una Acción

```typescript
interface Action {
  // Identificador único (generado desde el nombre de la clase)
  key: string;

  // Nombre para mostrar en la UI
  name: string;

  // Campos del formulario de la acción
  fields: Field[];

  // Mostrar en la vista de lista
  showOnIndex: boolean;

  // Mostrar en la vista de detalle
  showOnDetail: boolean;

  // Es una acción destructiva (requiere confirmación especial)
  destructive: boolean;

  // Puede ejecutarse sin seleccionar recursos
  standalone: boolean;

  // Texto de confirmación (null si no requiere)
  confirmText: string | null;

  // Texto del botón de confirmar
  confirmButtonText: string;

  // Texto del botón de cancelar
  cancelButtonText: string;

  // Componente personalizado (opcional, solo presente si se especifica)
  // Si no está presente, usar el componente default del frontend
  component?: string;
}
```

---

## Tipos de Respuesta

### 1. Mensaje de Éxito

```json
{
  "type": "message",
  "message": "Acción ejecutada correctamente en 5 recursos."
}
```

**Comportamiento frontend:**
- Mostrar toast/notificación de éxito
- Refrescar la lista de recursos

---

### 2. Mensaje de Error (Danger)

```json
{
  "type": "danger",
  "message": "No tienes permisos para ejecutar esta acción."
}
```

**Comportamiento frontend:**
- Mostrar toast/notificación de error
- No refrescar la lista

---

### 3. Redirección

```json
{
  "type": "redirect",
  "url": "/resources/users/1"
}
```

**Comportamiento frontend:**
- Navegar a la URL indicada (router push)

---

### 4. Descarga

```json
{
  "type": "download",
  "url": "/storage/exports/report-2024.pdf",
  "filename": "report-2024.pdf"
}
```

**Comportamiento frontend:**
- Iniciar descarga del archivo

---

### 5. Abrir en Nueva Pestaña

```json
{
  "type": "openInNewTab",
  "url": "https://external-service.com/report/123",
  "openInNewTab": true
}
```

**Comportamiento frontend:**
- Abrir URL en nueva pestaña (`window.open(url, '_blank')`)

---

## Flujo de Ejecución

### Flujo Básico (Sin campos)

```
1. Usuario selecciona recursos [1, 2, 3]
2. Usuario hace clic en acción "Activar"
3. Si action.confirmText existe:
   - Mostrar modal de confirmación
   - Usuario confirma
4. POST /actions/activate { resources: [1, 2, 3] }
5. Procesar respuesta según tipo
6. Refrescar lista si fue exitoso
```

### Flujo con Campos

```
1. Usuario selecciona recursos [1, 2, 3]
2. Usuario hace clic en acción "Enviar Email"
3. GET /actions/send-email/fields
4. Mostrar modal con formulario de campos
5. Usuario llena campos y confirma
6. POST /actions/send-email {
     resources: [1, 2, 3],
     subject: "...",
     message: "..."
   }
7. Procesar respuesta según tipo
8. Cerrar modal y refrescar lista
```

---

## Acciones con Campos

Las acciones pueden definir campos que el usuario debe llenar antes de ejecutar.

### Ejemplo de Acción con Campos

**Response de GET /actions:**
```json
{
  "key": "send-notification",
  "name": "Enviar Notificación",
  "fields": [
    {
      "label": "Título",
      "attribute": "title",
      "type": "text",
      "rules": ["required", "max:100"]
    },
    {
      "label": "Mensaje",
      "attribute": "body",
      "type": "textarea",
      "rules": ["required"]
    },
    {
      "label": "Prioridad",
      "attribute": "priority",
      "type": "select",
      "props": {
        "options": [
          {"value": "low", "label": "Baja"},
          {"value": "medium", "label": "Media"},
          {"value": "high", "label": "Alta"}
        ]
      }
    }
  ]
}
```

**Request de ejecución:**
```json
{
  "resources": [1, 2, 3],
  "title": "Actualización importante",
  "body": "Se han actualizado los términos de servicio",
  "priority": "high"
}
```

---

## Acciones Destructivas

Las acciones destructivas requieren confirmación y tienen estilo visual de peligro.

### Características

- `destructive: true`
- `confirmText` con mensaje de advertencia
- Botón de confirmar en rojo
- Doble confirmación recomendada en el frontend

### Ejemplo

```json
{
  "key": "delete-permanently",
  "name": "Eliminar Permanentemente",
  "destructive": true,
  "confirmText": "Esta acción no se puede deshacer. ¿Estás seguro de que deseas eliminar permanentemente estos recursos?",
  "confirmButtonText": "Eliminar Permanentemente",
  "cancelButtonText": "Cancelar"
}
```

### Implementación Frontend Recomendada

```vue
<template>
  <Modal v-if="showConfirmation">
    <template #header>
      <span class="text-red-600">{{ action.name }}</span>
    </template>

    <p class="text-gray-700">{{ action.confirmText }}</p>
    <p class="text-sm text-gray-500 mt-2">
      {{ selectedCount }} recurso(s) seleccionado(s)
    </p>

    <template #footer>
      <button
        @click="cancel"
        class="btn-secondary"
      >
        {{ action.cancelButtonText }}
      </button>
      <button
        @click="confirm"
        class="btn-danger"
      >
        {{ action.confirmButtonText }}
      </button>
    </template>
  </Modal>
</template>
```

---

## Acciones con Componente Personalizado

Puedes especificar un componente personalizado para que el frontend use una UI diferente.

### Características

- `component` solo se incluye en la respuesta si se especifica
- Si no está presente, el frontend debe usar su componente default
- Útil para acciones con UI compleja (wizards, uploads, etc.)

### Ejemplo Backend

```php
public function actions(NadotaRequest $request): array
{
    return [
        ImportAction::make()
            ->component('ActionImportWizard'),

        ExportAction::make()
            ->component('ActionExportModal'),
    ];
}
```

### Response con Componente

```json
{
  "key": "import-data",
  "name": "Importar Datos",
  "component": "ActionImportWizard",
  "standalone": true,
  "fields": []
}
```

### Response sin Componente (default)

```json
{
  "key": "send-email",
  "name": "Enviar Email",
  "standalone": false,
  "fields": []
}
```

### Implementación Frontend

```typescript
function getActionComponent(action: Action) {
  // Si tiene componente personalizado, usarlo
  if (action.component) {
    return resolveComponent(action.component);
  }

  // Si no, usar el default
  return DefaultActionModal;
}
```

---

## Acciones Standalone

Las acciones standalone pueden ejecutarse sin seleccionar recursos.

### Características

- `standalone: true`
- El botón está siempre habilitado
- `resources` puede estar vacío en el request

### Casos de Uso

- Exportar todos los registros
- Generar reportes
- Ejecutar procesos globales
- Importar datos

### Ejemplo

```json
{
  "key": "export-all",
  "name": "Exportar Todo",
  "standalone": true,
  "fields": [
    {
      "label": "Formato",
      "attribute": "format",
      "type": "select",
      "props": {
        "options": [
          {"value": "csv", "label": "CSV"},
          {"value": "xlsx", "label": "Excel"},
          {"value": "pdf", "label": "PDF"}
        ]
      }
    }
  ]
}
```

**Request:**
```json
{
  "resources": [],
  "format": "xlsx"
}
```

---

## Ejemplos de Implementación

### TypeScript - Tipos

```typescript
interface Action {
  key: string;
  name: string;
  fields: ActionField[];
  showOnIndex: boolean;
  showOnDetail: boolean;
  destructive: boolean;
  standalone: boolean;
  confirmText: string | null;
  confirmButtonText: string;
  cancelButtonText: string;
}

interface ActionField {
  label: string;
  attribute: string;
  type: string;
  component: string;
  rules: string[];
  props: Record<string, any>;
}

type ActionResponseType = 'message' | 'danger' | 'redirect' | 'download' | 'openInNewTab';

interface ActionResponse {
  type: ActionResponseType;
  message?: string;
  url?: string;
  filename?: string;
  openInNewTab?: boolean;
  data?: Record<string, any>;
}
```

### React - Hook useActions

```typescript
import { useState, useCallback } from 'react';

interface UseActionsOptions {
  resourceKey: string;
  onSuccess?: () => void;
}

export function useActions({ resourceKey, onSuccess }: UseActionsOptions) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const getActions = useCallback(async (context: 'index' | 'detail' = 'index') => {
    const response = await fetch(
      `/nadota-api/${resourceKey}/resource/actions?context=${context}`,
      { headers: getAuthHeaders() }
    );
    return response.json();
  }, [resourceKey]);

  const getActionFields = useCallback(async (actionKey: string) => {
    const response = await fetch(
      `/nadota-api/${resourceKey}/resource/actions/${actionKey}/fields`,
      { headers: getAuthHeaders() }
    );
    return response.json();
  }, [resourceKey]);

  const executeAction = useCallback(async (
    actionKey: string,
    resourceIds: number[],
    fields: Record<string, any> = {}
  ): Promise<ActionResponse> => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch(
        `/nadota-api/${resourceKey}/resource/actions/${actionKey}`,
        {
          method: 'POST',
          headers: {
            ...getAuthHeaders(),
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            resources: resourceIds,
            ...fields,
          }),
        }
      );

      const result: ActionResponse = await response.json();

      // Handle response based on type
      switch (result.type) {
        case 'message':
          toast.success(result.message);
          onSuccess?.();
          break;
        case 'danger':
          toast.error(result.message);
          break;
        case 'redirect':
          router.push(result.url!);
          break;
        case 'download':
          downloadFile(result.url!, result.filename!);
          break;
        case 'openInNewTab':
          window.open(result.url, '_blank');
          break;
      }

      return result;
    } catch (err) {
      setError(err.message);
      throw err;
    } finally {
      setLoading(false);
    }
  }, [resourceKey, onSuccess]);

  return {
    loading,
    error,
    getActions,
    getActionFields,
    executeAction,
  };
}
```

### Vue 3 - Componente ActionDropdown

```vue
<script setup lang="ts">
import { ref, computed } from 'vue';

interface Action {
  key: string;
  name: string;
  fields: any[];
  destructive: boolean;
  standalone: boolean;
  confirmText: string | null;
}

const props = defineProps<{
  resourceKey: string;
  selectedIds: number[];
  context?: 'index' | 'detail';
}>();

const emit = defineEmits<{
  (e: 'executed'): void;
}>();

const actions = ref<Action[]>([]);
const showModal = ref(false);
const currentAction = ref<Action | null>(null);
const actionFields = ref<any[]>([]);
const formData = ref<Record<string, any>>({});
const loading = ref(false);

// Cargar acciones
async function loadActions() {
  const response = await fetch(
    `/nadota-api/${props.resourceKey}/resource/actions?context=${props.context || 'index'}`
  );
  const data = await response.json();
  actions.value = data.actions;
}

// Determinar si una acción está habilitada
function isActionEnabled(action: Action): boolean {
  if (action.standalone) return true;
  return props.selectedIds.length > 0;
}

// Iniciar ejecución de acción
async function initiateAction(action: Action) {
  currentAction.value = action;

  // Cargar campos si los tiene
  if (action.fields.length > 0) {
    const response = await fetch(
      `/nadota-api/${props.resourceKey}/resource/actions/${action.key}/fields`
    );
    const data = await response.json();
    actionFields.value = data.fields;
  }

  showModal.value = true;
}

// Ejecutar acción
async function executeAction() {
  if (!currentAction.value) return;

  loading.value = true;

  try {
    const response = await fetch(
      `/nadota-api/${props.resourceKey}/resource/actions/${currentAction.value.key}`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          resources: props.selectedIds,
          ...formData.value,
        }),
      }
    );

    const result = await response.json();
    handleActionResponse(result);
  } finally {
    loading.value = false;
    showModal.value = false;
    currentAction.value = null;
    formData.value = {};
  }
}

function handleActionResponse(result: any) {
  switch (result.type) {
    case 'message':
      notify.success(result.message);
      emit('executed');
      break;
    case 'danger':
      notify.error(result.message);
      break;
    case 'redirect':
      router.push(result.url);
      break;
    case 'download':
      window.location.href = result.url;
      break;
    case 'openInNewTab':
      window.open(result.url, '_blank');
      break;
  }
}

onMounted(loadActions);
</script>

<template>
  <div class="action-dropdown">
    <Menu>
      <MenuButton class="btn-secondary">
        Acciones
        <ChevronDownIcon class="w-4 h-4 ml-2" />
      </MenuButton>

      <MenuItems>
        <MenuItem
          v-for="action in actions"
          :key="action.key"
          :disabled="!isActionEnabled(action)"
          @click="initiateAction(action)"
        >
          <span :class="{ 'text-red-600': action.destructive }">
            {{ action.name }}
          </span>
        </MenuItem>
      </MenuItems>
    </Menu>

    <!-- Modal de confirmación/formulario -->
    <Modal v-model="showModal">
      <template #header>
        <span :class="{ 'text-red-600': currentAction?.destructive }">
          {{ currentAction?.name }}
        </span>
      </template>

      <template #body>
        <!-- Texto de confirmación -->
        <p v-if="currentAction?.confirmText" class="mb-4">
          {{ currentAction.confirmText }}
        </p>

        <!-- Formulario de campos -->
        <form v-if="actionFields.length > 0" @submit.prevent="executeAction">
          <FieldRenderer
            v-for="field in actionFields"
            :key="field.attribute"
            :field="field"
            v-model="formData[field.attribute]"
          />
        </form>

        <!-- Info de selección -->
        <p class="text-sm text-gray-500 mt-4">
          {{ selectedIds.length }} recurso(s) seleccionado(s)
        </p>
      </template>

      <template #footer>
        <button @click="showModal = false" class="btn-secondary">
          {{ currentAction?.cancelButtonText || 'Cancelar' }}
        </button>
        <button
          @click="executeAction"
          :class="currentAction?.destructive ? 'btn-danger' : 'btn-primary'"
          :disabled="loading"
        >
          {{ currentAction?.confirmButtonText || 'Ejecutar' }}
        </button>
      </template>
    </Modal>
  </div>
</template>
```

---

## Manejo de Errores

### Error de Validación (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "subject": ["El campo asunto es obligatorio."],
    "message": ["El mensaje debe tener al menos 10 caracteres."]
  }
}
```

### Error de Autorización (403)

```json
{
  "message": "No tienes permisos para ejecutar esta acción."
}
```

### Error de Servidor (500)

```json
{
  "type": "danger",
  "message": "Error interno al procesar la acción."
}
```

---

## Buenas Prácticas

1. **Siempre verificar `standalone`** antes de habilitar el botón de acción
2. **Mostrar indicador de carga** durante la ejecución
3. **Refrescar la lista** después de acciones exitosas tipo `message`
4. **Validar campos en el frontend** antes de enviar
5. **Mostrar confirmación** para acciones destructivas
6. **Manejar todos los tipos de respuesta** en el frontend
7. **Cachear la lista de acciones** si no cambia frecuentemente
8. **Deshabilitar botones** mientras se ejecuta una acción

---

## Referencias

- [Documentación General de API](./API.md)
- [Documentación de Campos](./FIELDS.md)
