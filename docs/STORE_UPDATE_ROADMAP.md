# Roadmap: Mejora del Sistema Store/Update

Este documento describe el plan de implementación para mejorar los servicios ResourceStoreService y ResourceUpdateService.

## Análisis del Estado Actual

### Problemas Identificados

| Problema | Severidad | Impacto |
|----------|-----------|---------|
| Código duplicado entre Store y Update | Alta | Mantenibilidad reducida |
| Lógica de validación repetida | Alta | Inconsistencias potenciales |
| Manejo especial hardcodeado (instanceof) | Media | Difícil extender |
| Sin soporte para afterSave en Store | Alta | BelongsToMany no funciona en create |
| Fill no consistente entre campos | Media | Comportamiento impredecible |
| Sin hooks beforeStore/afterStore | Media | Limitada extensibilidad |
| MorphTo manejo complejo inline | Media | Código difícil de seguir |

### Arquitectura Actual

```
ResourceStoreService                ResourceUpdateService
├── Filter fields                   ├── Filter fields
├── Build validation rules          ├── Build validation rules (with :id)
├── Validate request                ├── Validate request
├── Collect attributes              ├── Collect attributes
├── Begin transaction               ├── Store original data
├── Process fields                  ├── Begin transaction
│   ├── File.fill()                 ├── Process fields
│   ├── MorphTo.fill()              │   ├── File.fill()
│   └── Default: resolveForStore    │   ├── MorphTo.fill()
├── model.save()                    │   └── Default: resolveForUpdate
├── Track action event              ├── model.save()
└── Commit                          ├── Track action event
                                    └── Commit
```

### Campos con fill() Personalizado

| Campo | Comportamiento fill() |
|-------|----------------------|
| Field (base) | Asigna valor del request al atributo |
| File | Sube archivo y asigna path |
| MorphTo | Asigna type e id desde request |
| Json | Decodifica JSON y respeta casts del modelo |
| ArrayField | Similar a Json |
| KeyValue | Similar a Json |
| BelongsToMany | Vacío (usa afterSave) |
| HasMany | Vacío (no aplica en store/update) |
| MorphToMany | Vacío (usa afterSave) |
| MorphedByMany | Vacío (usa afterSave) |

---

## Fase 1: Contrato Base y Trait Compartido

### 1.1 Crear Contrato para Field Filling
- [ ] Crear `FillableFieldInterface`
- [ ] Definir métodos: `fill()`, `afterSave()`, `beforeSave()`, `supportsAfterSave()`

### 1.2 Crear Trait para Lógica Compartida
- [ ] Crear `ProcessesFields` trait
- [ ] Mover lógica de filtrado de campos
- [ ] Mover lógica de construcción de reglas de validación
- [ ] Mover lógica de recolección de atributos
- [ ] Método para reemplazo de `:id` placeholder

**Archivos:**
- `src/Http/Fields/Contracts/FillableFieldInterface.php`
- `src/Http/Services/Traits/ProcessesFields.php`

---

## Fase 2: Servicio Base Abstracto

### 2.1 Crear AbstractResourcePersistService
- [ ] Extraer lógica común de Store y Update
- [ ] Template method pattern para el flujo
- [ ] Métodos abstractos para diferencias específicas

```php
abstract class AbstractResourcePersistService
{
    use ProcessesFields, TracksActionEvents;

    abstract protected function getModel(NadotaRequest $request, $id = null): Model;
    abstract protected function getAuthorizationAction(): string;
    abstract protected function getFieldsFilter(): callable;
    abstract protected function trackAction(Model $model, NadotaRequest $request, array $data, ?array $original = null): void;
    abstract protected function getSuccessMessage(): string;
    abstract protected function getSuccessStatusCode(): int;

    public function handle(NadotaRequest $request, $id = null): JsonResponse
    {
        // Template method implementation
    }
}
```

### 2.2 Refactorizar ResourceStoreService
- [ ] Extender AbstractResourcePersistService
- [ ] Implementar métodos abstractos específicos
- [ ] Mantener compatibilidad hacia atrás

### 2.3 Refactorizar ResourceUpdateService
- [ ] Extender AbstractResourcePersistService
- [ ] Implementar métodos abstractos específicos
- [ ] Mantener compatibilidad hacia atrás

