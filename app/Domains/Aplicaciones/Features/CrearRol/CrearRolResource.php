<?php

namespace App\Domains\Aplicaciones\Features\CrearRol;

use App\Domains\Aplicaciones\Domain\Dto\RolParaEscribirDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The new role, still without permissions: its name and description, never its id.
 *
 * @property RolParaEscribirDto $resource
 */
final class CrearRolResource extends JsonResource
{
    /**
     * @return array{nombre: string, descripcion: string|null, permisos: list<string>}
     */
    public function toArray(Request $request): array
    {
        return ['nombre' => $this->resource->nombre, 'descripcion' => $this->resource->descripcion, 'permisos' => []];
    }
}
