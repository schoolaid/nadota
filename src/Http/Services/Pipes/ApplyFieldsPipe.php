<?php

namespace SchoolAid\Nadota\Http\Services\Pipes;

use Closure;
use SchoolAid\Nadota\Http\DataTransferObjects\IndexRequestDTO;

class ApplyFieldsPipe
{
 
    public function handle(IndexRequestDTO $data, Closure $next)
    {
        if ($fields = $data->request->input('fields')) {
            $columns = explode(',', $fields);
            $data->query->select($columns);
        }

        return $next($data);
    }
}
