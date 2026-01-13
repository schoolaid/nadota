# Signature Field

Campo para capturar firmas digitales. Soporta almacenamiento como base64 en la base de datos o como archivo en disco (S3, local, etc.).

## Uso Basico

```php
use SchoolAid\Nadota\Http\Fields\Signature;

// Almacenar como base64 (default)
Signature::make('Firma', 'signature');

// Almacenar en S3
Signature::make('Firma', 'signature')
    ->disk('s3')
    ->path('signatures/');
```

---

## Modos de Almacenamiento

### Base64 (Default)

Almacena la firma como data URL en la base de datos. Ideal para firmas pequenas o cuando no necesitas archivos separados.

```php
Signature::make('Firma', 'signature')
    ->storeAsBase64();  // Explicitamente (es el default)
```

**Ventajas:**
- No requiere configuracion de storage
- Todo en un solo campo
- Facil de migrar

**Desventajas:**
- Aumenta el tamano de la base de datos
- No se puede cachear por CDN

### Disco (S3, Local, etc.)

Convierte el base64 a archivo y lo almacena en disco.

```php
Signature::make('Firma', 'signature')
    ->disk('s3')
    ->path('contracts/signatures');

// Con URLs temporales (para buckets privados)
Signature::make('Firma', 'signature')
    ->disk('s3')
    ->path('signatures/')
    ->temporaryUrl(60);  // 60 minutos

// Con cache de URLs
Signature::make('Firma', 'signature')
    ->disk('s3')
    ->path('signatures/')
    ->cachedTemporaryUrl(30, 60);  // cache 30min, url 60min
```

---

## Formato de Imagen

```php
// PNG (default) - soporta transparencia
Signature::make('Firma', 'signature')
    ->png();

// JPEG - menor tamano, sin transparencia
Signature::make('Firma', 'signature')
    ->jpeg(85);  // calidad 85%

// WebP - moderno, buen balance
Signature::make('Firma', 'signature')
    ->webp(90);

// SVG - vectorial, escalable
Signature::make('Firma', 'signature')
    ->svg();
```

---

## Configuracion del Canvas

```php
Signature::make('Firma', 'signature')
    // Dimensiones
    ->dimensions(500, 200)      // ancho x alto
    ->canvasWidth(500)          // solo ancho
    ->canvasHeight(200)         // solo alto

    // Pen/stroke
    ->penColor('#000000')       // color de la linea
    ->penWidth(3)               // grosor de la linea

    // Fondo
    ->backgroundColor('#f5f5f5') // color de fondo
    ->whiteBackground()          // fondo blanco
    ->transparent();             // fondo transparente (default)
```

---

## Opciones de UI

```php
Signature::make('Firma', 'signature')
    // Permitir limpiar
    ->clearable()           // default: true
    ->clearable(false)      // no permitir limpiar

    // Texto cuando esta vacio
    ->emptyText('Firme aqui')

    // Permitir firma escrita (ademas de dibujada)
    ->allowTyped(true, 'Dancing Script');  // font para firma escrita
```

---

## Ejemplo Completo

```php
// FilledFormItemResource.php
public function fields(Request $request): array
{
    return [
        DynamicField::make('Value', 'value')
            ->basedOn('formItem.type')
            ->types([
                // ... otros tipos

                6 => Signature::make('Firma', 'value')
                        ->disk('s3')
                        ->path('form-signatures/')
                        ->png()
                        ->dimensions(400, 150)
                        ->penColor('#1a1a1a')
                        ->penWidth(2)
                        ->whiteBackground()
                        ->cachedTemporaryUrl()
                        ->emptyText('Firme aqui'),
            ]),
    ];
}
```

---

## Salida JSON

### Con base64

```json
{
  "type": "signature",
  "component": "FieldSignature",
  "props": {
    "storageMode": "base64",
    "format": "png",
    "quality": 90,
    "canvasWidth": 400,
    "canvasHeight": 200,
    "penColor": "#000000",
    "penWidth": 2,
    "backgroundColor": null,
    "clearable": true,
    "emptyText": null,
    "allowTypedSignature": false,
    "typedFont": "cursive"
  },
  "value": {
    "type": "base64",
    "data": "data:image/png;base64,iVBORw0KGgo...",
    "dataUrl": "data:image/png;base64,iVBORw0KGgo..."
  }
}
```

