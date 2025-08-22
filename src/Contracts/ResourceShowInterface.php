<?php

namespace SchoolAid\Nadota\Contracts;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

interface ResourceShowInterface
{
    public function handle(NadotaRequest $request, $id);
}
