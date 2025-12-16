# Fields - Nadota

Sistema de campos para la definicion y renderizado de formularios y vistas.

---

## Navegacion Rapida

### Campos Basicos
| [Input](#input) | [Number](#number) | [Email](#email) | [URL](#url) | [Password](#password) | [Textarea](#textarea) | [Hidden](#hidden) |

### Campos de Seleccion
| [Select](#select) | [Radio](#radio) | [Checkbox](#checkbox) | [CheckboxList](#checkboxlist) | [Toggle](#toggle) |

### Campos Especiales
| [DateTime](#datetime) | [Code](#code) | [Json](#json) | [KeyValue](#keyvalue) | [ArrayField](#arrayfield) | [File](#file) | [Image](#image) |

### Campos de Relacion
| [BelongsTo](#belongsto) | [HasOne](#hasone) | [HasMany](#hasmany) | [BelongsToMany](#belongstomany) |
| [MorphTo](#morphto) | [MorphOne](#morphone) | [MorphMany](#morphmany) | [MorphToMany](#morphtomany) |
| [MorphedByMany](#morphedbymany) | [HasManyThrough](#hasmanythrough) | [HasOneThrough](#hasonethrough) |

### Otros
| [Metodos Comunes](#metodos-comunes) | [Respuesta JSON Base](#respuesta-json-base) | [Arquitectura](#arquitectura) | [API de Opciones](#api-de-opciones-de-fields) |

---

## Respuesta JSON Base

Todos los campos comparten esta estructura base en la API:

```json
{
  "key": "field_name",
  "label": "Field Label",
  "attribute": "field_name",
  "type": "text",
  "component": "FieldComponent",
  "value": "valor actual",
  "placeholder": "Placeholder text",
  "helpText": null,
  "readonly": false,
  "disabled": false,
  "required": false,
  "sortable": false,
  "searchable": false,
  "filterable": false,
  "showOnIndex": true,
  "showOnDetail": true,
  "showOnCreation": true,
  "showOnUpdate": true,
  "rules": [],
  "props": {},
  "optionsUrl": null
}
```

---

## Campos Basicos

---

### Input

Campo de texto basico para entrada de datos.

**Clase:** `SchoolAid\Nadota\Http\Fields\Input`
**Tipo:** `text`
**Componente:** `FieldInput`

#### Uso

```php
Input::make('Nombre', 'name')
    ->required()
    ->rules(['min:2', 'max:100'])
    ->placeholder('Ingresa tu nombre')
    ->searchable()
    ->sortable();
```

#### Respuesta API

```json
{
  "key": "name",
  "label": "Nombre",
  "attribute": "name",
  "type": "text",
  "component": "FieldInput",
  "value": "John Doe",
  "placeholder": "Ingresa tu nombre",
  "required": true,
  "searchable": true,
  "sortable": true,
  "rules": ["required", "min:2", "max:100"],
  "props": {}
}
```

#### Metodos

Hereda todos los [metodos comunes](#metodos-comunes).

---

### Number

Campo numerico con restricciones de min/max/step.

**Clase:** `SchoolAid\Nadota\Http\Fields\Number`
**Tipo:** `number`
**Componente:** `FieldNumber`

#### Uso

```php
Number::make('Precio', 'price')
    ->min(0)
    ->max(99999)
    ->step(0.01)
    ->required();

Number::make('Cantidad', 'quantity')
    ->min(1)
    ->step(1);
```

#### Respuesta API

```json
{
  "key": "price",
  "label": "Precio",
  "attribute": "price",
  "type": "number",
  "component": "FieldNumber",
  "value": 150.50,
  "required": true,
  "rules": ["required", "min:0", "max:99999", "numeric"],
  "props": {
    "min": 0,
    "max": 99999,
    "step": 0.01
  }
}
```

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `min(float $value)` | Valor minimo permitido |
| `max(float $value)` | Valor maximo permitido |
| `step(float $value)` | Incremento/decremento |

---

### Email

Campo de email con validacion automatica.

**Clase:** `SchoolAid\Nadota\Http\Fields\Email`
**Tipo:** `email`
**Componente:** `FieldEmail`

#### Uso

```php
Email::make('Correo', 'email')
    ->required()
    ->searchable();
```

#### Respuesta API

```json
{
  "key": "email",
  "label": "Correo",
  "attribute": "email",
  "type": "email",
  "component": "FieldEmail",
  "value": "user@example.com",
  "required": true,
  "rules": ["email", "required"],
  "props": {}
}
```

#### Metodos

Hereda todos los [metodos comunes](#metodos-comunes). Incluye validacion `email` automaticamente.

---

### URL

Campo de URL con validacion automatica.

**Clase:** `SchoolAid\Nadota\Http\Fields\URL`
**Tipo:** `url`
**Componente:** `FieldUrl`

#### Uso

```php
URL::make('Sitio Web', 'website')
    ->nullable();
```

#### Respuesta API

```json
{
  "key": "website",
  "label": "Sitio Web",
  "attribute": "website",
  "type": "url",
  "component": "FieldUrl",
  "value": "https://example.com",
  "rules": ["url", "nullable"],
  "props": {}
}
```

#### Metodos

Hereda todos los [metodos comunes](#metodos-comunes). Incluye validacion `url` automaticamente.

---

### Password

Campo de contrasena con opciones de confirmacion y medidor de fuerza.

**Clase:** `SchoolAid\Nadota\Http\Fields\Password`
**Tipo:** `password`
**Componente:** `FieldPassword`
**Visibilidad por defecto:** Solo en formularios (create/update)

#### Uso

```php
Password::make('Contrasena', 'password')
    ->required()
    ->minLength(8)
    ->confirmable()
    ->showStrengthIndicator();
```

#### Respuesta API

```json
{
  "key": "password",
  "label": "Contrasena",
  "attribute": "password",
  "type": "password",
  "component": "FieldPassword",
  "value": null,
  "showOnIndex": false,
  "showOnDetail": false,
  "showOnCreation": true,
  "showOnUpdate": true,
  "rules": ["required", "min:8", "string", "confirmed"],
  "props": {
    "confirmable": true,
    "minLength": 8,
    "showStrengthIndicator": true
  }
}
```

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `confirmable(bool $value = true)` | Requiere campo de confirmacion |
| `minLength(int $length)` | Longitud minima |
| `showStrengthIndicator(bool $show = true)` | Mostrar indicador de fuerza |

**Nota:** El valor siempre retorna `null` por seguridad.

---

### Textarea

Area de texto multilinea.

**Clase:** `SchoolAid\Nadota\Http\Fields\Textarea`
**Tipo:** `textarea`
**Componente:** `FieldTextarea`

#### Uso

```php
Textarea::make('Descripcion', 'description')
    ->rows(5)
    ->cols(40)
    ->maxHeight(200)
    ->nullable();
```

#### Respuesta API

```json
{
  "key": "description",
  "label": "Descripcion",
  "attribute": "description",
  "type": "textarea",
  "component": "FieldTextarea",
  "value": "Texto largo aqui...",
  "rules": ["nullable"],
  "props": {
    "rows": 5,
    "cols": 40,
    "maxHeight": 200
  }
}
```

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `rows(int $rows)` | Numero de filas visibles |
| `cols(int $cols)` | Numero de columnas |

---

### Hidden

Campo oculto para valores que no se muestran al usuario.

**Clase:** `SchoolAid\Nadota\Http\Fields\Hidden`
**Tipo:** `hidden`
**Componente:** `FieldHidden`
**Visibilidad por defecto:** Oculto en index y detail

#### Uso

```php
Hidden::make('Token', 'token')
    ->default(fn() => Str::random(32));
```

#### Respuesta API

```json
{
  "key": "token",
  "label": "Token",
  "attribute": "token",
  "type": "hidden",
  "component": "FieldHidden",
  "value": "abc123xyz...",
  "showOnIndex": false,
  "showOnDetail": false,
  "props": {}
}
```

---

## Campos de Seleccion

---

### Select

Dropdown con opciones estaticas o dinamicas.

**Clase:** `SchoolAid\Nadota\Http\Fields\Select`
**Tipo:** `select`
**Componente:** `FieldSelect`

#### Uso

```php
// Opciones simples (key => label)
Select::make('Estado', 'status')
    ->options([
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'pending' => 'Pendiente',
    ])
    ->clearable()
    ->required();

// Opciones con formato extendido
Select::make('Pais', 'country_id')
    ->options([
        ['value' => 1, 'label' => 'Mexico'],
        ['value' => 2, 'label' => 'Espana'],
    ]);

// Seleccion multiple
Select::make('Categorias', 'categories')
    ->options([...])
    ->multiple();
```

#### Respuesta API

```json
{
  "key": "status",
  "label": "Estado",
  "attribute": "status",
  "type": "select",
  "component": "FieldSelect",
  "value": "active",
  "required": true,
  "props": {
    "options": [
      {"value": "active", "label": "Activo"},
      {"value": "inactive", "label": "Inactivo"},
      {"value": "pending", "label": "Pendiente"}
    ],
    "multiple": false,
    "clearable": true,
    "placeholder": null
  }
}
```

**Con multiple:**
```json
{
  "value": ["cat1", "cat2", "cat3"],
  "props": {
    "multiple": true
  }
}
```

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `options(array $options)` | Define las opciones disponibles |
| `multiple(bool $value = true)` | Permite seleccion multiple |
| `clearable(bool $value = true)` | Permite limpiar la seleccion |
| `placeholder(string $text)` | Texto placeholder |

---

### Radio

Botones de radio para seleccion unica.

**Clase:** `SchoolAid\Nadota\Http\Fields\Radio`
**Tipo:** `radio`
**Componente:** `FieldRadio`

#### Uso

```php
Radio::make('Genero', 'gender')
    ->options([
        'male' => 'Masculino',
        'female' => 'Femenino',
        'other' => 'Otro',
    ])
    ->inline();
```

#### Respuesta API

```json
{
  "key": "gender",
  "label": "Genero",
  "attribute": "gender",
  "type": "radio",
  "component": "FieldRadio",
  "value": "male",
  "props": {
    "options": [
      {"value": "male", "label": "Masculino"},
      {"value": "female", "label": "Femenino"},
      {"value": "other", "label": "Otro"}
    ],
    "inline": true
  }
}
```

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `options(array $options)` | Define las opciones |
| `inline(bool $inline = true)` | Mostrar en linea horizontal |

---

### Checkbox

Checkbox simple para valores booleanos.

**Clase:** `SchoolAid\Nadota\Http\Fields\Checkbox`
**Tipo:** `checkbox`
**Componente:** `FieldCheckbox`

#### Uso

```php
Checkbox::make('Acepto terminos', 'accepted_terms')
    ->trueValue('yes')
    ->falseValue('no')
    ->required();
```

#### Respuesta API

```json
{
  "key": "accepted_terms",
  "label": "Acepto terminos",
  "attribute": "accepted_terms",
  "type": "checkbox",
  "component": "FieldCheckbox",
  "value": true,
  "required": true,
  "props": {
    "trueValue": "yes",
    "falseValue": "no"
  }
}
```

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `trueValue(mixed $value)` | Valor cuando esta marcado (default: 1) |
| `falseValue(mixed $value)` | Valor cuando no esta marcado (default: 0) |

---

### CheckboxList

Lista de checkboxes para seleccion multiple.

**Clase:** `SchoolAid\Nadota\Http\Fields\CheckboxList`
**Tipo:** `checkboxList`
**Componente:** `FieldCheckboxList`

#### Uso

```php
CheckboxList::make('Permisos', 'permissions')
    ->options([
        'read' => 'Leer',
        'write' => 'Escribir',
        'delete' => 'Eliminar',
        'admin' => 'Administrar',
    ])
    ->min(1)
    ->max(3)
    ->inline();
```

#### Respuesta API

```json
{
  "key": "permissions",
  "label": "Permisos",
  "attribute": "permissions",
  "type": "checkboxList",
  "component": "FieldCheckboxList",
  "value": ["read", "write"],
  "rules": ["array", "min:1", "max:3"],
  "props": {
    "options": [
      {"value": "read", "label": "Leer"},
      {"value": "write", "label": "Escribir"},
      {"value": "delete", "label": "Eliminar"},
      {"value": "admin", "label": "Administrar"}
    ],
    "minSelections": 1,
    "maxSelections": 3,
    "inline": true
  }
}
```

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `options(array $options)` | Define las opciones |
| `min(int $min)` | Minimo de selecciones requeridas |
| `max(int $max)` | Maximo de selecciones permitidas |
| `limit(int $max)` | Alias de max() |
| `inline(bool $inline = true)` | Mostrar en linea |

---

### Toggle

Switch on/off con etiquetas personalizables.

**Clase:** `SchoolAid\Nadota\Http\Fields\Toggle`
**Tipo:** `boolean`
**Componente:** `FieldToggle`

#### Uso

```php
Toggle::make('Activo', 'is_active')
    ->trueLabel('Habilitado')
    ->falseLabel('Deshabilitado')
    ->trueValue(1)
    ->falseValue(0)
    ->default(true);
```

#### Respuesta API

```json
{
  "key": "is_active",
  "label": "Activo",
  "attribute": "is_active",
  "type": "boolean",
  "component": "FieldToggle",
  "value": true,
  "props": {
    "trueLabel": "Habilitado",
    "falseLabel": "Deshabilitado",
    "trueValue": 1,
    "falseValue": 0
  }
}
```

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `trueLabel(string $label)` | Etiqueta cuando esta activo |
| `falseLabel(string $label)` | Etiqueta cuando esta inactivo |
| `trueValue(mixed $value)` | Valor para true (default: 1) |
| `falseValue(mixed $value)` | Valor para false (default: 0) |

---

## Campos Especiales

---

### DateTime

Campo de fecha y/o hora con formato configurable.

**Clase:** `SchoolAid\Nadota\Http\Fields\DateTime`
**Tipo:** `datetime`
**Componente:** `FieldDateTime`

#### Uso

```php
// Fecha y hora completa
DateTime::make('Fecha de Evento', 'event_at')
    ->format('Y-m-d H:i:s')
    ->min(new DateTime('2024-01-01'))
    ->max(new DateTime('2025-12-31'));

// Solo fecha
DateTime::make('Fecha de Nacimiento', 'birth_date')
    ->dateOnly();

// Solo hora
DateTime::make('Hora de Apertura', 'opening_time')
    ->timeOnly();
```

#### Respuesta API

```json
{
  "key": "event_at",
  "label": "Fecha de Evento",
  "attribute": "event_at",
  "type": "datetime",
  "component": "FieldDateTime",
  "value": "2024-06-15 14:30:00",
  "props": {
    "format": "Y-m-d H:i:s",
    "min": "2024-01-01",
    "max": "2025-12-31",
    "dateOnly": false,
    "timeOnly": false
  }
}
```

**Solo fecha:**
```json
{
  "value": "1990-05-15",
  "props": {
    "format": "Y-m-d",
    "dateOnly": true,
    "timeOnly": false
  }
}
```

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `format(string $format)` | Formato de fecha/hora (PHP) |
| `dateOnly()` | Solo fecha (Y-m-d) |
| `timeOnly()` | Solo hora (H:i:s) |
| `min(DateTimeInterface $date)` | Fecha/hora minima |
| `max(DateTimeInterface $date)` | Fecha/hora maxima |

---

### Code

Editor de codigo con syntax highlighting.

**Clase:** `SchoolAid\Nadota\Http\Fields\Code`
**Tipo:** `code`
**Componente:** `FieldCode`
**Visibilidad por defecto:** Oculto en index

#### Uso

```php
Code::make('Codigo', 'source_code')
    ->language('php')
    ->theme('dark')
    ->showLineNumbers()
    ->editable()
    ->wordWrap();

// Atajos de lenguaje
Code::make('Script', 'script')->javascript();
Code::make('Query', 'query')->sql();
Code::make('Estilos', 'styles')->css();
```

#### Respuesta API

```json
{
  "key": "source_code",
  "label": "Codigo",
  "attribute": "source_code",
  "type": "code",
  "component": "FieldCode",
  "value": "<?php\necho 'Hello World';",
  "showOnIndex": false,
  "props": {
    "language": "php",
    "theme": "dark",
    "showLineNumbers": true,
    "editable": true,
    "syntaxHighlighting": true,
    "wordWrap": true
  }
}
```

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `language(string $lang)` | Lenguaje de programacion |
| `php()`, `javascript()`, `python()`, `html()`, `css()`, `sql()`, `json()`, `yaml()`, `xml()`, `markdown()`, `shell()` | Atajos de lenguaje |
| `theme(string $theme)` | Tema: 'light' o 'dark' |
| `showLineNumbers(bool $show = true)` | Mostrar numeros de linea |
| `editable(bool $editable = true)` | Permitir edicion |
| `syntaxHighlighting(bool $enabled = true)` | Resaltado de sintaxis |
| `wordWrap(bool $wrap = true)` | Ajuste de linea |

---

### Json

Editor de datos JSON con formato y validacion.

**Clase:** `SchoolAid\Nadota\Http\Fields\Json`
**Tipo:** `json`
**Componente:** `FieldJson`
**Visibilidad por defecto:** Oculto en index

#### Uso

```php
Json::make('Configuracion', 'settings')
    ->prettyPrint()
    ->indentSize(4)
    ->showLineNumbers()
    ->editable();
```

#### Respuesta API

```json
{
  "key": "settings",
  "label": "Configuracion",
  "attribute": "settings",
  "type": "json",
  "component": "FieldJson",
  "value": {
    "theme": "dark",
    "notifications": true,
    "language": "es"
  },
  "showOnIndex": false,
  "props": {
    "prettyPrint": true,
    "editable": true,
    "indentSize": 4,
    "showLineNumbers": true
  }
}
```

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `prettyPrint(bool $pretty = true)` | Formato legible |
| `editable(bool $editable = true)` | Permitir edicion |
| `indentSize(int $size)` | Tamano de indentacion |
| `showLineNumbers(bool $show = true)` | Numeros de linea |

---

### KeyValue

Pares clave-valor con esquema definido.

**Clase:** `SchoolAid\Nadota\Http\Fields\KeyValue`
**Tipo:** `keyValue`
**Componente:** `FieldKeyValue`
**Visibilidad por defecto:** Oculto en index

#### Uso

```php
KeyValue::make('Metadata', 'metadata')
    ->schema([
        'title' => 'Titulo',
        'description' => [
            'label' => 'Descripcion',
            'type' => 'textarea',
        ],
        'is_featured' => [
            'label' => 'Destacado',
            'type' => 'toggle',
            'default' => false,
        ],
        'price' => [
            'label' => 'Precio',
            'type' => 'number',
            'rules' => ['numeric', 'min:0'],
        ],
    ])
    ->allowNewKeys()
    ->asTable();
```

#### Respuesta API

```json
{
  "key": "metadata",
  "label": "Metadata",
  "attribute": "metadata",
  "type": "keyValue",
  "component": "FieldKeyValue",
  "value": {
    "title": "Mi Producto",
    "description": "Una descripcion larga...",
    "is_featured": true,
    "price": 99.99
  },
  "showOnIndex": false,
  "rules": ["array"],
  "props": {
    "schema": {
      "title": "Titulo",
      "description": {"label": "Descripcion", "type": "textarea"},
      "is_featured": {"label": "Destacado", "type": "toggle", "default": false},
      "price": {"label": "Precio", "type": "number", "rules": ["numeric", "min:0"]}
    },
    "allowNewKeys": true,
    "showLabels": true,
    "labels": {"title": "Titulo", "description": "Descripcion", "is_featured": "Destacado", "price": "Precio"},
    "readonlyKeys": [],
    "hiddenKeys": [],
    "inputTypes": {"description": "textarea", "is_featured": "toggle", "price": "number"},
    "keyOptions": {},
    "defaults": {"is_featured": false},
    "groups": {},
    "asTable": true
  }
}
```

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `schema(array $schema)` | Define la estructura de campos |
| `allowNewKeys(bool $allow = true)` | Permitir agregar nuevas claves |
| `keyLabels(array $labels)` | Etiquetas para las claves |
| `keyLabel(string $key, string $label)` | Etiqueta individual |
| `readonlyKeys(array\|string $keys)` | Claves de solo lectura |
| `hideKeys(array\|string $keys)` | Claves ocultas |
| `keyRules(string $key, array\|string $rules)` | Reglas por clave |
| `inputType(string $key, string $type, ?array $options)` | Tipo de input |
| `group(string $name, array $keys)` | Agrupar claves |
| `asTable(bool $asTable = true)` | Mostrar como tabla |
| `defaults(array $defaults)` | Valores por defecto |
| `useKeys()` | Usar claves como etiquetas |

---

### ArrayField

Lista de valores simples con tipo configurable.

**Clase:** `SchoolAid\Nadota\Http\Fields\ArrayField`
**Tipo:** `array`
**Componente:** `FieldArray`
**Visibilidad por defecto:** Oculto en index

#### Uso

```php
// Array de strings
ArrayField::make('Tags', 'tags')
    ->strings()
    ->unique()
    ->min(1)
    ->max(10)
    ->displayAsChips();

// Array de emails
ArrayField::make('Correos Alternativos', 'alt_emails')
    ->emails()
    ->unique();

// Con opciones predefinidas
ArrayField::make('Colores', 'colors')
    ->options([
        'red' => 'Rojo',
        'blue' => 'Azul',
        'green' => 'Verde',
    ])
    ->max(3);
```

#### Respuesta API

```json
{
  "key": "tags",
  "label": "Tags",
  "attribute": "tags",
  "type": "array",
  "component": "FieldArray",
  "value": ["laravel", "php", "vue"],
  "showOnIndex": false,
  "rules": ["array", "min:1", "max:10", "distinct"],
  "props": {
    "valueType": "string",
    "allowDuplicates": false,
    "minItems": 1,
    "maxItems": 10,
    "sortable": true,
    "itemPlaceholder": "Enter value...",
    "defaultValues": [],
    "itemRules": ["string"],
    "options": null,
    "displayAsChips": true,
    "addButtonText": "Add Item"
  }
}
```

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `valueType(string $type)` | Tipo de valores |
| `strings()`, `numbers()`, `integers()`, `emails()`, `urls()` | Atajos de tipo |
| `unique()` | No permitir duplicados |
| `allowDuplicates(bool $allow)` | Permitir duplicados |
| `min(int $min)` | Minimo de elementos |
| `max(int $max)` | Maximo de elementos |
| `length(int $length)` | Cantidad fija |
| `sortable(bool $sortable = true)` | Permitir reordenar |
| `itemPlaceholder(string $text)` | Placeholder para items |
| `itemRules(array\|string $rules)` | Reglas por item |
| `options(array $options)` | Opciones predefinidas |
| `displayAsChips(bool $display = true)` | Mostrar como chips |
| `addButtonText(string $text)` | Texto del boton agregar |
| `defaultValues(array $values)` | Valores por defecto |

---

### File

Campo de carga de archivos con soporte para storage.

**Clase:** `SchoolAid\Nadota\Http\Fields\File`
**Tipo:** `file`
**Componente:** `FieldFile`

#### Uso

```php
// Archivo publico
File::make('Documento', 'document_path')
    ->disk('public')
    ->path('documents')
    ->accept(['.pdf', '.doc', '.docx'])
    ->maxSizeMB(10)
    ->downloadable();

// Archivo privado con URL temporal (S3)
File::make('Contrato', 'contract_path')
    ->disk('s3')
    ->path('contracts')
    ->temporaryUrl(30)
    ->cache(25);
```

#### Respuesta API

```json
{
  "key": "document_path",
  "label": "Documento",
  "attribute": "document_path",
  "type": "file",
  "component": "FieldFile",
  "value": {
    "path": "documents/contract-123456.pdf",
    "name": "contract-123456.pdf",
    "url": "https://storage.example.com/documents/contract-123456.pdf",
    "downloadable": true,
    "downloadUrl": "https://storage.example.com/documents/contract-123456.pdf",
    "size": 1048576,
    "mimeType": "application/pdf",
    "cached": false,
    "temporary": false
  },
  "props": {
    "acceptedTypes": [".pdf", ".doc", ".docx"],
    "maxSize": 10485760,
    "maxSizeMB": 10,
    "disk": "public",
    "path": "documents",
    "downloadable": true,
    "useTemporaryUrl": false,
    "cacheUrl": false
  }
}
```

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `disk(string $disk)` | Disco de storage |
| `path(string $path)` | Ruta de almacenamiento |
| `accept(array $types)` | Tipos aceptados (extensiones o MIME) |
| `maxSize(int $bytes)` | Tamano maximo en bytes |
| `maxSizeMB(int $megabytes)` | Tamano maximo en MB |
| `downloadable(bool $downloadable = true)` | Permitir descarga |
| `downloadRoute(string $route)` | Ruta personalizada de descarga |
| `temporaryUrl(int $minutes = 30)` | Usar URLs temporales |
| `cache(int $minutes = 30, ?string $prefix)` | Cachear URLs |
| `cachedTemporaryUrl(int $cacheMin, int $urlMin)` | URL temporal + cache |

---

### Image

Campo de carga de imagenes con previsualizacion.

**Clase:** `SchoolAid\Nadota\Http\Fields\Image`
**Tipo:** `image`
**Componente:** `FieldImage`
**Extiende:** `File`

#### Uso

```php
Image::make('Avatar', 'avatar_path')
    ->disk('public')
    ->path('avatars')
    ->maxSizeMB(2)
    ->maxImageDimensions(800, 800)
    ->showPreview()
    ->rounded()
    ->previewSize('150px');
```

#### Respuesta API

```json
{
  "key": "avatar_path",
  "label": "Avatar",
  "attribute": "avatar_path",
  "type": "image",
  "component": "FieldImage",
  "value": {
    "path": "avatars/user-123.jpg",
    "name": "user-123.jpg",
    "url": "https://storage.example.com/avatars/user-123.jpg",
    "downloadable": true,
    "downloadUrl": "https://storage.example.com/avatars/user-123.jpg",
    "size": 524288,
    "mimeType": "image/jpeg",
    "cached": false,
    "temporary": false
  },
  "props": {
    "acceptedTypes": ["jpg", "jpeg", "png", "gif", "webp"],
    "maxSize": 2097152,
    "maxSizeMB": 2,
    "disk": "public",
    "path": "avatars",
    "maxImageWidth": 800,
    "maxImageHeight": 800,
    "showPreview": true,
    "rounded": true,
    "square": false,
    "previewSize": "150px",
    "lazy": true,
    "showOnIndexPreview": true
  }
}
```

#### Metodos Especificos (adicionales a File)

| Metodo | Descripcion |
|--------|-------------|
| `maxImageDimensions(int $width, int $height)` | Dimensiones maximas |
| `maxImageWidth(int $width)` | Ancho maximo |
| `maxImageHeight(int $height)` | Alto maximo |
| `showPreview(bool $show = true)` | Mostrar previsualizacion |
| `rounded(bool $rounded = true)` | Imagen redondeada |
| `square(bool $square = true)` | Imagen cuadrada |
| `previewSize(string $size)` | Tamano de preview |
| `lazy(bool $lazy = true)` | Carga diferida |
| `showOnIndexPreview(bool $show = true)` | Preview en listado |

---

## Campos de Relacion

Los campos de relacion mapean relaciones de Eloquent.

---

### BelongsTo

Relacion inversa de HasMany (muchos a uno).

**Clase:** `SchoolAid\Nadota\Http\Fields\Relations\BelongsTo`
**Tipo:** `belongsTo`
**Componente:** `FieldBelongsTo`

#### Uso

```php
BelongsTo::make('Usuario', 'user', UserResource::class)
    ->displayAttribute('name')
    ->searchable()
    ->withFields();  // Incluir fields del resource relacionado
```

#### Respuesta API

**Sin withFields (default):**
```json
{
  "key": "user_id",
  "label": "Usuario",
  "type": "belongsTo",
  "value": {
    "key": 1,
    "label": "John Doe",
    "resource": "users"
  },
  "props": {
    "relationType": "belongsTo",
    "resource": "users",
    "displayAttribute": "name"
  }
}
```

**Con withFields:**
```json
{
  "value": {
    "key": 1,
    "label": "John Doe",
    "resource": "users",
    "fields": [
      {"key": "name", "value": "John Doe", "type": "text"},
      {"key": "email", "value": "john@example.com", "type": "email"}
    ]
  }
}
```

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `displayAttribute(string $attr)` | Atributo para mostrar |
| `withFields(bool $value = true)` | Incluir fields en respuesta |
| `fields(array $fields)` | Fields personalizados |
| `exceptFields(array $keys)` | Excluir fields especificos |

---

### HasOne

Relacion uno a uno.

**Clase:** `SchoolAid\Nadota\Http\Fields\Relations\HasOne`
**Tipo:** `hasOne`
**Componente:** `FieldHasOne`
**Visibilidad por defecto:** Solo en detail

#### Uso

```php
HasOne::make('Perfil', 'profile', ProfileResource::class)
    ->displayAttribute('full_name')
    ->withFields();
```

#### Respuesta API

```json
{
  "key": "profile",
  "label": "Perfil",
  "type": "hasOne",
  "showOnIndex": false,
  "showOnDetail": true,
  "value": {
    "key": 1,
    "label": "John's Profile",
    "resource": "profiles",
    "fields": [...]
  },
  "props": {
    "relationType": "hasOne",
    "resource": "profiles"
  }
}
```

---

### HasMany

Relacion uno a muchos.

**Clase:** `SchoolAid\Nadota\Http\Fields\Relations\HasMany`
**Tipo:** `hasMany`
**Componente:** `FieldHasMany`
**Visibilidad por defecto:** Solo en detail

#### Uso

```php
HasMany::make('Comentarios', 'comments', CommentResource::class)
    ->limit(10)
    ->orderBy('created_at', 'desc')
    ->paginated()
    ->fields([...])
    ->exceptFields(['user']);
```

#### Respuesta API

```json
{
  "key": "comments",
  "label": "Comentarios",
  "type": "hasMany",
  "showOnIndex": false,
  "showOnDetail": true,
  "value": {
    "data": [
      {
        "key": 1,
        "label": "Primer comentario",
        "resource": "comments",
        "fields": [...]
      },
      {
        "key": 2,
        "label": "Segundo comentario",
        "resource": "comments",
        "fields": [...]
      }
    ],
    "meta": {
      "total": 25,
      "hasMore": true,
      "resource": "comments"
    }
  },
  "props": {
    "limit": 10,
    "paginated": true,
    "orderBy": "created_at",
    "orderDirection": "desc",
    "relationType": "hasMany",
    "resource": "comments"
  }
}
```

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `limit(int $limit)` | Limite de items |
| `orderBy(string $field, string $direction = 'desc')` | Ordenamiento |
| `paginated(bool $paginated = true)` | Habilitar paginacion |
| `fields(array $fields)` | Fields personalizados |
| `exceptFields(array $keys)` | Excluir fields |
| `displayAttribute(string $attr)` | Atributo para label |

---

### BelongsToMany

Relacion muchos a muchos con soporte para pivot.

**Clase:** `SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany`
**Tipo:** `belongsToMany`
**Componente:** `FieldBelongsToMany`
**Visibilidad por defecto:** Detail, Create, Update

#### Uso

```php
BelongsToMany::make('Roles', 'roles', RoleResource::class)
    ->withPivot(['expires_at', 'is_admin'])
    ->pivotFields([
        DateTime::make('Expira', 'expires_at'),
        Toggle::make('Admin', 'is_admin'),
    ])
    ->withTimestamps()
    ->limit(20)
    ->orderBy('name', 'asc')
    ->paginated();  // Opcional: habilita paginacion
```

#### Respuesta API

```json
{
  "key": "roles",
  "label": "Roles",
  "type": "belongsToMany",
  "showOnIndex": false,
  "showOnDetail": true,
  "showOnCreation": true,
  "showOnUpdate": true,
  "value": {
    "data": [
      {
        "key": 1,
        "label": "Admin",
        "resource": "roles",
        "fields": [...],
        "pivot": {
          "expires_at": "2025-12-31",
          "is_admin": true,
          "created_at": "2024-01-15T10:00:00Z",
          "updated_at": "2024-01-15T10:00:00Z"
        }
      }
    ],
    "meta": {
      "total": 3,
      "hasMore": false,
      "resource": "roles",
      "pivotColumns": ["expires_at", "is_admin"]
    }
  },
  "pivotFields": [...],
  "props": {
    "limit": 20,
    "paginated": false,
    "orderBy": "name",
    "orderDirection": "asc",
    "relationType": "belongsToMany",
    "resource": "roles",
    "pivotColumns": ["expires_at", "is_admin"],
    "withPivot": true,
    "withTimestamps": true,
    "urls": {
      "options": "/nadota-api/posts/resource/field/roles/options?resourceId=1",
      "attach": "/nadota-api/posts/resource/1/attach/roles",
      "detach": "/nadota-api/posts/resource/1/detach/roles",
      "sync": "/nadota-api/posts/resource/1/sync/roles"
    }
  }
}
```

#### URLs de Operaciones

El campo incluye URLs para operaciones de relacion:

| URL | Descripcion |
|-----|-------------|
| `urls.options` | Obtener opciones disponibles (incluye `?resourceId=X` para auto-excluir registros ya relacionados) |
| `urls.attach` | Adjuntar registros a la relacion |
| `urls.detach` | Separar registros de la relacion |
| `urls.sync` | Sincronizar la relacion (reemplazar todos) |
| `urls.paginationUrl` | URL de paginacion (solo si `paginated()` esta habilitado) |

#### Auto-Exclusion de Registros Relacionados

La URL de `options` incluye automaticamente el parametro `resourceId` que permite al backend excluir automaticamente los registros que ya estan relacionados. Por ejemplo, si un Post ya tiene asociados los roles con IDs 1 y 2, al solicitar opciones estos no apareceran en los resultados.

**Parametros soportados en la URL de options:**

| Parametro | Descripcion |
|-----------|-------------|
| `resourceId` | ID del modelo padre (auto-excluye registros relacionados) |
| `search` | Termino de busqueda |
| `limit` | Limite de resultados (default: 15) |
| `exclude` | IDs adicionales a excluir (comma-separated) |
| `orderBy` | Campo para ordenar |
| `orderDirection` | Direccion del orden (asc/desc) |

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `withPivot(array $columns)` | Columnas pivot a incluir |
| `pivotFields(array $fields)` | Fields para datos pivot |
| `withTimestamps(bool $value = true)` | Incluir timestamps del pivot |
| `limit(int $limit)` | Limite de items |
| `orderBy(string $field, string $direction)` | Ordenamiento |
| `paginated(bool $paginated = true)` | Paginacion |
| `withFields(bool $value = true)` | Incluir fields en respuesta |
| `fields(array $fields)` | Fields personalizados |
| `exceptFields(array $keys)` | Excluir fields especificos |

---

### MorphTo

Relacion polimorfica inversa. Permite que un modelo pertenezca a diferentes tipos de modelos.

**Clase:** `SchoolAid\Nadota\Http\Fields\Relations\MorphTo`
**Tipo:** `morphTo`
**Componente:** `FieldMorphTo`

#### Uso

```php
// Usando array de resources (recomendado)
MorphTo::make('Comentable', 'commentable', [
    'post' => PostResource::class,
    'video' => VideoResource::class,
])
    ->withFields()
    ->displayAttribute('title');

// Usando metodos separados
MorphTo::make('Taggable', 'taggable')
    ->resources([
        'post' => PostResource::class,
        'product' => ProductResource::class,
    ])
    ->displayAttribute('name');

// Agregando tipos individualmente
MorphTo::make('Attachable', 'attachable')
    ->addMorphType('document', Document::class, DocumentResource::class)
    ->addMorphType('image', Image::class, ImageResource::class);
```

#### Respuesta API

```json
{
  "key": "commentable_id",
  "label": "Comentable",
  "attribute": "commentable_id",
  "type": "morphTo",
  "value": {
    "id": 5,
    "label": "Mi Post",
    "resource": "posts",
    "type": "post",
    "typeLabel": "Post",
    "optionsUrl": "/nadota-api/comments/resource/field/commentable/morph-options/post",
    "fields": [...]
  },
  "props": {
    "morphTypes": [
      {
        "value": "post",
        "label": "Post",
        "resource": "posts",
        "optionsUrl": "/nadota-api/comments/resource/field/commentable/morph-options/post"
      },
      {
        "value": "video",
        "label": "Video",
        "resource": "videos",
        "optionsUrl": "/nadota-api/comments/resource/field/commentable/morph-options/video"
      }
    ],
    "morphTypeAttribute": "commentable_type",
    "morphIdAttribute": "commentable_id",
    "isPolymorphic": true,
    "baseOptionsUrl": "/nadota-api/comments/resource/field/commentable/morph-options"
  }
}
```

#### Estructura de morphTypes

Cada tipo morph incluye:

| Propiedad | Descripcion |
|-----------|-------------|
| `value` | Alias del tipo (usado internamente) |
| `label` | Etiqueta legible para mostrar |
| `resource` | Key del resource relacionado |
| `optionsUrl` | URL especifica para obtener opciones de este tipo |

#### URLs de Options por Tipo

Cada tipo morph tiene su propia URL de options. El frontend debe:

1. Mostrar un selector de tipo (usando `morphTypes`)
2. Al seleccionar un tipo, cargar opciones desde su `optionsUrl`
3. Guardar tanto el tipo (`morphTypeAttribute`) como el ID (`morphIdAttribute`)

**Ejemplo de flujo:**
```javascript
// 1. Usuario selecciona tipo "post"
const selectedType = morphTypes.find(t => t.value === 'post');

// 2. Cargar opciones desde la URL del tipo
const options = await fetch(selectedType.optionsUrl + '?search=termino');

// 3. Al guardar, enviar ambos campos
{
  "commentable_type": "post",  // o "App\\Models\\Post" segun configuracion
  "commentable_id": 123
}
```

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `resources(array $resources)` | Definir tipos como ['alias' => ResourceClass] |
| `models(array $models)` | Definir tipos como ['alias' => ModelClass] |
| `addMorphType(string $alias, string $model, ?string $resource)` | Agregar tipo individual |
| `withFields(bool $value = true)` | Incluir fields en respuesta |
| `displayAttribute(string $attr)` | Atributo para label |
| `fields(array $fields)` | Fields personalizados |

---

### MorphOne

Relacion polimorfica uno a uno.

**Clase:** `SchoolAid\Nadota\Http\Fields\Relations\MorphOne`
**Tipo:** `morphOne`
**Componente:** `FieldMorphOne`
**Visibilidad por defecto:** Solo en detail

#### Uso

```php
MorphOne::make('Imagen', 'image', ImageResource::class)
    ->withFields()
    ->displayAttribute('filename');
```

#### Respuesta API

```json
{
  "key": "image",
  "label": "Imagen",
  "type": "morphOne",
  "showOnIndex": false,
  "showOnDetail": true,
  "value": {
    "key": 1,
    "label": "photo.jpg",
    "resource": "images",
    "fields": [...]
  },
  "props": {
    "relationType": "morphOne",
    "resource": "images"
  }
}
```

---

### MorphMany

Relacion polimorfica uno a muchos.

**Clase:** `SchoolAid\Nadota\Http\Fields\Relations\MorphMany`
**Tipo:** `morphMany`
**Componente:** `FieldMorphMany`
**Visibilidad por defecto:** Solo en detail

#### Uso

```php
MorphMany::make('Comentarios', 'comments', CommentResource::class)
    ->limit(10)
    ->orderBy('created_at', 'desc');
```

#### Respuesta API

```json
{
  "key": "comments",
  "label": "Comentarios",
  "type": "morphMany",
  "showOnIndex": false,
  "showOnDetail": true,
  "value": {
    "data": [
      {"key": 1, "label": "Comentario 1", "resource": "comments", "fields": [...]},
      {"key": 2, "label": "Comentario 2", "resource": "comments", "fields": [...]}
    ],
    "meta": {
      "total": 15,
      "hasMore": true,
      "resource": "comments"
    }
  },
  "props": {
    "limit": 10,
    "orderBy": "created_at",
    "orderDirection": "desc",
    "relationType": "morphMany",
    "resource": "comments"
  }
}
```

---

### MorphToMany

Relacion polimorfica muchos a muchos. Similar a BelongsToMany pero permite que multiples tipos de modelos compartan la misma tabla pivot.

**Clase:** `SchoolAid\Nadota\Http\Fields\Relations\MorphToMany`
**Tipo:** `belongsToMany` (usa el mismo tipo para compatibilidad de frontend)
**Componente:** `FieldMorphToMany`
**Visibilidad por defecto:** Detail, Create, Update

#### Uso

```php
MorphToMany::make('Etiquetas', 'tags', TagResource::class)
    ->withPivot(['order'])
    ->pivotFields([
        Number::make('Orden', 'order'),
    ])
    ->withTimestamps()
    ->limit(20)
    ->orderBy('name', 'asc')
    ->paginated();  // Opcional: habilita paginacion
```

#### Respuesta API

```json
{
  "key": "tags",
  "label": "Etiquetas",
  "type": "belongsToMany",
  "showOnIndex": false,
  "showOnDetail": true,
  "showOnCreation": true,
  "showOnUpdate": true,
  "value": {
    "data": [
      {
        "key": 1,
        "label": "Laravel",
        "resource": "tags",
        "fields": [...],
        "pivot": {"order": 1}
      }
    ],
    "meta": {
      "total": 5,
      "hasMore": false,
      "pivotColumns": ["order"],
      "isPolymorphic": true
    }
  },
  "pivotFields": [...],
  "props": {
    "limit": 20,
    "paginated": false,
    "orderBy": "name",
    "orderDirection": "asc",
    "relationType": "morphToMany",
    "resource": "tags",
    "pivotColumns": ["order"],
    "withPivot": true,
    "withTimestamps": false,
    "isPolymorphic": true,
    "urls": {
      "options": "/nadota-api/posts/resource/field/tags/options?resourceId=1",
      "attach": "/nadota-api/posts/resource/1/attach/tags",
      "detach": "/nadota-api/posts/resource/1/detach/tags",
      "sync": "/nadota-api/posts/resource/1/sync/tags"
    }
  }
}
```

#### URLs de Operaciones

Igual que BelongsToMany, el campo incluye URLs para operaciones:

| URL | Descripcion |
|-----|-------------|
| `urls.options` | Obtener opciones disponibles (incluye `?resourceId=X` para auto-excluir) |
| `urls.attach` | Adjuntar registros a la relacion |
| `urls.detach` | Separar registros de la relacion |
| `urls.sync` | Sincronizar la relacion |
| `urls.paginationUrl` | URL de paginacion (si `paginated()` esta habilitado) |

#### Auto-Exclusion de Registros Relacionados

La URL de `options` incluye `resourceId` para excluir automaticamente los tags que ya estan asociados al modelo actual.

#### Metodos Especificos

| Metodo | Descripcion |
|--------|-------------|
| `withPivot(array $columns)` | Columnas pivot a incluir |
| `pivotFields(array $fields)` | Fields para datos pivot |
| `withTimestamps(bool $value = true)` | Incluir timestamps del pivot |
| `limit(int $limit)` | Limite de items |
| `orderBy(string $field, string $direction)` | Ordenamiento |
| `paginated(bool $paginated = true)` | Paginacion |
| `withFields(bool $value = true)` | Incluir fields en respuesta |
| `fields(array $fields)` | Fields personalizados |
| `exceptFields(array $keys)` | Excluir fields especificos |

---

### MorphedByMany

Inversa de MorphToMany.

**Clase:** `SchoolAid\Nadota\Http\Fields\Relations\MorphedByMany`
**Tipo:** `morphedByMany`
**Componente:** `FieldMorphedByMany`
**Visibilidad por defecto:** Solo en detail

#### Uso

```php
// En TagResource: obtener todos los Posts/Videos que tienen este tag
MorphedByMany::make('Posts', 'posts', PostResource::class)
    ->withPivot(['order'])
    ->limit(10);
```

#### Respuesta API

```json
{
  "key": "posts",
  "label": "Posts",
  "type": "belongsToMany",
  "showOnIndex": false,
  "showOnDetail": true,
  "value": {
    "data": [
      {
        "key": 1,
        "label": "Post Title",
        "resource": "posts",
        "morphType": "App\\Models\\Post",
        "pivot": {"order": 1}
      }
    ],
    "meta": {
      "total": 10,
      "hasMore": true,
      "pivotColumns": ["order"],
      "isPolymorphic": true
    }
  },
  "props": {
    "relationType": "morphedByMany",
    "resource": "posts",
    "isPolymorphic": true
  }
}
```

---

### HasManyThrough

Relacion a traves de modelo intermedio.

**Clase:** `SchoolAid\Nadota\Http\Fields\Relations\HasManyThrough`
**Tipo:** `hasManyThrough`
**Componente:** `FieldHasManyThrough`
**Visibilidad por defecto:** Solo en detail

#### Uso

```php
// Country -> Users -> Posts
HasManyThrough::make('Posts del Pais', 'posts', PostResource::class)
    ->limit(10)
    ->orderBy('created_at', 'desc');
```

#### Respuesta API

```json
{
  "key": "posts",
  "label": "Posts del Pais",
  "type": "hasMany",
  "showOnIndex": false,
  "showOnDetail": true,
  "value": {
    "data": [
      {"key": 1, "label": "Post 1", "resource": "posts", "fields": [...]},
      {"key": 2, "label": "Post 2", "resource": "posts", "fields": [...]}
    ],
    "meta": {
      "total": 50,
      "hasMore": true,
      "resource": "posts"
    }
  },
  "props": {
    "limit": 10,
    "orderBy": "created_at",
    "orderDirection": "desc",
    "relationType": "hasManyThrough",
    "resource": "posts"
  }
}
```

---

### HasOneThrough

Relacion uno a uno a traves de modelo intermedio.

**Clase:** `SchoolAid\Nadota\Http\Fields\Relations\HasOneThrough`
**Tipo:** `hasOneThrough`
**Componente:** `FieldHasOneThrough`
**Visibilidad por defecto:** Solo en detail

#### Uso

```php
// Mechanic -> Car -> Owner
HasOneThrough::make('Dueno', 'carOwner', OwnerResource::class)
    ->withFields();
```

#### Respuesta API

```json
{
  "key": "carOwner",
  "label": "Dueno",
  "type": "hasOne",
  "showOnIndex": false,
  "showOnDetail": true,
  "value": {
    "key": 1,
    "label": "John Doe",
    "resource": "owners",
    "fields": [...]
  },
  "props": {
    "relationType": "hasOneThrough",
    "resource": "owners"
  }
}
```

---

## Metodos Comunes

Todos los campos heredan estos metodos de la clase base `Field`.

### Visibilidad

```php
// Mostrar/ocultar en vistas especificas
$field->showOnIndex(true);
$field->showOnDetail(true);
$field->showOnCreation(true);
$field->showOnUpdate(true);

// Atajos para ocultar
$field->hideFromIndex();
$field->hideFromDetail();
$field->hideFromCreation();
$field->hideFromUpdate();

// Mostrar solo en...
$field->onlyOnIndex();
$field->onlyOnDetail();
$field->onlyOnForms();
$field->exceptOnForms();

// Condicional
$field->showWhen(fn($request, $model) => $model->is_admin);
$field->hideWhen(fn($request, $model) => $model->is_deleted);
```

### Validacion

```php
$field->required();
$field->nullable();
$field->rules(['min:3', 'max:100']);
$field->requiredIf('type', 'premium');
$field->requiredUnless('status', 'draft');
$field->sometimes(fn() => ['required', 'min:5']);
```

### Busqueda y Ordenamiento

```php
$field->searchable();
$field->searchableGlobally();
$field->searchWeight(10);
$field->sortable();
$field->filterable();
```

### Valores por Defecto

```php
$field->default('valor');
$field->defaultFromAttribute('other_field');
$field->defaultUsing(fn($request, $model) => now()->format('Y-m-d'));
$field->defaultWhen(fn($request) => $request->user()->is_admin, 'valor');
```

### Apariencia

```php
// Ancho
$field->width('full');
$field->halfWidth();
$field->oneThirdWidth();
$field->twoThirdsWidth();
$field->oneQuarterWidth();
$field->threeQuartersWidth();

// Dimensiones
$field->maxHeight(300);
$field->minHeight(100);

// Otros
$field->placeholder('Escribe aqui...');
$field->helpText('Texto de ayuda');
$field->readonly();
$field->disabled();
```

### Campos Computados

```php
Input::make('Nombre Completo', 'full_name')
    ->displayUsing(fn($model) => $model->first_name . ' ' . $model->last_name)
    ->computed();
```

---

## Arquitectura

```
Field (clase abstracta)
├── DefaultValueTrait      # Valores por defecto
├── FieldDataAccessorsTrait# Accesores de datos
├── FieldResolveTrait      # Resolucion de valores
├── FilterableTrait        # Filtrado
├── RelationshipTrait      # Relaciones
├── SearchableTrait        # Busqueda
├── SortableTrait          # Ordenamiento
├── ValidationTrait        # Validacion
├── VisibilityTrait        # Visibilidad
└── Makeable               # Factory pattern (::make())
```

### Archivos

```
src/Http/Fields/
├── Field.php                 # Clase base
├── Contracts/FieldInterface.php
├── DataTransferObjects/FieldDTO.php
├── Enums/FieldType.php
├── Traits/                   # Traits compartidos
├── Relations/                # Campos de relacion (11 tipos)
├── Input.php, Number.php, Email.php, URL.php, Password.php
├── Textarea.php, Hidden.php
├── Select.php, Radio.php, Checkbox.php, CheckboxList.php, Toggle.php
├── DateTime.php, Code.php, Json.php, KeyValue.php, ArrayField.php
├── File.php, Image.php
└── CustomComponent.php
```

---

## API de Opciones de Fields

El sistema de opciones permite cargar dinamicamente las opciones disponibles para campos de relacion.

### Endpoints Disponibles

| Endpoint | Descripcion |
|----------|-------------|
| `GET /{resource}/resource/field/{field}/options` | Opciones para un field (BelongsTo, BelongsToMany, etc.) |
| `GET /{resource}/resource/field/{field}/options/paginated` | Opciones paginadas |
| `GET /{resource}/resource/field/{field}/morph-options/{type}` | Opciones para un tipo especifico de MorphTo |
| `GET /{resource}/resource/options` | Opciones del resource completo |

### Parametros de Query

Todos los endpoints de options soportan los siguientes parametros:

| Parametro | Tipo | Default | Max | Descripcion |
|-----------|------|---------|-----|-------------|
| `search` | string | `''` | - | Termino de busqueda |
| `limit` | int | `15` | `100` | Limite de resultados |
| `per_page` | int | `15` | `100` | Resultados por pagina (solo paginado) |
| `page` | int | `1` | - | Numero de pagina (solo paginado) |
| `exclude` | string/array | `[]` | - | IDs a excluir (comma-separated o array) |
| `resourceId` | mixed | `null` | - | ID del modelo padre (auto-excluye relacionados) |
| `orderBy` | string | `null` | - | Campo para ordenar |
| `orderDirection` | string | `'asc'` | - | Direccion del orden |

**Nota:** Los valores de `limit` y `per_page` estan limitados a un maximo de 100 para prevenir queries costosas.

### Respuesta de Options

```json
{
  "success": true,
  "options": [
    {"value": 1, "label": "Opcion 1"},
    {"value": 2, "label": "Opcion 2"}
  ],
  "meta": {
    "total": 2,
    "search": "",
    "limit": 15,
    "fieldType": "belongsTo"
  }
}
```

### Respuesta Paginada

```json
{
  "success": true,
  "data": [
    {"value": 1, "label": "Opcion 1"},
    {"value": 2, "label": "Opcion 2"}
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7,
    "from": 1,
    "to": 15,
    "search": "",
    "fieldType": "belongsToMany"
  }
}
```

### Auto-Exclusion de Registros Relacionados

Cuando se proporciona `resourceId`, el sistema automaticamente excluye los registros que ya estan relacionados con el modelo padre. Esto es util para evitar duplicados en relaciones como BelongsToMany o MorphToMany.

**Ejemplo:**
```
GET /nadota-api/posts/resource/field/tags/options?resourceId=1&search=php
```

Si el Post con ID 1 ya tiene tags con IDs [3, 5, 7], estos seran automaticamente excluidos de los resultados.

### Configuracion de Busqueda en Resource

Los resources pueden configurar que atributos son buscables:

```php
class StudentResource extends Resource
{
    // Atributos directos del modelo a buscar
    protected array $searchableAttributes = ['name', 'email', 'student_id'];

    // Atributos de relaciones a buscar (formato: 'relacion.atributo')
    protected array $searchableRelations = [
        'family.name',           // BelongsTo
        'grade.title',           // BelongsTo
        'enrollments.year',      // HasMany
        'author.profile.bio',    // Nested relation
    ];
}
```

Si no se configuran atributos buscables, el sistema usa un fallback:
`['name', 'title', 'label', 'display_name', 'full_name', 'description']`

### Personalizacion de Query

Los resources pueden personalizar la consulta de options implementando `optionsQuery`:

```php
class StudentResource extends Resource
{
    public function optionsQuery(Builder $query, NadotaRequest $request, array $params = []): Builder
    {
        // Filtrar solo estudiantes activos
        return $query->where('status', 'active');
    }
}

class UserResource extends Resource
{
    public function optionsQuery(Builder $query, NadotaRequest $request, array $params = []): Builder
    {
        // Filtrar por tenant del usuario actual
        return $query->where('tenant_id', $request->user()->tenant_id);
    }
}
```

### Busqueda Personalizada (Meilisearch/Algolia)

Para integraciones con motores de busqueda externos, usar `optionsSearch`:

```php
class StudentResource extends Resource
{
    public function optionsSearch(NadotaRequest $request, array $params = []): Collection|array|null
    {
        $search = $params['search'] ?? '';
        $limit = $params['limit'] ?? 15;
        $exclude = $params['exclude'] ?? [];

        if (empty($search)) {
            return null; // Usar query normal de base de datos
        }

        // Usar Laravel Scout con Meilisearch
        $query = Student::search($search)->take($limit);

        if (!empty($exclude)) {
            $query->whereNotIn('id', $exclude);
        }

        return $query->get();
    }
}
```

---

## Ver Tambien

- [Relations](../RELATIONS.md) - Documentacion detallada de campos de relacion
- [Filters](../FILTERS_API.md) - Sistema de filtros