**Archivos:**
- `src/Http/Services/AbstractResourcePersistService.php`
- `src/Http/Services/ResourceStoreService.php` (modificar)
- `src/Http/Services/ResourceUpdateService.php` (modificar)

---

## Fase 3: Soporte para afterSave en Store

### 3.1 Implementar afterSave en el Flujo
- [ ] Llamar `afterSave()` en campos que lo soporten
- [ ] Orden: fill() → save() → afterSave()
- [ ] Pasar modelo ya persistido

### 3.2 Actualizar Campos de Relación
- [ ] Verificar BelongsToMany.afterSave() funciona en create
- [ ] Verificar MorphToMany.afterSave() funciona en create
- [ ] Agregar afterSave a MorphedByMany si necesario

**Archivos:**
- `src/Http/Services/AbstractResourcePersistService.php` (modificar)
- `src/Http/Fields/Relations/BelongsToMany.php` (verificar)
- `src/Http/Fields/Relations/MorphToMany.php` (verificar)

---

## Fase 4: Hooks en Resource

### 4.1 Hooks Before/After para Store
- [ ] `beforeStore(Model $model, NadotaRequest $request): void`
- [ ] `afterStore(Model $model, NadotaRequest $request): void`

### 4.2 Hooks Before/After para Update
- [ ] `beforeUpdate(Model $model, NadotaRequest $request): void`
- [ ] `afterUpdate(Model $model, NadotaRequest $request, array $originalData): void`

### 4.3 Integración en Services
- [ ] Llamar hooks desde AbstractResourcePersistService
- [ ] Documentar orden de ejecución

**Archivos:**
- `src/Resource.php` (modificar)
- `src/Http/Services/AbstractResourcePersistService.php` (modificar)

---

## Fase 5: FieldProcessor Service

### 5.1 Crear FieldProcessor
- [ ] Centralizar lógica de procesamiento de campos
- [ ] Método `processForStore()`
- [ ] Método `processForUpdate()`
- [ ] Manejo unificado de campos especiales

### 5.2 Eliminar instanceof Checks
- [ ] Usar polimorfismo en lugar de instanceof
- [ ] Cada campo decide cómo llenarse
- [ ] Soporte para campos que necesitan pre/post procesamiento

```php
class FieldProcessor
{
    public function process(
        Collection $fields,
        Request $request,
        Model $model,
        ResourceInterface $resource,
        array $validatedData,
        string $operation // 'store' | 'update'
    ): void {
        // beforeSave
        $fields->each(fn($f) => $f->beforeSave($request, $model, $operation));

        // fill
        $fields->each(fn($f) => $this->fillField($f, $request, $model, $resource, $validatedData, $operation));

        // Model is saved by caller
    }

    public function afterSave(Collection $fields, Request $request, Model $model): void
    {
        $fields
            ->filter(fn($f) => $f->supportsAfterSave())
            ->each(fn($f) => $f->afterSave($request, $model));
    }
}
```

**Archivos:**
- `src/Http/Services/FieldProcessor.php`
- `src/Http/Services/AbstractResourcePersistService.php` (modificar)

---

## Fase 6: Validación Mejorada

### 6.1 ValidationRulesBuilder
- [ ] Crear clase dedicada para construir reglas
- [ ] Soporte para campos con múltiples atributos (MorphTo)
- [ ] Reemplazo de placeholders (:id, :model, etc.)
- [ ] Reglas condicionales por operación

### 6.2 Mensajes de Error Personalizados
- [ ] Permitir mensajes custom por campo
- [ ] Método `getValidationMessages()` en Field
- [ ] Soporte para traducciones

```php
class ValidationRulesBuilder
{
    public function build(Collection $fields, Model $model = null, string $operation = 'store'): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $fieldRules = $field->getRulesFor($operation);

            if ($field instanceof MorphTo) {
                $rules[$field->getMorphTypeAttribute()] = $fieldRules['type'] ?? [];
                $rules[$field->getMorphIdAttribute()] = $fieldRules['id'] ?? [];
            } else {
                $rules[$field->getAttribute()] = $fieldRules;
            }
        }

        if ($model) {
            $rules = $this->replacePlaceholders($rules, $model);
        }

        return $rules;
    }
}
```

