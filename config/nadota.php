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
        'text' => [
            'type' => 'text',
            'component' => 'FieldText'
        ],
        'belongsTo' => [
            'type' => 'belongsTo',
            'component' => 'FieldBelongsTo'
        ]
    ],
    'api' => [
        'prefix' => 'nadota-api'
    ],
    'frontend' => [
        'prefix' => 'resources'
    ],
];
