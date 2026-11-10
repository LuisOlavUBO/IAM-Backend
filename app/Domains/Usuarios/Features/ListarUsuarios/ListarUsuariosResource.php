<?php

namespace App\Domains\Usuarios\Features\ListarUsuarios;

use App\Domains\Usuarios\Domain\Dto\UsuarioResumenDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A person in the list (UsuarioResumen in OpenAPI).
 *
 * @property UsuarioResumenDto $resource
 */
final class ListarUsuariosResource extends JsonResource
{
    /**
     * @return array{uuid: string, nombre: string, correo: string, rut: ?string, estado: 'activo'|'baja'}
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->resource->uuid,
            'nombre' => $this->resource->nombre,
            'correo' => $this->resource->correo,
            'rut' => $this->resource->rut,
            'estado' => $this->resource->deBaja ? 'baja' : 'activo',
        ];
    }
}
