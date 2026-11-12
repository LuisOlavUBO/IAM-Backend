<?php

namespace App\Domains\Aplicaciones\Features\EditarPermiso;

use App\Domains\Aplicaciones\Domain\Dto\PermisoParaEscribirDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The permission after the edit: its name and description, never its id.
 *
 * @property PermisoParaEscribirDto $resource
 */
final class EditarPermisoResource extends JsonResource
{
    /**
     * @return array{nombre: string, descripcion: string}
     */
    public function toArray(Request $request): array
    {
        return ['nombre' => $this->resource->nombre, 'descripcion' => $this->resource->descripcion];
    }
}
