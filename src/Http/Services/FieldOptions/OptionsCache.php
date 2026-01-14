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

        return md5(json_encode($keyData));
    }

    /**
     * Invalidate cached options for a specific resource/field combination.
     *
     * @param string $resourceClass Resource class name
     * @param string|null $fieldName Field name (null = all fields for resource)
     * @return void
     */
    public static function invalidate(string $resourceClass, ?string $fieldName = null): void
    {
        // Note: This is a simple implementation. For production use with many keys,
        // consider using cache tags (Redis/Memcached) or a more sophisticated approach.
        $pattern = self::CACHE_PREFIX;
        
        if ($fieldName) {
            // Specific field - would need cache tag support for efficient invalidation
            // For now, this is a placeholder for future implementation
            // Cache::tags([self::CACHE_PREFIX, $resourceClass, $fieldName])->flush();
        } else {
            // All fields for resource - would need cache tag support
            // Cache::tags([self::CACHE_PREFIX, $resourceClass])->flush();
        }
    }

    /**
     * Clear all field options cache.
     * Use sparingly - mainly for testing or cache maintenance.
     *
     * @return void
     */
    public static function flush(): void
    {
        // Note: This would require cache tag support for efficient implementation
        // For now, this is a placeholder
        // Cache::tags([self::CACHE_PREFIX])->flush();
    }
}
