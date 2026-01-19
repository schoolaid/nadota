<?php

namespace SchoolAid\Nadota\Http\Fields\Enums;

enum FieldType: string
{
    case TEXT = 'text';
    case CHECKBOX = 'checkbox';
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
    case CHECKBOX_LIST = 'checkbox_list';
    case EMAIL = 'email';
    case URL = 'url';
    case PASSWORD = 'password';
    case BELONGS_TO_MANY = 'belongsToMany';
    case FILE = 'file';
    case IMAGE = 'image';
    case MORPH_TO = 'morphTo';
    case MORPH_MANY = 'morphMany';
    case MORPH_ONE = 'morphOne';
    case JSON = 'json';
    case CODE = 'code';
    case CUSTOM_COMPONENT = 'customComponent';
    case KEY_VALUE = 'keyValue';
    case ARRAY = 'array';
    case HTML = 'html';
    case TIME = 'time';
    case COLOR = 'color';
    case DYNAMIC = 'dynamic';
    case SIGNATURE = 'signature';
    case VARIABLE_TEXT = 'variableText';
    case RICH_TEXT = 'richText';
}
