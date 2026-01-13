# Signature Field - Frontend API Reference

Technical documentation for frontend developers implementing the Signature component.

---

## Response Structure

### Field JSON

```json
{
  "key": "signature",
  "label": "Firma",
  "attribute": "signature",
  "type": "signature",
  "component": "FieldSignature",
  "required": true,
  "disabled": false,
  "readonly": false,
  "showOnIndex": true,
  "showOnDetail": true,
  "showOnCreation": true,
  "showOnUpdate": true,
  "props": {
    "storageMode": "disk",
    "format": "png",
    "quality": 90,
    "canvasWidth": 400,
    "canvasHeight": 200,
    "penColor": "#000000",
    "penWidth": 2,
    "backgroundColor": null,
    "clearable": true,
    "emptyText": "Firme aquí",
    "allowTypedSignature": false,
    "typedFont": "cursive"
  },
  "value": {
    "type": "file",
    "path": "signatures/signature_123_1704067200_abc123.png",
    "url": "https://bucket.s3.amazonaws.com/signatures/..."
  }
}
```

---

## Props Reference

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `storageMode` | `'base64' \| 'disk'` | `'base64'` | How the signature is stored |
| `format` | `'png' \| 'jpeg' \| 'webp' \| 'svg'` | `'png'` | Image output format |
| `quality` | `number` | `90` | Compression quality (1-100, for JPEG/WebP) |
| `canvasWidth` | `number` | `400` | Canvas width in pixels |
| `canvasHeight` | `number` | `200` | Canvas height in pixels |
| `penColor` | `string` | `'#000000'` | Stroke color (CSS color) |
| `penWidth` | `number` | `2` | Stroke width in pixels |
| `backgroundColor` | `string \| null` | `null` | Background color (`null` = transparent) |
| `clearable` | `boolean` | `true` | Allow clearing the signature |
| `emptyText` | `string \| null` | `null` | Placeholder text when empty |
| `allowTypedSignature` | `boolean` | `false` | Allow typing name as signature |
| `typedFont` | `string` | `'cursive'` | Font for typed signature |

---

## Value Formats

### Base64 Storage

When `storageMode: 'base64'`:

```json
{
  "type": "base64",
  "data": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...",
  "dataUrl": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA..."
}
```

### Disk Storage

When `storageMode: 'disk'`:

```json
{
  "type": "file",
  "path": "signatures/signature_123_1704067200_abc123.png",
  "url": "https://bucket.s3.amazonaws.com/signatures/signature_123.png"
}
```

### Empty Value

```json
null
```

---

## TypeScript Definitions

```typescript
interface SignatureFieldProps {
  storageMode: 'base64' | 'disk';
  format: 'png' | 'jpeg' | 'webp' | 'svg';
  quality: number;
  canvasWidth: number;
  canvasHeight: number;
  penColor: string;
  penWidth: number;
  backgroundColor: string | null;
  clearable: boolean;
  emptyText: string | null;
  allowTypedSignature: boolean;
  typedFont: string;
}

interface SignatureValueBase64 {
  type: 'base64';
  data: string;
  dataUrl: string;
}

interface SignatureValueFile {
  type: 'file';
  path: string;
  url: string;
}

type SignatureValue = SignatureValueBase64 | SignatureValueFile | null;
```

---

## Form Submission

### Sending New Signature

Send the data URL string from canvas:

```
POST /api/resource/{id}
Content-Type: application/json

{
  "signature": "data:image/png;base64,iVBORw0KGgo..."
}
```

### Clearing Signature

Send the special value `'clear'`:

```
POST /api/resource/{id}
Content-Type: application/json

{
  "signature": "clear"
}
```

### Server Behavior

| `storageMode` | Server Action |
|---------------|---------------|
| `base64` | Stores data URL directly in database |
| `disk` | Converts base64 to file, stores on disk, saves path |

---

## Display Logic

| Condition | Display |
|-----------|---------|
| `value === null` | Show empty state with `emptyText` |
| `value.type === 'base64'` | Show image from `value.dataUrl` |
| `value.type === 'file'` | Show image from `value.url` |
| `readonly === true` | Show preview only, disable interactions |
| `disabled === true` | Show preview only, disable interactions |

---

## Canvas Configuration

### Dimensions

Use `canvasWidth` and `canvasHeight` for the drawing area size in pixels.

### Pen Settings

| Property | Usage |
|----------|-------|
| `penColor` | CSS color for stroke (e.g., `#000000`, `rgb(0,0,0)`) |
| `penWidth` | Stroke width in pixels |

### Background

| Value | Result |
|-------|--------|
| `null` | Transparent background |
| `'#ffffff'` | White background |
| Any CSS color | Solid color background |

---

## Export Format

| Format | MIME Type | Transparency | Use Case |
|--------|-----------|--------------|----------|
| `png` | `image/png` | Yes | Default, best quality |
| `jpeg` | `image/jpeg` | No | Smaller file size |
| `webp` | `image/webp` | Yes | Modern, good compression |
| `svg` | `image/svg+xml` | Yes | Vector, scalable |

The `quality` prop (1-100) only applies to `jpeg` and `webp` formats.

---

## Validation

| Rule | When to Apply |
|------|---------------|
| Required | Check if canvas is empty before submit |
| Format | Validate data URL prefix matches expected format |

---

## Libraries

Recommended libraries for signature capture:

| Library | Platform |
|---------|----------|
| [signature_pad](https://github.com/szimek/signature_pad) | Vanilla JS |
| [react-signature-canvas](https://www.npmjs.com/package/react-signature-canvas) | React |
| [vue-signature-pad](https://www.npmjs.com/package/vue-signature-pad) | Vue |

---

## Summary

| Responsibility | Frontend Action |
|----------------|-----------------|
| Render canvas | Use `canvasWidth`, `canvasHeight`, `backgroundColor` |
| Configure pen | Use `penColor`, `penWidth` |
| Capture signature | Handle mouse/touch events on canvas |
| Export data | Convert canvas to data URL with `format` and `quality` |
| Clear signature | Reset canvas, emit `'clear'` value |
| Display existing | Load image from `value.dataUrl` or `value.url` |
| Handle readonly | Disable interactions, show preview only |
