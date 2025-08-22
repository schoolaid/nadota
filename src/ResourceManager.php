<?php

namespace SchoolAid\Nadota;

use Exception;
use Illuminate\Support\Facades\Cache;
use SchoolAid\Nadota\Http\Helpers\Helpers;
use Symfony\Component\Finder\Finder;

class ResourceManager
{
    /**
     * @throws Exception
     */
    public static function registerResource($path): static
    {
        $path = base_path($path);

        $resources = [];

        $finder = new Finder();
        $finder->in($path)->files()->name('*Resource.php');

        foreach ($finder as $file) {
            $resourcePath = $file->getPathname();

            $formattedClass = str_replace(['/', '.php'], ['\\', ''], $resourcePath);
            $resourceClass = $formattedClass;
            $base = base_path();
            $base = str_replace('/', '\\', $base);

            $resourceClass = str_replace($base . '\\', '', $resourceClass);

            $resourceClass = ucfirst($resourceClass);

            if (is_subclass_of($resourceClass, Resource::class)) {
                $key = Helpers::toUri($resourceClass);
                if(isset($resources[$key])){
                    throw new Exception("Resource with uri key $key already exists");
                }

                $resource = new $resourceClass();
                if(!isset($resource->model)){
                    throw new Exception("Resource $resourceClass must have a model property");
                }

                $resources[$key] = [
                    'class' => $resourceClass,
                    'model' => $resource->model,
                ];
            }
        }

        Cache::put(config('nadota.key_resources_cache'), collect($resources));
        return new static();
    }

    public static function getResourceByKey($key): string
    {
        $resources = Cache::get(config('nadota.key_resources_cache'));

        return tap(once(function () use ($resources, $key) {
            return $resources[$key]['class'] ?? null;
        }), function ($resource) {
            abort_if(is_null($resource), 404);
        });
    }

    public static function getResources()
    {
        return Cache::get(config('nadota.key_resources_cache'));
    }

    public static function exists($key): bool
    {
        $resources = Cache::get(config('nadota.key_resources_cache'));

        return isset($resources[$key]);
    }

    public static function getModelByResource(string $resource)
    {
        $resources = Cache::get(config('nadota.key_resources_cache'));

        return tap(once(function () use ($resources, $resource) {
            return array_search($resource, $resources);
        }), function ($model) {
            abort_if(is_null($model), 404);
        });
    }
}
