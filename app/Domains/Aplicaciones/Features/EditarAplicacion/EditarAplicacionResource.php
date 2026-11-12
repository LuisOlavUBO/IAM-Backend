<?php

namespace App\Domains\Aplicaciones\Features\EditarAplicacion;

use App\Domains\Aplicaciones\Domain\Dto\AplicacionParaEscribirDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The application after the edit: its name and description, never its id.
 *
 * @property AplicacionParaEscribirDto $resource
 */
final class EditarAplicacionResource extends JsonResource
{
    /**
     * @return array{nombre: string, descripcion: string|null}
     */
    public function toArray(Request $request): array
    {
        return ['nombre' => $this->resource->nombre, 'descripcion' => $this->resource->descripcion];
    }
}
