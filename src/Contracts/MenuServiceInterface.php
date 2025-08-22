<?php

namespace Said\Nadota\Contracts;

use Said\Nadota\Http\Requests\NadotaRequest;

interface MenuServiceInterface
{
    public function handle(NadotaRequest $request);
}
