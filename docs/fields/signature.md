# Signature

`SchoolAid\Nadota\Http\Fields\Signature` (`type: signature`, component `FieldSignature`) provides a drawing canvas for capturing handwritten signatures. The result can be stored as a base64 data URL in the database or written to a file on a storage disk.

It inherits the full shared API from [README.md](README.md#shared-field-api).

## Quick start

```php
use SchoolAid\Nadota\Http\Fields\Signature;

// Store inline as base64 (default)
Signature::make('Signature', 'signature');

// Store as a PNG file on the public disk
Signature::make('Signature', 'signature')
    ->storeOnDisk('public', 'signatures')
    ->png()
    ->whiteBackground();
```

## Storage modes

By default the signature is stored as a base64 string (`storeAsBase64()`). In disk mode, base64 data submitted from the frontend is decoded and saved as a file; the model stores the file path and the previous file is deleted on replace.

| Method | Description |
| ------ | ----------- |
| `storeAsBase64()` | Store the data URL directly in the column (default). |
| `storeOnDisk(?string $disk = null, ?string $path = null)` | Store as a file on a disk. |
| `disk(string $disk)` | Set the disk (also switches to disk mode). |
| `path(string $path)` | Set the storage path (defaults to `signatures`). |

## Image format

| Method | Description |
| ------ | ----------- |
| `format(string $format)` | Set format (`png`, `jpeg`, `webp`, `svg`). |
| `png()` | PNG (default). |
| `jpeg(int $quality = 90)` | JPEG with quality. |
| `webp(int $quality = 90)` | WebP with quality. |
| `svg()` | SVG. |
| `quality(int $quality)` | Quality for JPEG/WebP (clamped to 1–100). |

## Canvas & pen

| Method | Description |
| ------ | ----------- |
| `dimensions(int $width, int $height)` | Canvas size (defaults 400×200). |
| `canvasWidth(int $width)` / `canvasHeight(int $height)` | Single-axis size. |
| `penColor(string $color)` | Stroke color (default `#000000`). |
| `penWidth(int $width)` | Stroke width (default `2`). |
| `backgroundColor(string $color)` | Background color. |
| `transparent()` | Transparent background (default). |
| `whiteBackground()` | White background (`#ffffff`). |

## URLs (disk mode)

| Method | Description |
| ------ | ----------- |
| `temporaryUrl(int $minutes = 30)` | Use temporary signed URLs. |
| `cache(int $minutes = 30)` | Cache generated URLs. |
| `cachedTemporaryUrl(int $cacheMinutes = 30, int $urlMinutes = 30)` | Combine caching + temporary URLs. |

## UX options

| Method | Description |
| ------ | ----------- |
| `clearable(bool $clearable = true)` | Allow clearing (default on). |
| `emptyText(string $text)` | Placeholder text when empty. |
| `allowTyped(bool $allow = true, string $font = 'cursive')` | Offer a typed-signature alternative. |

## Resolved value

`resolve()` returns a structured array:

- For base64 data: `['type' => 'base64', 'data' => ..., 'dataUrl' => 'data:image/...;base64,...']`. The MIME type in the data URL is derived from the configured `format`.
- For a file path (disk mode): `['type' => 'file', 'path' => ..., 'url' => ...]`, where the URL respects the temporary-URL and caching settings.

## Clearing

Submitting an empty string or the literal `clear` for the field deletes any stored file (in disk mode) and sets the attribute to `null`.

## Full example

```php
Signature::make('Customer signature', 'customer_signature')
    ->storeOnDisk('s3', 'contracts/signatures')
    ->jpeg(85)
    ->dimensions(600, 250)
    ->penColor('#1a1a1a')->penWidth(3)
    ->whiteBackground()
    ->cachedTemporaryUrl(60, 30)
    ->allowTyped(true, 'cursive')
    ->required();
```
