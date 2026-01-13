<?php

namespace SchoolAid\Nadota\Http\Fields;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Signature extends Field
{
    /**
     * Storage mode: 'base64' or 'disk'
     */
    protected string $storageMode = 'base64';

    /**
     * Disk for file storage (when mode is 'disk').
     */
    protected ?string $disk = null;

    /**
     * Path for file storage.
     */
    protected ?string $path = null;

    /**
     * Image format: 'png', 'jpeg', 'svg', 'webp'
     */
    protected string $format = 'png';

    /**
     * Image quality for JPEG/WebP (1-100).
     */
    protected int $quality = 90;

    /**
     * Canvas width.
     */
    protected int $canvasWidth = 400;

    /**
     * Canvas height.
     */
    protected int $canvasHeight = 200;

    /**
     * Stroke/pen color.
     */
    protected string $penColor = '#000000';

    /**
     * Stroke/pen width.
     */
    protected int $penWidth = 2;

    /**
     * Background color (null for transparent).
     */
    protected ?string $backgroundColor = null;

    /**
     * Whether to use temporary URLs for disk storage.
     */
    protected bool $useTemporaryUrl = false;

    /**
     * Minutes for temporary URL validity.
     */
    protected int $temporaryUrlMinutes = 30;

    /**
     * Whether to cache URLs.
     */
    protected bool $cacheUrl = false;

    /**
     * Cache duration in minutes.
     */
    protected int $cacheMinutes = 30;

    /**
     * Whether the signature is clearable.
     */
    protected bool $clearable = true;

    /**
     * Placeholder text when empty.
     */
    protected ?string $emptyText = null;

    /**
     * Show typed name option.
     */
    protected bool $allowTypedSignature = false;

    /**
     * Font for typed signature.
     */
    protected string $typedFont = 'cursive';

    public function __construct(string $name, string $attribute)
    {
        parent::__construct(
            $name,
            $attribute,
            FieldType::SIGNATURE->value,
            static::safeConfig('nadota.fields.signature.component', 'FieldSignature')
        );
    }

    /**
     * Store signature as base64 in the database.
     */
    public function storeAsBase64(): static
    {
        $this->storageMode = 'base64';

        return $this;
    }

    /**
     * Store signature as file on disk.
     */
    public function storeOnDisk(?string $disk = null, ?string $path = null): static
    {
        $this->storageMode = 'disk';
        $this->disk = $disk;
        $this->path = $path;

        return $this;
    }

    /**
     * Set the storage disk (shorthand for storeOnDisk).
     */
    public function disk(string $disk): static
    {
        $this->storageMode = 'disk';
        $this->disk = $disk;

        return $this;
    }

