<?php

namespace SchoolAid\Nadota\Mcp;

use BackedEnum;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Mcp\Contracts\CurrencyResolver;

/**
 * Turns a model into field => value for the MCP tools, from the model and Nadota's fields and not
 * from the panel's JSON: amounts come out raw (the panel formats them with `displayUsing`) and
 * without the UI metadata Nadota repeats on every row.
 */
class RecordProjector
{
    private const MAX_STRING_LENGTH = 1000;

    public function __construct(
        private readonly FieldTypes $types,
        private readonly CurrencyResolver $currencies,
    ) {
    }

    /**
     * Builds the columns once per query and returns the projection of one row.
     *
     * @param  Collection<int, Field>  $fields  the ones Nadota shows the user in that context (fieldsForIndex / fieldsForShow)
     * @return Closure(Model): array<string, mixed>
     */
    public function rowProjector(ExposedResource $exposed, NadotaRequest $request, Collection $fields): Closure
    {
        $columns = $fields
            ->filter(fn (Field $field): bool => $exposed->lists($field->key()) && $this->types->of($field) !== null)
            ->mapWithKeys(fn (Field $field): array => [$field->key() => $field])
            ->all();

        foreach ($exposed->extraMoneyAttributes($exposed->resource->flattenFields($request)) as $attribute) {
            $columns[$attribute] = null;
        }

        return function (Model $model) use ($exposed, $request, $columns): array {
            $row = ['id' => $model->getKey()];

            foreach ($columns as $key => $field) {
                $row[$key] = $this->value($exposed, $request, $model, (string) $key, $field);
            }

            return $row;
        };
    }

    private function value(ExposedResource $exposed, NadotaRequest $request, Model $model, string $key, ?Field $field): mixed
    {
        if (array_key_exists($key, $exposed->money)) {
            return $this->money($exposed->money[$key], $model, $field?->getAttribute() ?? $key);
        }

        if ($field === null) {
            return null;
        }

        $type = $this->types->of($field);

        if ($type === 'relation') {
            return $this->relation($field, $request, $model, $exposed);
        }

        if ($type === 'string') {
            return $this->string($field->resolve($request, $model, $exposed->resource));
        }

        if ($type === 'time') {
            return $this->string($model->getAttribute($field->getAttribute()));
        }

        $raw = $model->getAttribute($field->getAttribute());

        return match ($type) {
            'number' => is_numeric($raw) ? $raw + 0 : null,
            'boolean' => $raw === null ? null : (bool) $raw,
            'date' => $this->date($raw, withTime: false),
            'datetime' => $this->date($raw, withTime: true),
            'enum' => $this->enum($field, $raw),
            default => null,
        };
    }

    /**
     * @param  string|array{amount: string, currency: string}  $source
     * @return array{amount: float, currency: string|null}|null
     */
    private function money(string|array $source, Model $model, string $attribute): ?array
    {
        if (is_array($source)) {
            $amount = $model->{$source['amount']}();
            $currency = $model->{$source['currency']}();
        } else {
            $amount = $model->getAttribute($attribute);
            $currency = $source === 'default' ? null : $model->getAttribute($source);
        }

        if ($currency instanceof BackedEnum) {
            $currency = $currency->value;
        }

        if (! is_numeric($amount)) {
            return null;
        }

        return [
            'amount' => (float) $amount,
            'currency' => is_string($currency) && $currency !== '' ? $currency : $this->currencies->defaultCurrency(),
        ];
    }

    /**
     * Id and label of a to-one relation. If a scope hides the related record (it is out of the
     * user's reach), the id still comes from the foreign key and the label stays null.
     *
     * @return array{id: mixed, label: ?string}|null
     */
    private function relation(Field $field, NadotaRequest $request, Model $model, ExposedResource $exposed): ?array
    {
        $resolved = $field->resolve($request, $model, $exposed->resource);

        if (is_array($resolved) && (array_key_exists('id', $resolved) || array_key_exists('key', $resolved))) {
            return [
                'id' => $resolved['id'] ?? $resolved['key'],
                'label' => isset($resolved['label']) ? (string) $resolved['label'] : null,
            ];
        }

        if ($field instanceof BelongsTo) {
            $foreignKey = $model->getAttribute($field->resolveForeignKeyFromModel($model::class));

            return $foreignKey === null ? null : ['id' => $foreignKey, 'label' => null];
        }

        return null;
    }

    /**
     * @return array{value: mixed, label: string}|array<int, array{value: mixed, label: string}>|null
     */
    private function enum(Field $field, mixed $raw): ?array
    {
        if ($raw instanceof BackedEnum) {
            $raw = $raw->value;
        }

        if ($raw === null || $raw === '') {
            return null;
        }

        $options = collect($this->types->options($field));
        $option = fn (mixed $value): array => [
            'value' => $value,
            'label' => (string) ($options->first(fn (array $candidate): bool => (string) $candidate['value'] === (string) $value)['label'] ?? $value),
        ];

        return is_array($raw) ? array_map($option, $raw) : $option($raw);
    }

    private function date(mixed $raw, bool $withTime): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $date = $raw instanceof CarbonInterface ? $raw : Carbon::parse((string) $raw);

        return $withTime ? $date->toIso8601String() : $date->toDateString();
    }

    private function string(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if ($value === null) {
            return null;
        }

        $text = is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE);

        return Str::limit($text, self::MAX_STRING_LENGTH, '…');
    }
}
