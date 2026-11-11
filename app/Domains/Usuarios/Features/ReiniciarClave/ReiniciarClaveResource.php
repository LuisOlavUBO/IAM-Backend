<?php

namespace App\Domains\Usuarios\Features\ReiniciarClave;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The reset password (ClaveReiniciada in OpenAPI): the temporary password, shown only here, and whether
 * API-Auth closed the person's sessions.
 *
 * @property ClaveReiniciadaDto $resource
 */
final class ReiniciarClaveResource extends JsonResource
{
    /**
     * @return array{clave_temporal: array{clave: string, vence: string}, sesiones_revocadas: bool}
     */
    public function toArray(Request $request): array
    {
        $clave = $this->resource->claveTemporal;

        return [
            'clave_temporal' => ['clave' => $clave->clave, 'vence' => $clave->venceEn->toIso8601String()],
            'sesiones_revocadas' => $this->resource->sesionesRevocadas,
        ];
    }
}
