<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

/**
 * Multiple image upload field.
 *
 * Stores an array of relative paths (JSON column on the model, cast to array).
 * v1 is append-only: new uploads are appended to the existing paths; removing
 * stored images is a future iteration. Validation: `array|max:{maxFiles}` on
 * the attribute plus per-item `image|mimes|max` nested rules.
 */
class MultiImage extends File
{
    protected int $maxFiles = 5;

    public function __construct(string $name, string $attribute)
    {
        Field::__construct(
            $name,
            $attribute,
            FieldType::MULTI_IMAGE->value,
            static::safeConfig('nadota.fields.multi_image.component', 'FieldMultiImage')
        );

        $this->maxSize = static::safeConfig('nadota.fields.multi_image.max_size', 5 * 1024 * 1024);
        $this->acceptedTypes = ['jpg', 'jpeg', 'png', 'webp'];
    }

    public function maxFiles(int $maxFiles): static
    {
        $this->maxFiles = $maxFiles;

        return $this;
    }

    public function getRules(): array
    {
        $rules = array_values(array_filter(
            parent::getRules(),
            fn ($rule) => $rule !== 'string'
        ));

        $isNullable = in_array('nullable', $rules, true);

        // The frontend serializer (useObjectToFormData) submits the literal
        // string '[]' as its empty-array indicator on multipart requests.
        // Mirror KeyValue's convention: accept it as "no images" when the
        // field is nullable, accept real arrays, reject anything else.
        $rules[] = function (string $attribute, mixed $value, \Closure $fail) use ($isNullable): void {
            if ($value === null) {
                return;
            }

            if ($isNullable && in_array($value, ['', '[]'], true)) {
                return;
            }

            if (is_array($value)) {
                return;
            }

            $fail(__('validation.array', ['attribute' => $attribute]));
        };

        return array_merge($rules, ['max:'.$this->maxFiles]);
    }

    /**
     * Per-item rules, picked up by ProcessesFields::buildValidationRules().
     *
     * @return array<string, array<int, string>>
     */
    public function getNestedRules(): array
    {
        return [
            $this->getAttribute().'.*' => [
                'image',
                'mimes:'.implode(',', $this->acceptedTypes),
                'max:'.intdiv($this->maxSize, 1024),
            ],
        ];
    }

    public function fill(Request $request, Model $model): void
    {
        if ($this->isReadonly() || $this->isDisabled()) {
            return;
        }

        $attribute = $this->getAttribute();

        if (! $request->hasFile($attribute)) {
            return;
        }

        $uploads = $request->file($attribute);

        $disk = $this->disk ?? config('filesystems.default', 'local');
        $storagePath = $this->path ?? 'uploads';

        $options = ['disk' => $disk];

        if ($this->visibility !== null) {
            $options['visibility'] = $this->visibility;
        }

        $current = $model->{$attribute};
        $paths = is_array($current) ? array_values($current) : [];

        foreach (is_array($uploads) ? $uploads : [$uploads] as $upload) {
            if (! $upload instanceof UploadedFile) {
                continue;
            }

            $upload->store($storagePath, $options);
            $paths[] = $storagePath.'/'.$upload->hashName();
        }

        $model->{$attribute} = $paths;
    }

    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $paths = $model->{$this->getAttribute()};

        if (! is_array($paths) || $paths === []) {
            return [];
        }

        return array_map(fn (string $path) => [
            'path' => $path,
            'name' => basename($path),
            'url' => $this->getFileUrl($path, $model),
        ], array_values(array_filter($paths, 'is_string')));
    }

    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'maxFiles' => $this->maxFiles,
        ]);
    }
}
