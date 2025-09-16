<?php

namespace SchoolAid\Nadota\Http\Traits;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

trait VisibleWhen
{
    public mixed $canSeeCallback = null;

    public function canSee($callback): static
    {
        $this->canSeeCallback = $callback;
        return $this;
    }

    public function isVisible(NadotaRequest $request): bool
    {
        if ($this->canSeeCallback === null) {
            return true;
        }

        return call_user_func($this->canSeeCallback, $request);
    }
}
