<?php

namespace SchoolAid\Nadota\Http\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Query performance debugger to identify N+1 queries and slow operations.
 * 
 * This helper is designed for development/debugging only.
 * Do not enable in production as it adds overhead.
 */
class QueryDebugger
{
    /**
     * Collected queries during profiling.
     */
    protected static array $queries = [];

    /**
     * Start time of profiling session.
     */
    protected static ?float $startTime = null;

    /**
     * Whether query logging is currently active.
     */
    protected static bool $isActive = false;

    /**
     * Start profiling database queries.
     * 
     * @return void
     */
    public static function start(): void
    {
        if (static::$isActive) {
            return;
        }

        static::$queries = [];
        static::$startTime = microtime(true);
        static::$isActive = true;

        DB::enableQueryLog();
    }

    /**
     * Stop profiling and return collected data.
     * 
     * @param bool $logResults Whether to log results to Laravel log
     * @return array Statistics about queries
     */
    public static function stop(bool $logResults = false): array
    {
        if (!static::$isActive) {
            return [];
        }

        $queries = DB::getQueryLog();
        $endTime = microtime(true);
        $totalTime = ($endTime - static::$startTime) * 1000; // Convert to ms

        DB::disableQueryLog();
        static::$isActive = false;

        $stats = static::analyzeQueries($queries, $totalTime);

        if ($logResults) {
            static::logStats($stats);
        }

        return $stats;
    }

    /**
     * Analyze queries and generate statistics.
     * 
     * @param array $queries
     * @param float $totalTime
     * @return array
     */
    protected static function analyzeQueries(array $queries, float $totalTime): array
    {
        $queryCount = count($queries);
        $totalQueryTime = array_sum(array_column($queries, 'time'));
        
        // Group queries by type
        $selects = 0;
        $inserts = 0;
        $updates = 0;
        $deletes = 0;
        
        $slowQueries = [];
        $duplicateQueries = [];
        $queryPatterns = [];

        foreach ($queries as $query) {
            $sql = $query['query'];
            $time = $query['time'];

            // Count query types
            if (stripos($sql, 'select') === 0) {
                $selects++;
            } elseif (stripos($sql, 'insert') === 0) {
                $inserts++;
            } elseif (stripos($sql, 'update') === 0) {
                $updates++;
            } elseif (stripos($sql, 'delete') === 0) {
                $deletes++;
            }

            // Identify slow queries (>100ms)
            if ($time > 100) {
                $slowQueries[] = [
                    'sql' => $sql,
                    'time' => $time,
                    'bindings' => $query['bindings'],
                ];
            }

            // Identify potential duplicates
            $pattern = static::normalizeQuery($sql);
            if (!isset($queryPatterns[$pattern])) {
                $queryPatterns[$pattern] = [
                    'count' => 0,
                    'example' => $sql,
                    'total_time' => 0,
                ];
            }
            $queryPatterns[$pattern]['count']++;
            $queryPatterns[$pattern]['total_time'] += $time;
        }

        // Find duplicates (same query pattern executed multiple times)
        foreach ($queryPatterns as $pattern => $data) {
            if ($data['count'] > 1) {
                $duplicateQueries[] = [
                    'pattern' => $pattern,
                    'count' => $data['count'],
                    'example' => $data['example'],
                    'total_time' => $data['total_time'],
                ];
            }
        }

        // Sort duplicates by count (most frequent first)
        usort($duplicateQueries, fn($a, $b) => $b['count'] <=> $a['count']);

        return [
            'total_queries' => $queryCount,
            'total_time_ms' => round($totalTime, 2),
            'query_time_ms' => round($totalQueryTime, 2),
            'overhead_ms' => round($totalTime - $totalQueryTime, 2),
            'query_types' => [
                'select' => $selects,
                'insert' => $inserts,
                'update' => $updates,
                'delete' => $deletes,
            ],
            'slow_queries' => $slowQueries,
            'duplicate_queries' => array_slice($duplicateQueries, 0, 10), // Top 10
            'n_plus_one_suspects' => static::detectNPlusOne($duplicateQueries),
        ];
    }

