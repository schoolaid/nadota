<?php

namespace Said\Nadota\Http\Traits;

trait VisibleWhen
{
    public mixed $canSeeCallback;

    public function canSee($callback): static
    {
        $this->canSeeCallback = $callback;
        return $this;
    }
}
