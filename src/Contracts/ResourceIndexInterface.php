<?php

namespace Said\Nadota\Contracts;

use Said\Nadota\Http\Requests\NadotaRequest;

interface ResourceIndexInterface
{
    public function handle(NadotaRequest $request);
}