    /**
     * Normalize a query to identify patterns.
     * Replaces specific values with placeholders.
     * 
     * @param string $sql
     * @return string
     */
    protected static function normalizeQuery(string $sql): string
    {
        // Remove specific IDs and values to create pattern
        $pattern = preg_replace('/\d+/', '?', $sql);
        $pattern = preg_replace('/\'[^\']*\'/', '?', $pattern);
        $pattern = preg_replace('/\s+/', ' ', $pattern);
        
        return trim($pattern);
    }

    /**
     * Detect potential N+1 query issues.
     * 
     * @param array $duplicates
     * @return array
     */
    protected static function detectNPlusOne(array $duplicates): array
    {
        $suspects = [];

        foreach ($duplicates as $duplicate) {
            // N+1 queries typically involve:
            // - High repetition count (>10)
            // - SELECT queries with WHERE clauses
            // - Similar structure but different values
            if (
                $duplicate['count'] > 10 &&
                stripos($duplicate['example'], 'select') === 0 &&
                stripos($duplicate['example'], 'where') !== false
            ) {
                $suspects[] = [
                    'count' => $duplicate['count'],
                    'example' => $duplicate['example'],
                    'total_time_ms' => round($duplicate['total_time'], 2),
                    'suggestion' => 'Consider using eager loading or batch loading',
                ];
            }
        }

        return $suspects;
    }

    /**
     * Log statistics to Laravel log.
     * 
     * @param array $stats
     * @return void
     */
    protected static function logStats(array $stats): void
    {
        Log::info('Query Performance Report', [
            'total_queries' => $stats['total_queries'],
            'total_time_ms' => $stats['total_time_ms'],
            'query_time_ms' => $stats['query_time_ms'],
            'query_types' => $stats['query_types'],
        ]);

        if (!empty($stats['slow_queries'])) {
            Log::warning('Slow Queries Detected', [
                'count' => count($stats['slow_queries']),
                'queries' => $stats['slow_queries'],
            ]);
        }

        if (!empty($stats['n_plus_one_suspects'])) {
            Log::warning('Potential N+1 Query Issues', [
                'count' => count($stats['n_plus_one_suspects']),
                'suspects' => $stats['n_plus_one_suspects'],
            ]);
        }
    }

    /**
     * Profile a callback and return results along with query stats.
     * 
     * @param callable $callback
     * @param bool $logResults
     * @return array ['result' => mixed, 'stats' => array]
     */
    public static function profile(callable $callback, bool $logResults = false): array
    {
        static::start();
        
        $result = $callback();
        
        $stats = static::stop($logResults);

        return [
            'result' => $result,
            'stats' => $stats,
        ];
    }

    /**
     * Format statistics for display.
     * 
     * @param array $stats
     * @return string
     */
    public static function formatStats(array $stats): string
    {
        $output = "Query Performance Report\n";
        $output .= str_repeat('=', 50) . "\n";
        $output .= "Total Queries: {$stats['total_queries']}\n";
        $output .= "Total Time: {$stats['total_time_ms']}ms\n";
        $output .= "Query Time: {$stats['query_time_ms']}ms\n";
        $output .= "Overhead: {$stats['overhead_ms']}ms\n";
        $output .= "\nQuery Types:\n";
        foreach ($stats['query_types'] as $type => $count) {
            $output .= "  {$type}: {$count}\n";
        }

        if (!empty($stats['slow_queries'])) {
            $output .= "\nSlow Queries (>100ms): " . count($stats['slow_queries']) . "\n";
            foreach (array_slice($stats['slow_queries'], 0, 5) as $i => $query) {
                $output .= "  " . ($i + 1) . ". {$query['time']}ms - " . substr($query['sql'], 0, 100) . "...\n";
            }
        }

        if (!empty($stats['n_plus_one_suspects'])) {
            $output .= "\n⚠ Potential N+1 Issues: " . count($stats['n_plus_one_suspects']) . "\n";
            foreach (array_slice($stats['n_plus_one_suspects'], 0, 3) as $i => $suspect) {
                $output .= "  " . ($i + 1) . ". Executed {$suspect['count']} times ({$suspect['total_time_ms']}ms)\n";
                $output .= "     {$suspect['suggestion']}\n";
                $output .= "     Example: " . substr($suspect['example'], 0, 80) . "...\n";
            }
        }

        return $output;
    }
}
