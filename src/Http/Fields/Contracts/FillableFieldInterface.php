<?php

namespace SchoolAid\Nadota\Http\Fields\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Interface for fields that can fill model attributes.
 *
 * This interface defines the contract for how fields handle
 * storing and updating data on Eloquent models.
 */
interface FillableFieldInterface
{
    /**
     * Fill the model attribute with the field's value from the request.
     *
     * This method is called before the model is saved.
     * Use this for setting simple attributes or handling file uploads.
     *
     * @param Request $request The current request
     * @param Model $model The model being filled
     * @return void
     */
    public function fill(Request $request, Model $model): void;

    /**
     * Perform operations before the model is saved.
     *
     * Called before fill() for any pre-processing needs.
     *
     * @param Request $request The current request
     * @param Model $model The model being saved
     * @param string $operation The operation type: 'store' or 'update'
     * @return void
     */
    public function beforeSave(Request $request, Model $model, string $operation): void;

    /**
     * Perform operations after the model is saved.
     *
     * This method is called after the model is persisted to the database.
     * Use this for relationship syncing (BelongsToMany, MorphToMany, etc.)
     * that requires the model to have an ID.
     *
     * @param Request $request The current request
     * @param Model $model The saved model (with ID)
     * @return void
     */
    public function afterSave(Request $request, Model $model): void;

    /**
     * Determine if this field supports the afterSave callback.
     *
     * Fields that manage relationships or need the model ID
     * should return true.
     *
     * @return bool
     */
    public function supportsAfterSave(): bool;

    /**
     * Get the validation rules for this field.
     *
     * @return array|string
     */
    public function getRules(): array|string;

    /**
     * Get the attribute name for this field.
     *
     * @return string
     */
    public function getAttribute(): string;

    /**
     * Check if this field is computed (not stored in database).
     *
     * @return bool
     */
    public function isComputed(): bool;

    /**
     * Check if this field is readonly.
     *
     * @return bool
     */
    public function isReadonly(): bool;
}
