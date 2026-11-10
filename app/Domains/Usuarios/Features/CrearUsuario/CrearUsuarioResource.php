<?php

namespace App\Domains\Usuarios\Features\CrearUsuario;

use App\Domains\Usuarios\Domain\Dto\PersonaCreadaDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The person just created (PersonaCreada in OpenAPI): their uuid and the temporary password, shown only here.
 *
 * @property PersonaCreadaDto $resource
 */
final class CrearUsuarioResource extends JsonResource
{
    /**
     * @return array{uuid: string, clave_temporal: array{clave: string, vence: string}|null}
     */
    public function toArray(Request $request): array
    {
        $clave = $this->resource->claveTemporal;

        return [
            'uuid' => $this->resource->uuid,
            'clave_temporal' => $clave === null ? null : ['clave' => $clave->clave, 'vence' => $clave->venceEn->toIso8601String()],
        ];
    }
}
