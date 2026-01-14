<?php

namespace SchoolAid\Nadota\Http\Services;
use SchoolAid\Nadota\Contracts\ResourceAuthorizationInterface;
use Illuminate\Support\Facades\Gate;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class ResourceAuthorizationService implements ResourceAuthorizationInterface
{
    public $model;

    public function setModel($model): self
    {
        $this->model = $model;
        return $this;
    }

    public function authorizedTo(NadotaRequest $request, string $action, array $context = []): bool
    {
        $gate = Gate::getPolicyFor($this->model);

        if (!is_null($gate)) {
            // Try field-specific method first (e.g., attachGrades, detachStudents)
            if (!empty($context['field'])) {
                $fieldSpecificAction = $action . ucfirst($context['field']);
                if (method_exists($gate, $fieldSpecificAction)) {
                    return Gate::forUser($request->user())->allows(
                        $fieldSpecificAction,
                        [$this->model, $context]
                    );
                }
            }

            // Fallback to generic action method
            if (method_exists($gate, $action)) {
                return Gate::forUser($request->user())->allows(
                    $action,
                    empty($context) ? $this->model : [$this->model, $context]
                );
            }
        }

        return true;
    }
}