### Con disco

```json
{
  "type": "signature",
  "component": "FieldSignature",
  "props": {
    "storageMode": "disk",
    "format": "png"
  },
  "value": {
    "type": "file",
    "path": "signatures/signature_123_1704067200_abc123.png",
    "url": "https://bucket.s3.amazonaws.com/signatures/..."
  }
}
```

---

## Frontend Implementation

```vue
<template>
  <div class="signature-field">
    <!-- Canvas para dibujar -->
    <canvas
      ref="canvas"
      :width="props.canvasWidth"
      :height="props.canvasHeight"
      :style="{ backgroundColor: props.backgroundColor || 'transparent' }"
      @mousedown="startDrawing"
      @mousemove="draw"
      @mouseup="stopDrawing"
      @touchstart="startDrawing"
      @touchmove="draw"
      @touchend="stopDrawing"
    />

    <!-- Controles -->
    <div class="signature-controls">
      <button v-if="props.clearable" @click="clear">
        Clear
      </button>
    </div>

    <!-- Preview de firma existente -->
    <div v-if="value && !isDrawing" class="signature-preview">
      <img
        v-if="value.type === 'base64'"
        :src="value.dataUrl"
        alt="Signature"
      />
      <img
        v-else-if="value.type === 'file'"
        :src="value.url"
        alt="Signature"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
  field: Object,
  modelValue: Object,
});

const emit = defineEmits(['update:modelValue']);

const canvas = ref(null);
const ctx = ref(null);
const isDrawing = ref(false);

onMounted(() => {
  ctx.value = canvas.value.getContext('2d');
  ctx.value.strokeStyle = props.field.props.penColor;
  ctx.value.lineWidth = props.field.props.penWidth;
  ctx.value.lineCap = 'round';
});

function startDrawing(e) {
  isDrawing.value = true;
  ctx.value.beginPath();
  ctx.value.moveTo(getX(e), getY(e));
}

function draw(e) {
  if (!isDrawing.value) return;
  ctx.value.lineTo(getX(e), getY(e));
  ctx.value.stroke();
}

function stopDrawing() {
  isDrawing.value = false;
  emitValue();
}

function clear() {
  ctx.value.clearRect(0, 0, canvas.value.width, canvas.value.height);
  emit('update:modelValue', 'clear');
}

function emitValue() {
  const dataUrl = canvas.value.toDataURL(`image/${props.field.props.format}`);
  emit('update:modelValue', dataUrl);
}

function getX(e) {
  const rect = canvas.value.getBoundingClientRect();
  return (e.touches?.[0]?.clientX ?? e.clientX) - rect.left;
}

function getY(e) {
  const rect = canvas.value.getBoundingClientRect();
  return (e.touches?.[0]?.clientY ?? e.clientY) - rect.top;
}
</script>
```

---

## Migracion de Base de Datos

```php
// Para base64 - campo TEXT o LONGTEXT
Schema::table('filled_form_items', function (Blueprint $table) {
    $table->longText('value')->nullable()->change();
});

// Para disco - campo VARCHAR
Schema::table('contracts', function (Blueprint $table) {
    $table->string('signature_path', 500)->nullable();
});
```

---

## Validacion

```php
Signature::make('Firma', 'signature')
    ->required()
    ->rules('max:500000');  // max 500KB para base64
```

---

## Best Practices

1. **Para formularios con muchas firmas**: Usa almacenamiento en disco para no sobrecargar la DB

2. **Para firmas legales**: Usa PNG o SVG para maxima calidad

3. **Para formularios moviles**: Ajusta las dimensiones del canvas para pantallas tactiles

4. **Para S3 privado**: Siempre usa `cachedTemporaryUrl()` para mejor performance

5. **Considera el tamano**: Una firma en base64 PNG puede ser 10-50KB, en JPEG 5-20KB
