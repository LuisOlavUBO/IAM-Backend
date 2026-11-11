<?php

namespace App\Domains\Clientes\Features\CrearCliente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The client just created (ClienteCreado in OpenAPI): its id and its secret, shown only here.
 *
 * @property ClienteCreadoDto $resource
 */
final class CrearClienteResource extends JsonResource
{
    /**
     * @return array{id: string, secreto: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'secreto' => $this->resource->secreto,
        ];
    }
}