    /**
     * Set the storage path.
     */
    public function path(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Set image format.
     */
    public function format(string $format): static
    {
        $this->format = strtolower($format);

        return $this;
    }

    /**
     * Use PNG format.
     */
    public function png(): static
    {
        return $this->format('png');
    }

    /**
     * Use JPEG format with optional quality.
     */
    public function jpeg(int $quality = 90): static
    {
        $this->format = 'jpeg';
        $this->quality = $quality;

        return $this;
    }

    /**
     * Use WebP format with optional quality.
     */
    public function webp(int $quality = 90): static
    {
        $this->format = 'webp';
        $this->quality = $quality;

        return $this;
    }

    /**
     * Use SVG format.
     */
    public function svg(): static
    {
        return $this->format('svg');
    }

    /**
     * Set image quality (for JPEG/WebP).
     */
    public function quality(int $quality): static
    {
        $this->quality = min(100, max(1, $quality));

        return $this;
    }

    /**
     * Set canvas dimensions.
     */
    public function dimensions(int $width, int $height): static
    {
        $this->canvasWidth = $width;
        $this->canvasHeight = $height;

        return $this;
    }

    /**
     * Set canvas width.
     */
    public function canvasWidth(int $width): static
    {
        $this->canvasWidth = $width;

        return $this;
    }

    /**
     * Set canvas height.
     */
    public function canvasHeight(int $height): static
    {
        $this->canvasHeight = $height;

        return $this;
    }

    /**
     * Set pen/stroke color.
     */
    public function penColor(string $color): static
    {
        $this->penColor = $color;

        return $this;
    }

    /**
     * Set pen/stroke width.
     */
    public function penWidth(int $width): static
    {
        $this->penWidth = $width;

        return $this;
    }

    /**
     * Set background color.
     */
    public function backgroundColor(string $color): static
    {
        $this->backgroundColor = $color;

        return $this;
    }

    /**
     * Set transparent background (default for PNG).
     */
    public function transparent(): static
    {
        $this->backgroundColor = null;

        return $this;
    }

    /**
     * Set white background.
     */
    public function whiteBackground(): static
    {
        $this->backgroundColor = '#ffffff';

        return $this;
    }

    /**
     * Use temporary URLs for disk storage.
     */
    public function temporaryUrl(int $minutes = 30): static
    {
        $this->useTemporaryUrl = true;
        $this->temporaryUrlMinutes = $minutes;

        return $this;
    }

    /**
     * Cache the URL.
     */
    public function cache(int $minutes = 30): static
    {
        $this->cacheUrl = true;
        $this->cacheMinutes = $minutes;

        return $this;
    }

    /**
     * Use cached temporary URL (common for S3).
     */
    public function cachedTemporaryUrl(int $cacheMinutes = 30, int $urlMinutes = 30): static
    {
        $this->temporaryUrl($urlMinutes);
        $this->cache($cacheMinutes);

        return $this;
    }

    /**
     * Make signature clearable.
     */
    public function clearable(bool $clearable = true): static
    {
        $this->clearable = $clearable;

        return $this;
    }

    /**
     * Set empty state text.
     */
    public function emptyText(string $text): static
    {
        $this->emptyText = $text;

        return $this;
    }

    /**
     * Allow typed signature as alternative.
     */
    public function allowTyped(bool $allow = true, string $font = 'cursive'): static
    {
        $this->allowTypedSignature = $allow;
        $this->typedFont = $font;

        return $this;
    }

    /**
     * Resolve the signature value for display.
     */
    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $value = parent::resolve($request, $model, $resource);

        if (!$value) {
            return null;
        }

        // Handle array values (from JSON cast)
        if (is_array($value)) {
            // If it already has the expected structure, return as-is
            if (isset($value['type']) && in_array($value['type'], ['base64', 'file'])) {
                return $value;
            }

            // If it has a 'value' key, extract it
            if (isset($value['value'])) {
                $value = $value['value'];
            } else {
                // Can't determine the structure, return null
                return null;
            }
        }

        // Ensure value is a string at this point
        if (!is_string($value)) {
            return null;
        }

        // Check if it's a base64 string
        if ($this->isBase64($value)) {
            return [
                'type' => 'base64',
                'data' => $value,
                'dataUrl' => $this->ensureDataUrl($value),
            ];
        }

        // It's a file path
        return [
            'type' => 'file',
            'path' => $value,
            'url' => $this->getFileUrl($value, $model),
        ];
    }

    /**
     * Check if value is a base64 string.
     */
    protected function isBase64(string $value): bool
    {
        // Check if it starts with data: prefix
        if (str_starts_with($value, 'data:')) {
            return true;
        }

        // Check if it's a valid base64 string (no path separators, valid base64 chars)
        if (preg_match('/^[a-zA-Z0-9\/+]+=*$/', $value) && !str_contains($value, '/')) {
            return true;
        }

        return false;
    }

