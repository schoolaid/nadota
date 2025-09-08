<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class File extends Field
{
    protected array $acceptedTypes = [];
    protected ?int $maxSize = null; // in bytes
    protected ?string $disk = null;
    protected ?string $path = null;
    protected bool $downloadable = true;
    protected ?string $downloadRoute = null;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute);
        $this->type(FieldType::FILE);
        $this->component(config('nadota.fields.file.component', 'FieldFile'));
        
        // Set default max size from config or 10MB
        $this->maxSize = config('nadota.fields.file.max_size', 10 * 1024 * 1024);
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

    public function getRules(): array
    {
        $rules = parent::getRules();

        // Add file validation rule
        $rules[] = 'file';

        // Add size validation if specified
        if ($this->maxSize !== null) {
            $rules[] = 'max:' . ceil($this->maxSize / 1024); // Laravel expects KB
        }

        // Add MIME type validation if specified
        if (!empty($this->acceptedTypes)) {
            $mimeTypes = $this->convertToMimeTypes($this->acceptedTypes);
            $rules[] = 'mimes:' . implode(',', $mimeTypes);
        }

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

        // Return file information
        return [
            'path' => $value,
            'name' => basename($value),
            'url' => $this->getFileUrl($value),
            'downloadable' => $this->downloadable,
            'downloadUrl' => $this->getDownloadUrl($value),
            'size' => $this->getFileSize($value),
            'mimeType' => $this->getFileMimeType($value),
        ];
    }

    /**
     * Get the public URL for the file.
     */
    protected function getFileUrl(string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $disk = $this->disk ?? config('filesystems.default');
        
        try {
            return \Storage::disk($disk)->url($path);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the download URL for the file.
     */
    protected function getDownloadUrl(string $path): ?string
    {
        if (!$this->downloadable || !$path) {
            return null;
        }

        if ($this->downloadRoute) {
            return route($this->downloadRoute, ['file' => $path]);
        }

        // Default download URL
        return $this->getFileUrl($path);
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
            'downloadable' => $this->downloadable,
        ]);
    }
}