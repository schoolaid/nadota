<?php

namespace SchoolAid\Nadota\Contracts;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

interface ResourceEditInterface
{
    public function handle(NadotaRequest $request, $id);

}
