<?php

namespace Said\Nadota\Contracts;

use Said\Nadota\Http\Requests\NadotaRequest;

interface ResourceUpdateInterface
{
    public function handle(NadotaRequest $request, $id);
}
