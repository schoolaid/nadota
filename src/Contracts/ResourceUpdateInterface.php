<?php

namespace SchoolAid\Nadota\Contracts;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

interface ResourceUpdateInterface
{
    public function handle(NadotaRequest $request, $id);
}
