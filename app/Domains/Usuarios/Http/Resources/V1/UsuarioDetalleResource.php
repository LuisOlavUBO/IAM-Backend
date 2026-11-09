<?php

namespace App\Domains\Usuarios\Http\Resources\V1;

use App\Domains\Usuarios\Application\DetalleUsuario;
use App\Domains\Usuarios\Application\RolAsignado;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A person with their account data and roles grouped by application (UsuarioDetalle in OpenAPI).
 *
 * @property DetalleUsuario $resource
 */
final class UsuarioDetalleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $usuario = $this->resource->usuario;

        return (new UsuarioResource($usuario))->toArray($request) + [
            'baja_desde' => self::fecha($usuario->disabled_at),
            'tiene_clave' => $usuario->tieneClave(),
            'microsoft_vinculado' => $usuario->microsoftVinculado(),
            'clave_temporal_vence' => self::fecha($usuario->temporary_password_expires_at),
            'creado' => self::fecha($usuario->created_at),
            'actualizado' => self::fecha($usuario->updated_at),
            'aplicaciones' => self::aplicaciones($this->resource->roles),
        ];
    }

    /**
     * The roles (already ordered) grouped by application (design D7).
     *
     * @param  list<RolAsignado>  $roles
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

    private static function fecha(?CarbonInterface $fecha): ?string
    {
        return $fecha?->toIso8601String();
    }
}
