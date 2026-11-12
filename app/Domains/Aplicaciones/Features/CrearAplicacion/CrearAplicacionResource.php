<?php

namespace App\Domains\Aplicaciones\Features\CrearAplicacion;

use App\Domains\Aplicaciones\Domain\Dto\AplicacionParaEscribirDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The new application: its name and description, never its id.
 *
 * @property AplicacionParaEscribirDto $resource
 */
final class CrearAplicacionResource extends JsonResource
{
    /**
     * @return array{nombre: string, descripcion: string|null}
     */
    public function toArray(Request $request): array
    {
        return ['nombre' => $this->resource->nombre, 'descripcion' => $this->resource->descripcion];
    }
}
