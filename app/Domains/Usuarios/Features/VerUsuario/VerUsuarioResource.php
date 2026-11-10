<?php

namespace App\Domains\Usuarios\Features\VerUsuario;

use App\Domains\Usuarios\Domain\Dto\RolAsignadoDto;
use App\Domains\Usuarios\Domain\Dto\UsuarioDetalleDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One person with their account data and roles grouped by application (UsuarioDetalle in OpenAPI).
 *
 * @property UsuarioDetalleDto $resource
 */
final class VerUsuarioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $u = $this->resource;

        return [
            'uuid' => $u->uuid,
            'nombre' => $u->nombre,
            'correo' => $u->correo,
            'rut' => $u->rut,
            'estado' => $u->bajaDesde === null ? 'activo' : 'baja',
            'baja_desde' => $u->bajaDesde?->toIso8601String(),
            'tiene_clave' => $u->tieneClave,
            'microsoft_vinculado' => $u->microsoftVinculado,
            'clave_temporal_vence' => $u->claveTemporalVence?->toIso8601String(),
            'creado' => $u->creado?->toIso8601String(),
            'actualizado' => $u->actualizado?->toIso8601String(),
            'aplicaciones' => self::aplicaciones($u->roles),
        ];
    }

    /**
     * The roles (already ordered) grouped by application.
     *
     * @param  list<RolAsignadoDto>  $roles
     * @return list<array{nombre: string, descripcion: ?string, roles: list<array{nombre: string, descripcion: ?string}>}>
     */
    private static function aplicaciones(array $roles): array
    {
        $aplicaciones = [];

        foreach ($roles as $rol) {
            $aplicaciones[$rol->aplicacion] ??= ['nombre' => $rol->aplicacion, 'descripcion' => $rol->aplicacionDescripcion, 'roles' => []];
            $aplicaciones[$rol->aplicacion]['roles'][] = ['nombre' => $rol->rol, 'descripcion' => $rol->rolDescripcion];
        }

        return array_values($aplicaciones);
    }
}
