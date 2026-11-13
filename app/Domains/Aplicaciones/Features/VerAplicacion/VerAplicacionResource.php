<?php

namespace App\Domains\Aplicaciones\Features\VerAplicacion;

use App\Domains\Aplicaciones\Domain\Dto\AplicacionDetalleDto;
use App\Domains\Aplicaciones\Domain\Dto\PermisoDto;
use App\Domains\Aplicaciones\Domain\Dto\RolDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One application with its permissions and its roles (AplicacionDetalle in OpenAPI).
 *
 * @property AplicacionDetalleDto $resource
 */
final class VerAplicacionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $a = $this->resource;

        return [
            'nombre' => $a->nombre,
            'descripcion' => $a->descripcion,
            'creada' => $a->creada?->toIso8601String(),
            'actualizada' => $a->actualizada?->toIso8601String(),
            'baja_desde' => $a->bajaDesde?->toIso8601String(),
            'permisos' => array_map(fn (PermisoDto $p) => ['nombre' => $p->nombre, 'descripcion' => $p->descripcion], $a->permisos),
            'roles' => array_map(fn (RolDto $r) => [
                'nombre' => $r->nombre,
                'descripcion' => $r->descripcion,
                'permisos' => $r->permisos,
                'baja_desde' => $r->bajaDesde?->toIso8601String(),
            ], $a->roles),
        ];
    }
}
