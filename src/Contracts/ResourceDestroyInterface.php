<?php

namespace Said\Nadota\Contracts;

use Said\Nadota\Http\Requests\NadotaRequest;

interface ResourceDestroyInterface
{
    public function handle(NadotaRequest $request, $id);
}
