<?php

namespace SchoolAid\Nadota\Http\Services;
use SchoolAid\Nadota\Contracts\ResourceAuthorizationInterface;
use Illuminate\Support\Facades\Gate;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class ResourceAuthorizationService implements ResourceAuthorizationInterface
{
    /**
     * Actions that, when the policy defines neither a field-specific nor a generic
     * method for them, are decided by the policy's `update` ability on the parent.
     *
     * @var array<int, string>
     */
    private const PARENT_UPDATE_FALLBACK_ACTIONS = ['attach', 'detach'];

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

            // Relation changes are an update of the parent when the policy is silent on them
            if (in_array($action, self::PARENT_UPDATE_FALLBACK_ACTIONS, true)) {
                return $this->authorizedTo($request, 'update');
            }
        }

        return true;
    }
}
