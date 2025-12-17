<?php

namespace SchoolAid\Nadota\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\ValidatedInput;
use SchoolAid\Nadota\Http\Traits\AuthorizesResources;
use SchoolAid\Nadota\Http\Traits\PrepareResource;

class NadotaRequest extends FormRequest
{
    use AuthorizesResources;
    use PrepareResource;

    /**
     * Custom validator instance for dynamic validation.
     */
    protected ?Validator $customValidator = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => 'sometimes|string',
        ];
    }

    /**
     * Set a custom validator instance.
     *
     * @param Validator $validator
     * @return void
     */
    public function setCustomValidator(Validator $validator): void
    {
        $this->customValidator = $validator;
    }

    /**
     * Get the custom validator instance.
     *
     * @return Validator|null
     */
    public function getCustomValidator(): ?Validator
    {
        return $this->customValidator;
    }
}
