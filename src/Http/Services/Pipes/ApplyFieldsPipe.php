<?php

namespace Said\Nadota\Http\Services\Pipes;

use Closure;
use Said\Nadota\Http\DataTransferObjects\IndexRequestDTO;

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
