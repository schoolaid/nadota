<?php

namespace Said\Nadota\Http\Fields\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Said\Nadota\Contracts\ResourceInterface;
use Said\Nadota\Http\Requests\NadotaRequest;

interface FieldInterface
{
    public function toArray(NadotaRequest $request, ?Model $model = null, ?ResourceInterface $resource = null): array;
    public function resolve(NadotaRequest $request, Model $model, ?ResourceInterface $resource): mixed;
    public function getAttribute(): string;
    public function key(): string;
}
