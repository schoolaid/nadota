<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class File extends Field
{
    protected array $acceptedTypes = [];
    protected ?int $maxSize = null; // in bytes
    protected ?string $disk = null;
    protected ?string $path = null;
    protected ?string $visibility = null; // 'public' or 'private'
    protected bool $downloadable = true;
    protected ?string $downloadRoute = null;
    protected bool $useTemporaryUrl = false;
    protected ?int $temporaryUrlMinutes = 29;
    protected bool $signUrl = true;
    protected bool $storedAsUrl = false;
    protected bool $cacheUrl = false;
    protected ?int $cacheMinutes = 30;
    protected ?string $cachePrefix = null;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::FILE->value, static::safeConfig('nadota.fields.file.component', 'FieldFile'));

        // Set default max size from config or 10MB
        $this->maxSize = static::safeConfig('nadota.fields.file.max_size', 10 * 1024 * 1024);
    }

    /**
     * Set accepted file types (MIME types or extensions).
     */
    public function accept(array $types): static
    {
        $this->acceptedTypes = $types;
        return $this;
    }

    /**
     * Set maximum file size in bytes.
     */
    public function maxSize(int $bytes): static
    {
        $this->maxSize = $bytes;
        return $this;
    }

    /**
     * Set maximum file size in megabytes (convenience method).
     */
    public function maxSizeMB(int $megabytes): static
    {
        $this->maxSize = $megabytes * 1024 * 1024;
        return $this;
    }

    /**
     * Set the storage disk for file uploads.
     */
    public function disk(string $disk): static
    {
        $this->disk = $disk;
        return $this;
    }

    /**
     * Set the storage path for file uploads.
     */
    public function path(string $path): static
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Set the file visibility ('public' or 'private').
     */
    public function visibility(string $visibility): static
    {
        $this->visibility = $visibility;
        return $this;
    }

    /**
     * Set the file visibility to public (shortcut for S3 public files).
     */
    public function public(): static
    {
        $this->visibility = 'public';
        return $this;
    }

    /**
     * Set the file visibility to private.
     */
    public function private(): static
    {
        $this->visibility = 'private';
        return $this;
    }

    /**
     * Make the file downloadable.
     */
    public function downloadable(bool $downloadable = true): static
    {
        $this->downloadable = $downloadable;
        return $this;
    }

    /**
     * Set custom download route.
     */
    public function downloadRoute(string $route): static
    {
        $this->downloadRoute = $route;
        return $this;
    }

    /**
     * Use temporary URLs for file access (for private files on S3, etc.).
     */
    public function temporaryUrl(int $minutes = 30): static
    {
        $this->useTemporaryUrl = true;
        $this->temporaryUrlMinutes = $minutes;
        return $this;
    }

    /**
     * Disable URL signing and return plain URLs.
     */
    public function withoutSigning(): static
    {
        $this->signUrl = false;
        return $this;
    }

    /**
     * Enable URL signing (default behavior).
     */
    public function withSigning(): static
    {
        $this->signUrl = true;
        return $this;
    }

    /**
     * Indicate that the stored value is already a full URL (not a relative path).
     */
    public function storedAsUrl(bool $stored = true): static
    {
        $this->storedAsUrl = $stored;
        return $this;
    }

    /**
     * Cache the file URL for better performance.
     */
    public function cache(int $minutes = 30, ?string $prefix = null): static
    {
        $this->cacheUrl = true;
        $this->cacheMinutes = $minutes;
        $this->cachePrefix = $prefix;
        return $this;
    }

    /**
     * Use both temporary URL and caching (common for S3 files).
     */
    public function cachedTemporaryUrl(int $cacheMinutes = 30, int $urlMinutes = 30): static
    {
        $this->temporaryUrl($urlMinutes);
        $this->cache($cacheMinutes);
        return $this;
    }

    public function getRules(): array
    {
        $rules = parent::getRules();
//        // Add file validation rule
//        $rules[] = 'file';
//
//        // Add size validation if specified
//        if ($this->maxSize !== null) {
//            $rules[] = 'max:' . ceil($this->maxSize / 1024); // Laravel expects KB
//        }
//
//        // Add MIME type validation if specified
//        if (!empty($this->acceptedTypes)) {
//            $mimeTypes = $this->convertToMimeTypes($this->acceptedTypes);
//            $rules[] = 'mimes:' . implode(',', $mimeTypes);
//        }
//
        return $rules;
    }

    /**
     * Convert file extensions to MIME types.
     */
    protected function convertToMimeTypes(array $types): array
    {
        $mimeMap = [
            'jpg' => 'jpeg',
            'jpeg' => 'jpeg',
            'png' => 'png',
            'gif' => 'gif',
            'pdf' => 'pdf',
            'doc' => 'doc',
            'docx' => 'vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'vnd.ms-excel',
            'xlsx' => 'vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'plain',
            'csv' => 'csv',
            'zip' => 'zip',
        ];

        $mimeTypes = [];
        foreach ($types as $type) {
            if (str_contains($type, '/')) {
                // Already a MIME type
                $mimeTypes[] = explode('/', $type)[1];
            } else {
                // Convert extension to MIME type
                $extension = ltrim($type, '.');
                $mimeTypes[] = $mimeMap[$extension] ?? $extension;
            }
        }

        return array_unique($mimeTypes);
    }

    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $value = parent::resolve($request, $model, $resource);

        if (!$value) {
            return null;
        }

        // Handle array values (from JSON cast)
        if (is_array($value)) {
            // If it already has the expected structure with 'path', return as-is
            if (isset($value['path'])) {
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

        // Return file information
        $data = [
            'path' => $value,
            'name' => basename($value),
            'url' => $this->getFileUrl($value, $model),
            'downloadable' => $this->downloadable,
            'downloadUrl' => $this->getDownloadUrl($value, $model),
//            'size' => $this->getFileSize($value),
//            'mimeType' => $this->getFileMimeType($value),
            'cached' => $this->cacheUrl,
            'temporary' => $this->useTemporaryUrl,
        ];

        return $data;
    }

    /**
     * Get the public URL for the file.
     */
    protected function getFileUrl(string $path, ?Model $model = null): ?string
    {
        if (!$path) {
            return null;
        }

        $disk = $this->disk ?? config('filesystems.default');


        // If caching is enabled
        if ($this->cacheUrl && $model) {
//            $cacheKey = $this->getCacheKey($model, $path);

            return Cache::remember($path, now()->addMinutes($this->cacheMinutes * 60), function () use ($disk, $path) {
                return $this->generateFileUrl($disk, $path);
            });
        }

        // Generate URL without caching
        return $this->generateFileUrl($disk, $path);
    }

    /**
     * Generate the actual file URL (temporary or regular).
     */
    protected function generateFileUrl(string $disk, string $path): ?string
    {
        try {
            // If the stored value is already a full URL, return it directly
            if ($this->storedAsUrl) {
                return $path;
            }

            // Use temporary URL if configured and signing is enabled
            if ($this->signUrl && $this->useTemporaryUrl && Storage::disk($disk)->providesTemporaryUrls()) {
                return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($this->temporaryUrlMinutes));
            }

            // Regular URL
            return Storage::disk($disk)->url($path);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the cache key for this file URL.
     */
    protected function getCacheKey(Model $model, string $path): string
    {
        $prefix = $this->cachePrefix ?? 'file_url';
        $modelKey = class_basename($model) . '_' . $model->getKey();
        $fieldKey = $this->getAttribute();

        return "{$prefix}_{$modelKey}_{$fieldKey}_" . md5($path);
    }

    /**
     * Get the download URL for the file.
     */
    protected function getDownloadUrl(string $path, ?Model $model = null): ?string
    {
        if (!$this->downloadable || !$path) {
            return null;
        }

        if ($this->downloadRoute) {
            return route($this->downloadRoute, ['file' => $path]);
        }

        // Default download URL
        return $this->getFileUrl($path, $model);
    }

    /**
     * Get file size in bytes.
     */
    protected function getFileSize(string $path): ?int
    {
        if (!$path) {
            return null;
        }

        $disk = $this->disk ?? config('filesystems.default');
        
        try {
            return \Storage::disk($disk)->size($path);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get file MIME type.
     */
    protected function getFileMimeType(string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $disk = $this->disk ?? config('filesystems.default');
        
        try {
            return \Storage::disk($disk)->mimeType($path);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'acceptedTypes' => $this->acceptedTypes,
            'maxSize' => $this->maxSize,
            'maxSizeMB' => $this->maxSize ? round($this->maxSize / (1024 * 1024), 2) : null,
            'disk' => $this->disk,
            'path' => $this->path,
            'visibility' => $this->visibility,
            'downloadable' => $this->downloadable,
            'useTemporaryUrl' => $this->useTemporaryUrl,
            'signUrl' => $this->signUrl,
            'storedAsUrl' => $this->storedAsUrl,
            'cacheUrl' => $this->cacheUrl,
        ]);
    }

    /**
     * Handle file upload and store the file path in the model.
     *
     * @param Request $request
     * @param Model $model
     * @return void
     */
    public function fill(Request $request, Model $model): void
    {
        // Don't fill if the field is readonly or disabled
        if ($this->isReadonly() || $this->isDisabled()) {
            return;
        }

        $requestAttribute = $this->getAttribute();

        // Check if a file was uploaded
        if ($request->hasFile($requestAttribute)) {

            // Validate the file is valid
            if ($request->file($requestAttribute)) {
                // Determine storage disk
                $disk = $this->disk ?? config('filesystems.default', 'local');

                // Determine a storage path
                $storagePath = $this->path ?? 'uploads';

                // Build storage options
                $options = ['disk' => $disk];

                // Add visibility if specified (for S3 public/private files)
                if ($this->visibility !== null) {
                    $options['visibility'] = $this->visibility;
                }

                $request->file($requestAttribute)->store($storagePath, $options);

                $relativePath = $storagePath . '/' . $request->file($requestAttribute)->hashName();

                // Store as full URL or relative path based on configuration
                if ($this->storedAsUrl) {
                    $model->{$this->getAttribute()} = Storage::disk($disk)->url($relativePath);
                } else {
                    $model->{$this->getAttribute()} = $relativePath;
                }
            }
        }
    }

    /**
     * Generate a unique filename for the uploaded file.
     *
     * @param UploadedFile $file
     * @return string
     */
    protected function generateFilename(UploadedFile $file): string
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();

        // Get filename without extension
        $pathInfo = pathinfo($originalName);
        $filename = $pathInfo['filename'] ?? 'file';

        // If no extension, try to get from mime type
        if (empty($extension)) {
            $extension = $file->guessExtension() ?: 'bin';
        }

        // Sanitize filename
        $filename = preg_replace('/[^A-Za-z0-9\-_]/', '-', $filename);
        $filename = substr($filename, 0, 100); // Limit length

        // Handle empty filename
        if (empty($filename)) {
            $filename = 'file';
        }

        // Add timestamp for uniqueness
        $timestamp = time();
        $random = substr(md5(uniqid()), 0, 8);

        return "{$filename}-{$timestamp}-{$random}.{$extension}";
    }

    /**
     * Delete the old file from storage when replacing or removing.
     *
     * @param Model $model
     * @return void
     */
    protected function deleteOldFile(Model $model): void
    {
        $oldPath = $model->getOriginal($this->getAttribute());

        if ($oldPath) {
            $disk = $this->disk ?? config('filesystems.default', 'local');

            try {
                Storage::disk($disk)->delete($oldPath);
            } catch (\Exception $e) {
                // Log the error but don't fail the operation
                Log::warning("Failed to delete old file: {$oldPath}", ['error' => $e->getMessage()]);
            }
        }
    }
}