<?php

namespace Said\Nadota\Http\Fields;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Said\Nadota\Contracts\ResourceInterface;
use Said\Nadota\Http\Fields\Enums\FieldType;

class Image extends File
{
    protected ?int $maxWidth = null;
    protected ?int $maxHeight = null;
    protected bool $showPreview = true;
    protected array $thumbnailSizes = [];
    protected ?string $alt = null;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute);
        $this->type(FieldType::IMAGE);
        $this->component(config('nadota.fields.image.component', 'field-image'));
        
        // Set default accepted types to common image formats
        $this->acceptedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        // Set default max size to 5MB for images
        $this->maxSize = config('nadota.fields.image.max_size', 5 * 1024 * 1024);
    }

    /**
     * Set maximum image dimensions.
     */
    public function maxDimensions(int $width, int $height): static
    {
        $this->maxWidth = $width;
        $this->maxHeight = $height;
        return $this;
    }

    /**
     * Set maximum image width.
     */
    public function maxWidth(int $width): static
    {
        $this->maxWidth = $width;
        return $this;
    }

    /**
     * Set maximum image height.
     */
    public function maxHeight(int $height): static
    {
        $this->maxHeight = $height;
        return $this;
    }

    /**
     * Show or hide preview of the image.
     */
    public function preview(bool $show = true): static
    {
        $this->showPreview = $show;
        return $this;
    }

    /**
     * Define thumbnail sizes to generate.
     */
    public function thumbnails(array $sizes): static
    {
        $this->thumbnailSizes = $sizes;
        return $this;
    }

    /**
     * Set alt text attribute for the image.
     */
    public function alt(string $alt): static
    {
        $this->alt = $alt;
        return $this;
    }

    public function getRules(): array
    {
        $rules = parent::getRules();

        // Add image validation rule
        $rules[] = 'image';

        // Add dimension validation if specified
        if ($this->maxWidth !== null || $this->maxHeight !== null) {
            $dimensions = 'dimensions:';
            $constraints = [];

            if ($this->maxWidth !== null) {
                $constraints[] = "max_width={$this->maxWidth}";
            }

            if ($this->maxHeight !== null) {
                $constraints[] = "max_height={$this->maxHeight}";
            }

            $rules[] = $dimensions . implode(',', $constraints);
        }

        return $rules;
    }

    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $fileData = parent::resolve($request, $model, $resource);

        if (!$fileData) {
            return null;
        }

        // Add image-specific information
        $imageData = array_merge($fileData, [
            'isImage' => true,
            'showPreview' => $this->showPreview,
            'alt' => $this->alt ?: $this->fieldData->name,
            'thumbnails' => $this->getThumbnails($fileData['path']),
            'dimensions' => $this->getImageDimensions($fileData['path']),
        ]);

        return $imageData;
    }

    /**
     * Get thumbnail URLs for different sizes.
     */
    protected function getThumbnails(string $path): array
    {
        if (empty($this->thumbnailSizes) || !$path) {
            return [];
        }

        $thumbnails = [];
        $disk = $this->disk ?? config('filesystems.default');

        foreach ($this->thumbnailSizes as $size => $dimensions) {
            $thumbnailPath = $this->generateThumbnailPath($path, $size);
            
            try {
                if (\Storage::disk($disk)->exists($thumbnailPath)) {
                    $thumbnails[$size] = [
                        'path' => $thumbnailPath,
                        'url' => \Storage::disk($disk)->url($thumbnailPath),
                        'width' => $dimensions['width'] ?? null,
                        'height' => $dimensions['height'] ?? null,
                    ];
                }
            } catch (\Exception $e) {
                // Thumbnail doesn't exist or can't be accessed
                continue;
            }
        }

        return $thumbnails;
    }

    /**
     * Generate thumbnail path based on original path and size.
     */
    protected function generateThumbnailPath(string $path, string $size): string
    {
        $pathInfo = pathinfo($path);
        return $pathInfo['dirname'] . '/thumbs/' . $pathInfo['filename'] . '_' . $size . '.' . $pathInfo['extension'];
    }

    /**
     * Get image dimensions.
     */
    protected function getImageDimensions(string $path): ?array
    {
        if (!$path) {
            return null;
        }

        $disk = $this->disk ?? config('filesystems.default');
        
        try {
            $fullPath = \Storage::disk($disk)->path($path);
            
            if (file_exists($fullPath)) {
                $imageSize = getimagesize($fullPath);
                if ($imageSize) {
                    return [
                        'width' => $imageSize[0],
                        'height' => $imageSize[1],
                        'type' => $imageSize[2],
                        'ratio' => round($imageSize[0] / $imageSize[1], 2),
                    ];
                }
            }
        } catch (\Exception $e) {
            // Can't get dimensions
        }

        return null;
    }

    /**
     * Check if the file is a valid image.
     */
    protected function isValidImage(string $path): bool
    {
        $dimensions = $this->getImageDimensions($path);
        return $dimensions !== null;
    }

    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'maxWidth' => $this->maxWidth,
            'maxHeight' => $this->maxHeight,
            'showPreview' => $this->showPreview,
            'thumbnailSizes' => $this->thumbnailSizes,
            'alt' => $this->alt,
            'isImage' => true,
        ]);
    }
}