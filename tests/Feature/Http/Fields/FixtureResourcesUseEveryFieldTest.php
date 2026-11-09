<?php

use SchoolAid\Nadota\Http\Fields\DynamicField;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Section;
use SchoolAid\Nadota\Resource;

/*
 * Coverage guards: a field class added to src/Http/Fields must be wired into a
 * fixture resource (so the HTTP round-trip tests exercise it) and must get its
 * own tests/Unit/Fields/<Class>Test.php.
 */

/** @return array<int, class-string> concrete classes declared directly in src/Http/Fields */
function fieldCatalogueClasses(): array
{
    return collect(glob(dirname(__DIR__, 4) . '/src/Http/Fields/*.php'))
        ->map(fn (string $path) => 'SchoolAid\\Nadota\\Http\\Fields\\' . basename($path, '.php'))
        ->filter(fn (string $class) => class_exists($class) && ! (new ReflectionClass($class))->isAbstract())
        ->sort()
        ->values()
        ->all();
}

/** @return array<int, class-string<Resource>> */
function fieldCatalogueFixtureResources(): array
{
    return collect(glob(dirname(__DIR__, 3) . '/Fixtures/Resources/*.php'))
        ->map(fn (string $path) => 'SchoolAid\\Nadota\\Tests\\Fixtures\\Resources\\' . basename($path, '.php'))
        ->filter(fn (string $class) => class_exists($class) && is_subclass_of($class, Resource::class)
            && ! (new ReflectionClass($class))->isAbstract())
        ->values()
        ->all();
}

/** Classes of every field a resource declares, looking inside Sections and DynamicField types. */
function fieldCatalogueClassesUsedBy(iterable $items): array
{
    $classes = [];

    foreach ($items as $item) {
        $classes[] = $item::class;

        if ($item instanceof Section) {
            $classes = [...$classes, ...fieldCatalogueClassesUsedBy($item->getFields())];
        }

        if ($item instanceof DynamicField) {
            $nested = array_filter([
                ...(fn () => $this->typeMap)->call($item),
                (fn () => $this->defaultField)->call($item),
            ], fn ($candidate) => $candidate instanceof Field);

            $classes = [...$classes, ...fieldCatalogueClassesUsedBy($nested)];
        }
    }

    return $classes;
}

it('finds the field catalogue and the fixture resources', function () {
    expect(count(fieldCatalogueClasses()))->toBeGreaterThanOrEqual(35)
        ->and(fieldCatalogueFixtureResources())->toContain(
            \SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource::class,
            \SchoolAid\Nadota\Tests\Fixtures\Resources\StudentMediaResource::class,
            \SchoolAid\Nadota\Tests\Fixtures\Resources\StudentShowcaseResource::class,
        );
});

it('uses every field class in at least one fixture resource', function () {
    $request = createNadotaRequest();

    $used = collect(fieldCatalogueFixtureResources())
        ->flatMap(fn (string $resource) => fieldCatalogueClassesUsedBy((new $resource())->fields($request)))
        ->unique()
        ->all();

    expect(array_values(array_diff(fieldCatalogueClasses(), $used)))->toBe([]);
});

it('has a unit test file for every field class', function () {
    $missing = collect(fieldCatalogueClasses())
        ->map(fn (string $class) => class_basename($class) . 'Test.php')
        ->reject(fn (string $file) => file_exists(dirname(__DIR__, 3) . '/Unit/Fields/' . $file))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
