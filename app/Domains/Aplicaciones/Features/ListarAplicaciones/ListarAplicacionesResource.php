<?php

namespace App\Domains\Aplicaciones\Features\ListarAplicaciones;

use App\Domains\Aplicaciones\Domain\Dto\AplicacionResumenDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An application in the list (AplicacionResumen in OpenAPI).
 *
 * @property AplicacionResumenDto $resource
 */
final class ListarAplicacionesResource extends JsonResource
{
    /**
     * @return array{nombre: string, descripcion: ?string, permisos: int, roles: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'nombre' => $this->resource->nombre,
            'descripcion' => $this->resource->descripcion,
            'permisos' => $this->resource->permisos,
            'roles' => $this->resource->roles,
        ];
    }
}
