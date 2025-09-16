<?php

namespace SchoolAid\Nadota\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use SchoolAid\Nadota\Http\Traits\AuthorizesResources;
use SchoolAid\Nadota\Http\Traits\PrepareResource;

class NadotaRequest extends FormRequest
{
    use AuthorizesResources;
    use PrepareResource;
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
}
