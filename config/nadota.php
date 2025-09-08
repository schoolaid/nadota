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
        ]
    ],
    'api' => [
        'prefix' => 'nadota-api'
    ],
    'frontend' => [
        'prefix' => 'resources'
    ],
];
