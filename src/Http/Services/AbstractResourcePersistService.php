<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Contracts\FillableFieldInterface;
use SchoolAid\Nadota\Http\Fields\File;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\Traits\ProcessesFields;
use SchoolAid\Nadota\Http\Traits\TracksActionEvents;

/**
 * Abstract base service for store and update operations.
 *
 * Uses Template Method pattern to define the algorithm structure
 * while allowing subclasses to customize specific steps.
 */
abstract class AbstractResourcePersistService
{
    use ProcessesFields;
    use TracksActionEvents;

    /**
     * Handle the persist operation.
     *
     * @param NadotaRequest $request
     * @param mixed|null $id Optional ID for update operations
     * @return JsonResponse
     */
    public function handle(NadotaRequest $request, mixed $id = null): JsonResponse
    {
        $this->prepareRequest($request);

        $resource = $request->getResource();
        $model = $this->getModel($request, $resource, $id);

        $this->authorize($request, $model);

        $fields = $this->filterFields($resource, $request);

        $rules = $this->buildValidationRules($fields, $this->isUpdate() ? $model : null);

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $attributes = $this->collectFieldAttributes($fields);

        $validatedData = $validator->safe()->only($attributes);

        // Store original data for update tracking
        $originalData = $this->isUpdate() ? $model->getAttributes() : null;

        try {
            DB::beginTransaction();

            // Call resource hook before operation
            $this->callBeforeHook($resource, $model, $request);

            // Process fields (beforeSave + fill)
            $this->processFields($fields, $request, $model, $resource, $validatedData);

            // Save the model
            $model->save();

            // Process afterSave for fields that need it (relations)
            $this->processAfterSave($fields, $request, $model);

            // Call resource hook after operation
            $this->callAfterHook($resource, $model, $request, $originalData);

            // Track the action
            $this->trackAction($model, $request, $validatedData, $originalData);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse($e);
        }

        return $this->successResponse($model);
    }

    /**
     * Prepare the request (resource resolution, etc.)
     *
     * @param NadotaRequest $request
     * @return void
     */
    protected function prepareRequest(NadotaRequest $request): void
    {
        if ($this->isUpdate()) {
            $request->prepareResource();
        }
    }

    /**
     * Get the model for the operation.
     *
     * @param NadotaRequest $request
     * @param ResourceInterface $resource
     * @param mixed $id
     * @return Model
     */
    abstract protected function getModel(NadotaRequest $request, ResourceInterface $resource, $id): Model;

    /**
     * Authorize the operation.
     *
     * @param NadotaRequest $request
     * @param Model $model
     * @return void
     */
    protected function authorize(NadotaRequest $request, Model $model): void
    {
        $request->authorized($this->getAuthorizationAction(), $this->isUpdate() ? $model : null);
    }

    /**
     * Get the authorization action name.
     *
     * @return string
     */
    abstract protected function getAuthorizationAction(): string;

    /**
     * Filter fields for this operation.
     *
     * @param ResourceInterface $resource
     * @param NadotaRequest $request
     * @return Collection
     */
    abstract protected function filterFields(ResourceInterface $resource, NadotaRequest $request): Collection;

    /**
     * Process all fields for the operation.
     *
     * @param Collection $fields
     * @param NadotaRequest $request
     * @param Model $model
     * @param ResourceInterface $resource
     * @param array $validatedData
     * @return void
     */
    protected function processFields(
        Collection $fields,
        NadotaRequest $request,
        Model $model,
        ResourceInterface $resource,
        array $validatedData
    ): void {
        $operation = $this->isUpdate() ? 'update' : 'store';

        $fields->each(function ($field) use ($request, $model, $resource, $validatedData, $operation) {
            // Call beforeSave if the field supports it
            if (method_exists($field, 'beforeSave')) {
                $field->beforeSave($request, $model, $operation);
            }

            // Fill the field
            $this->fillField($field, $request, $model, $resource, $validatedData);
        });
    }

    /**
     * Fill a single field.
     *
     * @param mixed $field
     * @param NadotaRequest $request
     * @param Model $model
     * @param ResourceInterface $resource
     * @param array $validatedData
     * @return void
     */
    protected function fillField(
        $field,
        NadotaRequest $request,
        Model $model,
        ResourceInterface $resource,
        array $validatedData
    ): void {
        // Fields that handle their own filling with custom logic
        // BelongsTo: resolves actual FK from Eloquent relationship
        // File: handles file upload
        // MorphTo: handles morph type and id
        if ($field instanceof BelongsTo || $field instanceof File || $field instanceof MorphTo) {
            $field->fill($request, $model);
            return;
        }

        // Fields that implement FillableFieldInterface and have custom fill
        if ($field instanceof FillableFieldInterface) {
            $field->fill($request, $model);
            return;
        }

        // Default handling for simple fields
        $attribute = $field->getAttribute();

        if (isset($validatedData[$attribute])) {
            $resolveMethod = $this->isUpdate() ? 'resolveForUpdate' : 'resolveForStore';
            $model->{$attribute} = $field->{$resolveMethod}($request, $model, $resource, $validatedData[$attribute]);
        }
    }

    /**
     * Process afterSave for fields that support it.
     *
     * @param Collection $fields
     * @param NadotaRequest $request
     * @param Model $model
     * @return void
     */
    protected function processAfterSave(Collection $fields, NadotaRequest $request, Model $model): void
    {
        $fields->each(function ($field) use ($request, $model) {
            if (method_exists($field, 'supportsAfterSave') && $field->supportsAfterSave()) {
                $field->afterSave($request, $model);
            }
        });
    }

    /**
     * Call the before hook on the resource.
     *
     * @param ResourceInterface $resource
     * @param Model $model
     * @param NadotaRequest $request
     * @return void
     */
    abstract protected function callBeforeHook(ResourceInterface $resource, Model $model, NadotaRequest $request): void;

    /**
     * Call the after hook on the resource.
     *
     * @param ResourceInterface $resource
     * @param Model $model
     * @param NadotaRequest $request
     * @param array|null $originalData
     * @return void
     */
    abstract protected function callAfterHook(
        ResourceInterface $resource,
        Model $model,
        NadotaRequest $request,
        ?array $originalData
    ): void;

    /**
     * Track the action event.
     *
     * @param Model $model
     * @param NadotaRequest $request
     * @param array $validatedData
     * @param array|null $originalData
     * @return void
     */
    abstract protected function trackAction(
        Model $model,
        NadotaRequest $request,
        array $validatedData,
        ?array $originalData
    ): void;

    /**
     * Check if this is an update operation.
     *
     * @return bool
     */
    abstract protected function isUpdate(): bool;

    /**
     * Get the success message.
     *
     * @return string
     */
    abstract protected function getSuccessMessage(): string;

    /**
     * Get the success status code.
     *
     * @return int
     */
    abstract protected function getSuccessStatusCode(): int;

    /**
     * Get the error message.
     *
     * @return string
     */
    abstract protected function getErrorMessage(): string;

    /**
     * Build the success response.
     *
     * @param Model $model
     * @return JsonResponse
     */
    protected function successResponse(Model $model): JsonResponse
    {
        return response()->json([
            'message' => $this->getSuccessMessage(),
            'data' => $model,
        ], $this->getSuccessStatusCode());
    }

    /**
     * Build the error response.
     *
     * @param \Exception $e
     * @return JsonResponse
     */
    protected function errorResponse(\Exception $e): JsonResponse
    {
        return response()->json([
            'message' => $this->getErrorMessage(),
            'error' => $e->getMessage(),
        ], 500);
    }
}