    /**
     * Ensure value has data URL prefix.
     */
    protected function ensureDataUrl(string $value): string
    {
        if (str_starts_with($value, 'data:')) {
            return $value;
        }

        $mimeType = match ($this->format) {
            'jpeg', 'jpg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };

        return "data:{$mimeType};base64,{$value}";
    }

    /**
     * Get file URL for disk storage.
     */
    protected function getFileUrl(string $path, ?Model $model = null): ?string
    {
        if (!$path) {
            return null;
        }

        $disk = $this->disk ?? config('filesystems.default');

        try {
            if ($this->cacheUrl && $model) {
                return \Cache::remember(
                    "signature_url_{$model->getKey()}_{$this->getAttribute()}",
                    now()->addMinutes($this->cacheMinutes),
                    fn() => $this->generateFileUrl($disk, $path)
                );
            }

            return $this->generateFileUrl($disk, $path);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate file URL.
     */
    protected function generateFileUrl(string $disk, string $path): ?string
    {
        try {
            if ($this->useTemporaryUrl && Storage::disk($disk)->providesTemporaryUrls()) {
                return Storage::disk($disk)->temporaryUrl(
                    $path,
                    now()->addMinutes($this->temporaryUrlMinutes)
                );
            }

            return Storage::disk($disk)->url($path);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Fill the model with signature data.
     */
    public function fill(Request $request, Model $model): void
    {
        if ($this->isReadonly() || $this->isDisabled()) {
            return;
        }

        $attribute = $this->getAttribute();
        $value = $request->input($attribute);

        if ($value === null) {
            return;
        }

        // Handle clear action
        if ($value === '' || $value === 'clear') {
            $this->deleteOldFile($model);
            $model->{$attribute} = null;
            return;
        }

        // Check if it's a base64 data URL
        if (str_starts_with($value, 'data:')) {
            if ($this->storageMode === 'disk') {
                // Convert base64 to file and store
                $filePath = $this->storeBase64AsFile($value, $model);
                $model->{$attribute} = $filePath;
            } else {
                // Store as base64 directly
                $model->{$attribute} = $value;
            }
            return;
        }

        // If it's already a path and we're in disk mode, keep it
        if ($this->storageMode === 'disk' && !$this->isBase64($value)) {
            $model->{$attribute} = $value;
        }
    }

    /**
     * Store base64 data as a file.
     */
    protected function storeBase64AsFile(string $dataUrl, Model $model): ?string
    {
        // Extract base64 data
        $data = $this->extractBase64Data($dataUrl);

        if (!$data) {
            return null;
        }

        $disk = $this->disk ?? config('filesystems.default');
        $path = $this->path ?? 'signatures';

        // Generate unique filename
        $filename = $this->generateFilename($model);

        // Full path
        $fullPath = rtrim($path, '/') . '/' . $filename;

        // Delete old file if exists
        $this->deleteOldFile($model);

        // Store the file
        Storage::disk($disk)->put($fullPath, $data);

        return $fullPath;
    }

    /**
     * Extract binary data from base64 data URL.
     */
    protected function extractBase64Data(string $dataUrl): ?string
    {
        // Remove data URL prefix
        if (preg_match('/^data:image\/\w+;base64,(.+)$/', $dataUrl, $matches)) {
            return base64_decode($matches[1]);
        }

        // Try as raw base64
        $decoded = base64_decode($dataUrl, true);

        return $decoded !== false ? $decoded : null;
    }

    /**
     * Generate unique filename for signature.
     */
    protected function generateFilename(Model $model): string
    {
        $timestamp = time();
        $random = Str::random(8);
        $modelKey = $model->getKey() ?? 'new';

        $extension = match ($this->format) {
            'jpeg', 'jpg' => 'jpg',
            'webp' => 'webp',
            'svg' => 'svg',
            default => 'png',
        };

        return "signature_{$modelKey}_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Delete old signature file.
     */
    protected function deleteOldFile(Model $model): void
    {
        $oldValue = $model->getOriginal($this->getAttribute());

        if ($oldValue && $this->storageMode === 'disk' && !$this->isBase64($oldValue)) {
            $disk = $this->disk ?? config('filesystems.default');

            try {
                Storage::disk($disk)->delete($oldValue);
            } catch (\Exception $e) {
                // Ignore deletion errors
            }
        }
    }

    /**
     * Get field props.
     */
    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'storageMode' => $this->storageMode,
            'format' => $this->format,
            'quality' => $this->quality,
            'canvasWidth' => $this->canvasWidth,
            'canvasHeight' => $this->canvasHeight,
            'penColor' => $this->penColor,
            'penWidth' => $this->penWidth,
            'backgroundColor' => $this->backgroundColor,
            'clearable' => $this->clearable,
            'emptyText' => $this->emptyText,
            'allowTypedSignature' => $this->allowTypedSignature,
            'typedFont' => $this->typedFont,
        ]);
    }

    /**
     * Get validation rules.
     */
    public function getRules(): array
    {
        $rules = parent::getRules();

        // Add base64 or file validation
        // Note: Custom validation may be needed for base64 data URLs

        return $rules;
    }
}
