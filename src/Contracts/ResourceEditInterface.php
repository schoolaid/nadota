<?php

namespace Said\Nadota\Contracts;

use Said\Nadota\Http\Requests\NadotaRequest;

interface ResourceEditInterface
{
    public function handle(NadotaRequest $request, $id);

}
