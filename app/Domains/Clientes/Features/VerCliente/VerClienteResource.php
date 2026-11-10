<?php

namespace App\Domains\Clientes\Features\VerCliente;

use App\Domains\Clientes\Domain\Dto\ClienteDetalleDto;
use App\Domains\Clientes\Domain\Dto\RolClienteDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One OAuth client with its grant types, redirections and roles (ClienteDetalle in OpenAPI).
 *
 * @property ClienteDetalleDto $resource
 */
final class VerClienteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $c = $this->resource;

        return [
            'id' => $c->id,
            'nombre' => $c->nombre,
            'aplicacion' => $c->aplicacion,
            'concesiones' => $c->concesiones,
            'confidencial' => $c->confidencial,
            'redirecciones' => $c->redirecciones,
            'revocado' => $c->revocado,
            'creado' => $c->creado?->toIso8601String(),
            'actualizado' => $c->actualizado?->toIso8601String(),
            'roles' => array_map(fn (RolClienteDto $r) => [
                'aplicacion' => $r->aplicacion,
                'nombre' => $r->nombre,
                'descripcion' => $r->descripcion,
            ], $c->roles),
        ];
    }
}
