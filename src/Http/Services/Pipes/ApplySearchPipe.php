<?php

namespace SchoolAid\Nadota\Http\Services\Pipes;

use Closure;
use SchoolAid\Nadota\Http\DataTransferObjects\IndexRequestDTO;

class ApplySearchPipe
{
    public function handle(IndexRequestDTO $data, Closure $next)
    {
        $resource = $data->resource;
        $searchKey = $resource->getSearchKey();
        $search = $data->request->get($searchKey);

        if (empty($search)) {
            return $next($data);
        }
        $searchableAttributes = $resource->getSearchableAttributes();
        $searchableRelations = $resource->getSearchableRelations();

        // Si no hay nada configurado para buscar, continuar
        if (empty($searchableAttributes) && empty($searchableRelations)) {
            return $next($data);
        }

        $data->query->where(function ($query) use ($search, $searchableAttributes, $searchableRelations) {
            // Buscar en atributos directos
            foreach ($searchableAttributes as $attribute) {
                $query->orWhere($attribute, 'LIKE', "%{$search}%");
            }

            // Buscar en relaciones
            foreach ($searchableRelations as $relationPath) {
                $this->applyRelationSearch($query, $relationPath, $search);
            }
        });

        return $next($data);
    }

    /**
     * Aplica búsqueda en una relación
     * Soporta relaciones anidadas: 'user.name', 'category.parent.title'
     */
    protected function applyRelationSearch($query, string $relationPath, string $search): void
    {
        $parts = explode('.', $relationPath);
        $attribute = array_pop($parts); // Último elemento es el atributo
        $relation = implode('.', $parts); // El resto es la relación

        if (empty($relation)) {
            // Si no hay relación, es un atributo directo (ya manejado arriba)
            return;
        }

        $query->orWhereHas($relation, function ($q) use ($attribute, $search) {
            $q->where($attribute, 'LIKE', "%{$search}%");
        });
    }
}
