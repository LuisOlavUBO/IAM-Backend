<?php

namespace App\Domains\Usuarios\Http\Resources\V1;

use App\Domains\Usuarios\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A person in the list (UsuarioResumen in OpenAPI). A whitelist: never the id or a credential.
 *
 * @property Usuario $resource
 */
final class UsuarioResource extends JsonResource
{
    /**
     * @return array{uuid: string, nombre: string, correo: string, rut: ?string, estado: 'activo'|'baja'}
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => Str::lower($this->resource->uuid),
            'nombre' => $this->resource->name,
            'correo' => $this->resource->email,
            'rut' => $this->resource->rut,
            'estado' => $this->resource->estaDeBaja() ? 'baja' : 'activo',
        ];
    }
}
