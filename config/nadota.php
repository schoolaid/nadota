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
        'exclude_fields' => ['password', 'remember_token', 'api_token'],
        'track_fields' => true,
        'track_original' => true,
        'track_changes' => true,
    ],
];
