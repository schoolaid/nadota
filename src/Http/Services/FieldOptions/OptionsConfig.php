<?php

namespace SchoolAid\Nadota\Http\Services\FieldOptions;

/**
 * Configuration constants for field options.
 */
class OptionsConfig
{
    /**
     * Default number of options to return.
     */
    public const DEFAULT_LIMIT = 15;

    /**
     * Maximum number of options allowed (prevents expensive queries).
     */
    public const MAX_LIMIT = 100;

    /**
     * Default order direction.
     */
    public const DEFAULT_ORDER_DIRECTION = 'asc';

    /**
     * Default pagination per page.
     */
    public const DEFAULT_PER_PAGE = 15;

    /**
     * Maximum pagination per page allowed.
     */
    public const MAX_PER_PAGE = 100;

    /**
     * Common attributes to search when no searchable attributes are configured.
     */
    public const FALLBACK_SEARCH_ATTRIBUTES = [
        'name',
        'title',
        'label',
        'display_name',
        'full_name',
        'description',
    ];

    /**
     * Common attributes to use for display label fallback.
     */
    public const FALLBACK_LABEL_ATTRIBUTES = [
        'name',
        'title',
        'label',
        'display_name',
        'full_name',
        'description',
    ];
}
