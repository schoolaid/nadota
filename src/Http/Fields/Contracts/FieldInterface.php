<?php

namespace SchoolAid\Nadota\Http\Fields\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

interface FieldInterface
{
    public function toArray(NadotaRequest $request, ?Model $model = null, ?ResourceInterface $resource = null): array;
    public function resolve(NadotaRequest $request, Model $model, ?ResourceInterface $resource): mixed;
    public function getAttribute(): string;
    public function key(): string;
}
