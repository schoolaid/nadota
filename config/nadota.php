<?php

return [
    'path' => 'said',
    'namespace' => 'said',
    'key_resources_cache' => 'said_nadota_class_file_map',
    'path_resources' => 'app/Nadota',
    'middlewares' => [
        'api',
    ],
    'fields' => [
        // Basic Input Fields
        'text' => [
            'type' => 'text',
            'component' => 'FieldText'
        ],
        'input' => [
            'type' => 'input',
            'component' => 'FieldText'
        ],
        'number' => [
            'type' => 'number',
            'component' => 'FieldNumber'
        ],
        'email' => [
            'type' => 'email',
            'component' => 'FieldEmail'
        ],
        'url' => [
            'type' => 'url',
            'component' => 'FieldUrl'
        ],
        'password' => [
            'type' => 'password',
            'component' => 'FieldPassword'
        ],
        'textarea' => [
            'type' => 'textarea',
            'component' => 'FieldTextarea'
        ],
        'hidden' => [
            'type' => 'hidden',
            'component' => 'FieldHidden'
        ],
        
        // Date and Time Fields
        'datetime' => [
            'type' => 'datetime',
            'component' => 'FieldDateTime'
        ],
        
        // Boolean Fields
        'checkbox' => [
            'type' => 'checkbox',
            'component' => 'FieldCheckbox'
        ],
        'toggle' => [
            'type' => 'toggle',
            'component' => 'FieldToggle'
        ],
        
        // Selection Fields
        'select' => [
            'type' => 'select',
            'component' => 'FieldSelect'
        ],
        'radio' => [
            'type' => 'radio',
            'component' => 'FieldRadio'
        ],
        'checkboxList' => [
            'type' => 'checkboxList',
            'component' => 'FieldCheckboxList'
        ],
        'keyvalue' => [
            'type' => 'keyvalue',
            'component' => 'FieldKeyValue'
        ],
        // File Fields
        'file' => [
            'type' => 'file',
            'component' => 'FieldFile'
        ],
        'image' => [
            'type' => 'image',
            'component' => 'FieldImage'
        ],
        
        // Relationship Fields
        'belongsTo' => [
            'type' => 'belongsTo',
            'component' => 'FieldBelongsTo'
        ],
        'hasOne' => [
            'type' => 'hasOne',
            'component' => 'FieldHasOne'
        ],
        'hasMany' => [
            'type' => 'hasMany',
            'component' => 'FieldHasMany'
        ],
        'belongsToMany' => [
            'type' => 'belongsToMany',
            'component' => 'FieldBelongsToMany'
        ],
        'hasManyThrough' => [
            'type' => 'hasManyThrough',
            'component' => 'FieldHasManyThrough'
        ],
        'morphTo' => [
            'type' => 'morphTo',
            'component' => 'FieldMorphTo'
        ],
        'morphMany' => [
            'type' => 'morphMany',
            'component' => 'FieldMorphMany'
        ],
        'morphOne' => [
            'type' => 'morphOne',
            'component' => 'FieldMorphOne'
        ],
        'morphToMany' => [
            'type' => 'morphToMany',
            'component' => 'FieldMorphToMany'
        ],
        'morphedByMany' => [
            'type' => 'morphedByMany',
            'component' => 'FieldMorphedByMany'
        ],
    ],
    'api' => [
        'prefix' => 'nadota-api'
    ],
    'frontend' => [
        'prefix' => 'resources'
    ],

    // Action Events Tracking
    'track_actions' => env('NADOTA_TRACK_ACTIONS', true),
    'action_events' => [
        'enabled' => env('NADOTA_TRACK_ACTIONS', true),
        'table' => 'action_events',

        // System user ID for actions without authenticated user (registrations, jobs, artisan commands)
        // Set to null to allow null user_id, or set a specific user ID for system actions
        'system_user_id' => env('NADOTA_SYSTEM_USER_ID', null),

        // Fields to exclude from logging (sensitive data)
        'exclude_fields' => [
            'password',
            'remember_token',
            'api_token',
            'token',
            'secret',
            'api_key',
            'private_key',
        ],

        // What data to track
        'track_fields' => true,
        'track_original' => true,
        'track_changes' => true,

        // Dispatch Laravel events (ActionLogged) for external listeners
        'dispatch_events' => env('NADOTA_DISPATCH_ACTION_EVENTS', true),

        // Queue configuration for async logging
        'queue' => env('NADOTA_ACTION_EVENTS_QUEUE', false),
        'queue_name' => env('NADOTA_ACTION_EVENTS_QUEUE_NAME', 'default'),
    ],

    // Export Configuration
    'export' => [
        // Enable/disable export globally
        'enabled' => env('NADOTA_EXPORT_ENABLED', true),

        // Available export formats
        'formats' => ['excel', 'csv'],

        // Default export format
        'default_format' => 'excel',

        // Maximum records for synchronous export
        // Above this threshold, export should be processed async (future feature)
        'sync_limit' => env('NADOTA_EXPORT_SYNC_LIMIT', 1000),

        // Chunk size for processing large datasets
        'chunk_size' => env('NADOTA_EXPORT_CHUNK_SIZE', 500),
    ],
];