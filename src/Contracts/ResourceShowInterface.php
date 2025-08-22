<?php

namespace Said\Nadota\Contracts;

use Said\Nadota\Http\Requests\NadotaRequest;

interface ResourceShowInterface
{
    public function handle(NadotaRequest $request, $id);
}
