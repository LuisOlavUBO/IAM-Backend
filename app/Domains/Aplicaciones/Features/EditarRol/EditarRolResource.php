<?php

namespace App\Domains\Aplicaciones\Features\EditarRol;

use App\Domains\Aplicaciones\Domain\Dto\RolParaEscribirDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The role after the edit: its name and description, never its id.
 *
 * @property RolParaEscribirDto $resource
 */
final class EditarRolResource extends JsonResource
{
    /**
     * @return array{nombre: string, descripcion: string|null}
     */
    public function toArray(Request $request): array
    {
        return ['nombre' => $this->resource->nombre, 'descripcion' => $this->resource->descripcion];
    }
}
