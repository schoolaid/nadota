<?php

namespace SchoolAid\Nadota\Http\Services\FieldOptions;

use Illuminate\Support\Facades\Cache;

/**
 * Optional caching layer for field options queries.
 * Can be enabled per-field to cache expensive lookups.
 */
class OptionsCache
{
    /**
     * Default TTL for cached options (in seconds).
     * 5 minutes by default - short enough to stay fresh, long enough to help.
     */
    const DEFAULT_TTL = 300;

    /**
     * Cache key prefix to avoid collisions.
     */
    const CACHE_PREFIX = 'nadota:field_options:';

    /**
     * Get cached options or execute callback and cache result.
     *
     * @param string $cacheKey Unique identifier for this options query
     * @param callable $callback Function to execute if cache miss
     * @param int|null $ttl Time to live in seconds (null = no caching)
     * @return mixed
     */
    public static function remember(string $cacheKey, callable $callback, ?int $ttl = self::DEFAULT_TTL)
    {
        // If TTL is null or 0, bypass cache
        if ($ttl === null || $ttl <= 0) {
            return $callback();
        }

        $fullKey = self::CACHE_PREFIX . $cacheKey;

        return Cache::remember($fullKey, $ttl, $callback);
    }

    /**
     * Generate a cache key from query parameters.
     *
     * @param string $resourceClass Resource class name
     * @param string $fieldName Field name
     * @param array $params Query parameters
     * @return string
     */
    public static function generateKey(string $resourceClass, string $fieldName, array $params = []): string
    {
        // Include only relevant params that affect query results
        $relevantParams = [
            'search' => $params['search'] ?? '',
            'limit' => $params['limit'] ?? null,
            'exclude' => $params['exclude'] ?? [],
            'orderBy' => $params['orderBy'] ?? null,
            'orderDirection' => $params['orderDirection'] ?? 'asc',
            'filters' => $params['filters'] ?? [],
        ];

        // Remove empty/null values to normalize key
        $relevantParams = array_filter($relevantParams, fn($value) => 
            $value !== null && $value !== '' && $value !== []
        );

        $keyData = [
            'resource' => class_basename($resourceClass),
            'field' => $fieldName,
            'params' => $relevantParams,
        ];

        // Use SHA-256 for better hash distribution and security practices
        return hash('sha256', json_encode($keyData));
    }

    /**
     * Invalidate cached options for a specific resource/field combination.
     *
     * Note: This is a placeholder for future implementation with cache tags.
     * Current implementation does not support fine-grained invalidation.
     * For now, use Cache::flush() to clear all cache or wait for TTL expiry.
     *
     * @param string $resourceClass Resource class name
     * @param string|null $fieldName Field name (null = all fields for resource)
     * @return void
     * @throws \BadMethodCallException Always throws until proper implementation
     * 
     * @deprecated This method is not yet implemented. Will be available in future version with cache tag support.
     */
    public static function invalidate(string $resourceClass, ?string $fieldName = null): void
    {
        throw new \BadMethodCallException(
            'OptionsCache::invalidate() is not yet implemented. ' .
            'Requires cache driver with tag support (Redis/Memcached). ' .
            'For now, use Cache::flush() or wait for cache TTL expiry.'
        );
        
        // Future implementation with cache tags:
        // if ($fieldName) {
        //     Cache::tags([self::CACHE_PREFIX, $resourceClass, $fieldName])->flush();
        // } else {
        //     Cache::tags([self::CACHE_PREFIX, $resourceClass])->flush();
        // }
    }

    /**
     * Clear all field options cache.
     * 
     * Note: This is a placeholder for future implementation with cache tags.
     * Current implementation does not support scoped flushing.
     * For now, use Cache::flush() to clear all application cache.
     *
     * @return void
     * @throws \BadMethodCallException Always throws until proper implementation
     * 
     * @deprecated This method is not yet implemented. Will be available in future version with cache tag support.
     */
    public static function flush(): void
    {
        throw new \BadMethodCallException(
            'OptionsCache::flush() is not yet implemented. ' .
            'Requires cache driver with tag support (Redis/Memcached). ' .
            'For now, use Cache::flush() to clear all cache.'
        );
        
        // Future implementation with cache tags:
        // Cache::tags([self::CACHE_PREFIX])->flush();
    }
}
