<?php

namespace SchoolAid\Nadota\Contracts;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use Symfony\Component\HttpFoundation\Response;

interface ResourceExportInterface
{
    public function handle(NadotaRequest $request): Response;
}
