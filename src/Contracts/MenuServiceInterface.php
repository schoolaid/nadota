<?php

namespace SchoolAid\Nadota\Contracts;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

interface MenuServiceInterface
{
    public function handle(NadotaRequest $request);
}
