<?php

namespace SchoolAid\Nadota\Contracts;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

interface ResourceDestroyInterface
{
    public function handle(NadotaRequest $request, $id);
}
