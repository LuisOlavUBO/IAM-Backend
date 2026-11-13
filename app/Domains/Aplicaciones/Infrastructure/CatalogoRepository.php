<?php

namespace App\Domains\Aplicaciones\Infrastructure;

use App\Domains\Aplicaciones\Domain\Dto\AplicacionParaEscribirDto;
use App\Domains\Aplicaciones\Domain\Dto\ImpactoDto;
use App\Domains\Aplicaciones\Domain\Dto\PermisoParaEscribirDto;
use App\Domains\Aplicaciones\Domain\Dto\RolParaEscribirDto;
use App\Domains\Aplicaciones\Domain\Interfaces\ICatalogoRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * ICatalogoRepository against SQL Server. It writes only applications, oauth_scopes, roles and
 * oauth_scope_role, which API-IAM owns and API-Auth reads on every token it issues.
 */
final class CatalogoRepository implements ICatalogoRepository
{
    public function aplicacion(string $nombre): ?AplicacionParaEscribirDto
    {
        $fila = DB::table('applications')->where('name', $nombre)->first(['id', 'name', 'description']);

        return $fila === null ? null : new AplicacionParaEscribirDto((int) $fila->id, $fila->name, $fila->description);
    }

    public function permiso(string $nombre): ?PermisoParaEscribirDto
    {
        return $this->permisos([$nombre])[0] ?? null;
    }

    public function permisos(array $nombres): array
    {
        if ($nombres === []) {
            return [];
        }

        return $this->consultaDePermisos()
            ->whereIn('oauth_scopes.name', array_values(array_unique($nombres)))
            ->orderBy('oauth_scopes.name')
            ->get($this->columnasDePermiso())
            ->map(fn (object $fila) => self::permisoDto($fila))
            ->all();
    }

    public function rol(int $aplicacionId, string $nombre): ?RolParaEscribirDto
    {
        $fila = DB::table('roles')
            ->join('applications', 'applications.id', '=', 'roles.application_id')
            ->where('roles.application_id', $aplicacionId)
            ->where('roles.name', $nombre)
            ->first(['roles.id', 'applications.name as aplicacion', 'roles.name', 'roles.description']);

        return $fila === null ? null : new RolParaEscribirDto((int) $fila->id, $fila->aplicacion, $fila->name, $fila->description);
    }

    public function crearAplicacion(string $nombre, ?string $descripcion): int
    {
        return (int) DB::table('applications')->insertGetId([
            'name' => $nombre, 'description' => $descripcion, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function crearPermiso(int $aplicacionId, string $nombre, string $descripcion): int
    {
        return (int) DB::table('oauth_scopes')->insertGetId([
            'application_id' => $aplicacionId, 'name' => $nombre, 'description' => $descripcion, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function crearRol(int $aplicacionId, string $nombre, ?string $descripcion): int
    {
        return (int) DB::table('roles')->insertGetId([
            'application_id' => $aplicacionId, 'name' => $nombre, 'description' => $descripcion, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function describirAplicacion(int $id, ?string $descripcion): void
    {
        DB::table('applications')->where('id', $id)->update(['description' => $descripcion, 'updated_at' => now()]);
    }

    public function describirPermiso(int $id, string $descripcion): void
    {
        DB::table('oauth_scopes')->where('id', $id)->update(['description' => $descripcion, 'updated_at' => now()]);
    }

    public function describirRol(int $id, ?string $descripcion): void
    {
        DB::table('roles')->where('id', $id)->update(['description' => $descripcion, 'updated_at' => now()]);
    }

    public function permisosDeRol(int $rolId): array
    {
        return $this->consultaDePermisos()
            ->join('oauth_scope_role', 'oauth_scope_role.oauth_scope_id', '=', 'oauth_scopes.id')
            ->where('oauth_scope_role.role_id', $rolId)
            ->orderBy('oauth_scopes.name')
            ->get($this->columnasDePermiso())
            ->map(fn (object $fila) => self::permisoDto($fila))
            ->all();
    }

    public function asignarPermiso(int $rolId, int $permisoId): void
    {
        DB::table('oauth_scope_role')->insert(['role_id' => $rolId, 'oauth_scope_id' => $permisoId]);
    }

    public function quitarPermiso(int $rolId, int $permisoId): void
    {
        DB::table('oauth_scope_role')->where('role_id', $rolId)->where('oauth_scope_id', $permisoId)->delete();
    }

    public function impactoDeAplicacion(int $id): ImpactoDto
    {
        return ImpactoDto::deAplicacion(
            DB::table('oauth_scopes')->where('application_id', $id)->count(),
            DB::table('roles')->where('application_id', $id)->count(),
            DB::table('oauth_clients')->where('application_id', $id)->count(),
        );
    }

    public function impactoDePermiso(int $id): ImpactoDto
    {
        $roles = DB::table('oauth_scope_role')
            ->join('roles', 'roles.id', '=', 'oauth_scope_role.role_id')
            ->join('applications', 'applications.id', '=', 'roles.application_id')
            ->where('oauth_scope_role.oauth_scope_id', $id)
            ->orderBy('applications.name')
            ->orderBy('roles.name')
            ->get(['applications.name as aplicacion', 'roles.name'])
            ->map(fn (object $fila) => "{$fila->aplicacion}/{$fila->name}")
            ->all();

        $porSusRoles = fn (string $tabla) => DB::table($tabla)
            ->whereIn("{$tabla}.role_id", DB::table('oauth_scope_role')->where('oauth_scope_id', $id)->select('role_id'));

        return ImpactoDto::dePermiso(
            $roles,
            $porSusRoles('role_user')->distinct()->count('user_id'),
            $porSusRoles('oauth_client_role')->distinct()->count('client_id'),
        );
    }

    public function impactoDeRol(int $id): ImpactoDto
    {
        return ImpactoDto::deRol(
            DB::table('role_user')->where('role_id', $id)->count(),
            DB::table('oauth_client_role')->where('role_id', $id)->count(),
            array_map(fn (PermisoParaEscribirDto $permiso) => $permiso->nombre, $this->permisosDeRol($id)),
        );
    }

    public function borrarAplicacion(int $id): void
    {
        DB::table('applications')->where('id', $id)->delete();
    }

    public function borrarPermiso(int $id): void
    {
        DB::table('oauth_scopes')->where('id', $id)->delete();
    }

    public function borrarRol(int $id): void
    {
        DB::table('roles')->where('id', $id)->delete();
    }

    private function consultaDePermisos(): Builder
    {
        return DB::table('oauth_scopes')->join('applications', 'applications.id', '=', 'oauth_scopes.application_id');
    }

    /** @return list<string> */
    private function columnasDePermiso(): array
    {
        return ['oauth_scopes.id', 'applications.name as aplicacion', 'oauth_scopes.name', 'oauth_scopes.description'];
    }

    private static function permisoDto(object $fila): PermisoParaEscribirDto
    {
        return new PermisoParaEscribirDto((int) $fila->id, $fila->aplicacion, $fila->name, (string) $fila->description);
    }
}
