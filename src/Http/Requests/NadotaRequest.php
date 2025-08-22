<?php

namespace Said\Nadota\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Said\Nadota\Http\Traits\AuthorizesResources;
use Said\Nadota\Http\Traits\PrepareResource;

class NadotaRequest extends FormRequest
{
    use AuthorizesResources;
    use PrepareResource;
    public function authorize(): bool
    {
        return true;
    }
}
