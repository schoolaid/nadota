<?php

namespace SchoolAid\Nadota\Contracts;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

interface ResourceIndexInterface
{
    public function handle(NadotaRequest $request);
}