**Archivos:**
- `src/Http/Services/ValidationRulesBuilder.php`
- `src/Http/Fields/Field.php` (modificar - agregar getRulesFor, getValidationMessages)

---

## Fase 7: Transacciones y Rollback Mejorado

### 7.1 Rollback de Archivos
- [ ] Registrar archivos subidos durante la transacción
- [ ] Eliminar archivos si hay rollback
- [ ] Método cleanup en File field

### 7.2 Rollback de Relaciones
- [ ] Registrar cambios en relaciones
- [ ] Revertir si hay error

### 7.3 TransactionManager (opcional)
- [ ] Crear wrapper para transacciones
- [ ] Registrar acciones de cleanup
- [ ] Ejecutar cleanup en rollback

**Archivos:**
- `src/Http/Services/TransactionManager.php` (opcional)
- `src/Http/Fields/File.php` (modificar)

---

## Fase 8: Testing y Documentación

### 8.1 Tests Unitarios
- [ ] Tests para AbstractResourcePersistService
- [ ] Tests para FieldProcessor
- [ ] Tests para ValidationRulesBuilder
- [ ] Tests para hooks en Resource

### 8.2 Tests de Integración
- [ ] Test store con BelongsToMany
- [ ] Test update con MorphTo
- [ ] Test store con File + rollback

### 8.3 Documentación
- [ ] Documentar hooks en Resource
- [ ] Documentar cómo crear campos custom con fill
- [ ] Documentar flujo completo store/update
- [ ] Actualizar docs/fields/README.md

**Archivos:**
- `tests/Unit/Services/AbstractResourcePersistServiceTest.php`
- `tests/Unit/Services/FieldProcessorTest.php`
- `tests/Integration/StoreUpdateFlowTest.php`
- `docs/fields/CUSTOM_FIELDS.md`

---

## Flujo Propuesto Post-Refactor

```
Request → Service.handle()
           │
           ├── getModel() [abstracto]
           ├── authorize()
           ├── filterFields()
           ├── ValidationRulesBuilder.build()
           ├── Validator.validate()
           │
           ├── DB::beginTransaction()
           │   │
           │   ├── Resource.beforeStore/Update()
           │   ├── FieldProcessor.process()
           │   │   ├── field.beforeSave()
           │   │   └── field.fill() [polimórfico]
           │   ├── model.save()
           │   ├── FieldProcessor.afterSave()
           │   │   └── field.afterSave() [relaciones]
           │   ├── Resource.afterStore/Update()
           │   ├── TracksActionEvents.track()
           │   │
           │   └── DB::commit()
           │
           └── JsonResponse
```

---

## Priorización

### Alta Prioridad (Fase 1-3)
- Eliminar código duplicado
- Soporte afterSave en Store (BelongsToMany en create)
- Trait compartido

### Media Prioridad (Fase 4-6)
- Hooks en Resource
- FieldProcessor
- ValidationRulesBuilder

### Baja Prioridad (Fase 7-8)
- Rollback mejorado
- Testing completo
- Documentación

---

## Progreso

- [x] Análisis del sistema actual
- [x] Creación del roadmap
- [ ] Fase 1: Contrato Base y Trait
- [ ] Fase 2: Servicio Base Abstracto
- [ ] Fase 3: Soporte afterSave en Store
- [ ] Fase 4: Hooks en Resource
- [ ] Fase 5: FieldProcessor
- [ ] Fase 6: Validación Mejorada
- [ ] Fase 7: Transacciones y Rollback
- [ ] Fase 8: Testing y Documentación

---

## Notas de Implementación

### Compatibilidad Hacia Atrás

1. Mantener firmas de métodos públicos
2. Nuevos hooks son opcionales (métodos vacíos en Resource base)
3. Campos existentes siguen funcionando sin cambios

### Consideraciones de Performance

1. FieldProcessor no debe agregar overhead significativo
2. Evitar instanciación innecesaria de objetos
3. Lazy loading de servicios

### Orden de Ejecución

```
1. Resource.beforeStore()
2. Field.beforeSave() [cada campo]
3. Field.fill() [cada campo]
4. Model.save()
5. Field.afterSave() [campos que lo soporten]
6. Resource.afterStore()
7. ActionEvent tracking
```
