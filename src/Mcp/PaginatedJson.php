<?php

namespace SchoolAid\Nadota\Mcp;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginatedJson
{
    /**
     * @return array{data: array<int, mixed>, pagination: array{total: int, perPage: int, page: int, lastPage: int}}
     */
    public static function from(LengthAwarePaginator $paginator, Closure $mapRow): array
    {
        return [
            'data' => collect($paginator->items())->map($mapRow)->values()->all(),
            'pagination' => [
                'total' => $paginator->total(),
                'perPage' => $paginator->perPage(),
                'page' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
            ],
        ];
    }
}
