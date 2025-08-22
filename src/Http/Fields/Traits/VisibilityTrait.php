<?php

namespace Said\Nadota\Http\Fields\Traits;

use Said\Nadota\Http\Requests\NadotaRequest;

trait VisibilityTrait
{
    /**
     * @var mixed
     */
    protected mixed $showOnCreation = true;

    /**
     * @var mixed
     */
    protected mixed $showOnDetail = true;

    /**
     * @var mixed
     */
    protected mixed $showOnUpdate = true;

    /**
     * @var mixed
     */
    protected mixed $showOnIndex = true;

    /**
     * @var callable|null
     */
    protected $hideWhenCallback = null;

    /**
     * @var callable|null
     */
    protected $showWhenCallback = null;

    /**
     *
     * @param mixed $value
     * @return static
     */
    public function showOnCreation(callable|bool $value = true): static
    {
        $this->showOnCreation = $value;
        return $this;
    }

    /**
     *
     * @param callable|bool $value
     * @return static
     */
    public function showOnDetail(callable|bool $value = true): static
    {
        $this->showOnDetail = $value;
        return $this;
    }

    /**
     *
     * @param callable|bool $value
     * @return static
     */
    public function showOnUpdate(callable|bool $value = true): static
    {
        $this->showOnUpdate = $value;
        return $this;
    }

    /**
     *
     * @param callable|bool $value
     * @return static
     */
    public function showOnIndex(callable|bool $value = true): static
    {
        $this->showOnIndex = $value;
        return $this;
    }

    /**
     *
     * @param NadotaRequest|null $request
     * @param mixed|null $resource
     * @return bool
     */
    public function isShowOnCreation(?NadotaRequest $request = null, mixed $resource = null): bool
    {
        return $this->evaluateVisibility($this->showOnCreation, $request, $resource);
    }

    /**
     *
     * @param NadotaRequest|null $request
     * @param mixed|null $resource
     * @return bool
     */
    public function isShowOnDetail(?NadotaRequest $request = null, mixed $resource = null): bool
    {
        return $this->evaluateVisibility($this->showOnDetail, $request, $resource);
    }

    /**
     *
     * @param NadotaRequest|null $request
     * @param mixed|null $resource
     * @return bool
     */
    public function isShowOnUpdate(?NadotaRequest $request = null, mixed $resource = null): bool
    {
        return $this->evaluateVisibility($this->showOnUpdate, $request, $resource);
    }

    /**
     *
     * @param NadotaRequest|null $request
     * @param mixed|null $resource
     * @return bool
     */
    public function isShowOnIndex(?NadotaRequest $request = null, mixed $resource = null): bool
    {
        return $this->evaluateVisibility($this->showOnIndex, $request, $resource);
    }

    /**
     * Hide field from index view
     * @return static
     */
    public function hideFromIndex(): static
    {
        $this->showOnIndex = false;
        return $this;
    }

    /**
     * Hide field from detail view
     * @return static
     */
    public function hideFromDetail(): static
    {
        $this->showOnDetail = false;
        return $this;
    }

    /**
     * Hide field from creation form
     * @return static
     */
    public function hideFromCreation(): static
    {
        $this->showOnCreation = false;
        return $this;
    }

    /**
     * Hide field from update form
     * @return static
     */
    public function hideFromUpdate(): static
    {
        $this->showOnUpdate = false;
        return $this;
    }

    /**
     * Show field only on index view
     * @return static
     */
    public function onlyOnIndex(): static
    {
        $this->showOnIndex = true;
        $this->showOnDetail = false;
        $this->showOnCreation = false;
        $this->showOnUpdate = false;
        return $this;
    }

    /**
     * Show field only on detail view
     * @return static
     */
    public function onlyOnDetail(): static
    {
        $this->showOnIndex = false;
        $this->showOnDetail = true;
        $this->showOnCreation = false;
        $this->showOnUpdate = false;
        return $this;
    }

    /**
     * Show field only on forms (creation and update)
     * @return static
     */
    public function onlyOnForms(): static
    {
        $this->showOnIndex = false;
        $this->showOnDetail = false;
        $this->showOnCreation = true;
        $this->showOnUpdate = true;
        return $this;
    }

    /**
     * Hide field from all forms (creation and update)
     * @return static
     */
    public function exceptOnForms(): static
    {
        $this->showOnIndex = true;
        $this->showOnDetail = true;
        $this->showOnCreation = false;
        $this->showOnUpdate = false;
        return $this;
    }

    /**
     * Hide field when condition is true
     * @param callable $callback
     * @return static
     */
    public function hideWhen(callable $callback): static
    {
        $this->hideWhenCallback = $callback;
        return $this;
    }

    /**
     * Show field when condition is true
     * @param callable $callback
     * @return static
     */
    public function showWhen(callable $callback): static
    {
        $this->showWhenCallback = $callback;
        return $this;
    }

    /**
     * Show field only when condition is true
     * @param callable $callback
     * @return static
     */
    public function onlyWhen(callable $callback): static
    {
        return $this->showWhen($callback);
    }

    /**
     *
     * @param callable|bool $visibility
     * @param NadotaRequest|null $request
     * @param mixed|null $resource
     * @return bool
     */
    protected function evaluateVisibility(callable|bool $visibility, ?NadotaRequest $request, mixed $resource): bool
    {
        // Check hide condition first
        if ($this->hideWhenCallback && call_user_func($this->hideWhenCallback, $request, $resource)) {
            return false;
        }

        // Check show condition
        if ($this->showWhenCallback && !call_user_func($this->showWhenCallback, $request, $resource)) {
            return false;
        }

        // Then evaluate normal visibility
        if (is_callable($visibility)) {
            return (bool) call_user_func($visibility, $request, $resource);
        }

        return (bool) $visibility;
    }
}
