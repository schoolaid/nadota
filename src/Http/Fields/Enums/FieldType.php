<?php

namespace Said\Nadota\Http\Fields\Enums;

enum FieldType: string
{
    case TEXT = 'text';
    case BELONGS_TO = 'belongsTo';
    case HAS_MANY = 'hasMany';
    case HAS_ONE = 'hasOne';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case NUMBER = 'number';
    case SELECT = 'select';
    case TEXTAREA = 'textarea';
    case HIDDEN = 'hidden';
    case RADIO = 'radio';
    case EMAIL = 'email';
    case URL = 'url';
    case BELONGS_TO_MANY = 'belongsToMany';
    case FILE = 'file';
    case IMAGE = 'image';
}
