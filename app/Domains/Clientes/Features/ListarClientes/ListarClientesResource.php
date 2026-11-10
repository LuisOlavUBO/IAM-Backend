<?php

namespace App\Domains\Clientes\Features\ListarClientes;

use App\Domains\Clientes\Domain\Dto\ClienteResumenDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An OAuth client in the list (ClienteResumen in OpenAPI).
 *
 * @property ClienteResumenDto $resource
 */
final class ListarClientesResource extends JsonResource
{
    /**
     * @return array{id: string, nombre: string, aplicacion: string, revocado: bool, roles: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'nombre' => $this->resource->nombre,
            'aplicacion' => $this->resource->aplicacion,
            'revocado' => $this->resource->revocado,
            'roles' => $this->resource->roles,
        ];
    }
}
