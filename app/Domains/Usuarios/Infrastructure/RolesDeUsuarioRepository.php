<?php

namespace App\Domains\Usuarios\Infrastructure;

use App\Domains\Usuarios\Domain\Dto\RolDeAplicacionDto;
use App\Domains\Usuarios\Domain\Interfaces\IRolesDeUsuarioRepository;
use Illuminate\Support\Facades\DB;

/**
 * IRolesDeUsuarioRepository against SQL Server. It writes only role_user, which API-IAM owns.
 */
final class RolesDeUsuarioRepository implements IRolesDeUsuarioRepository
{
    public function idDePersona(string $uuid): ?int
    {
        $id = DB::table('users')->where('uuid', $uuid)->value('id');

        return $id === null ? null : (int) $id;
    }

    public function activa(int $personaId): bool
    {
        return DB::table('users')->where('id', $personaId)->whereNull('disabled_at')->exists();
    }

    public function rol(string $aplicacion, string $nombre): ?RolDeAplicacionDto
    {
        $fila = DB::table('roles')
            ->join('applications', 'applications.id', '=', 'roles.application_id')
            ->where('applications.name', $aplicacion)
            ->where('roles.name', $nombre)
            ->first(['roles.id', 'applications.name as aplicacion', 'roles.name']);

        return $fila === null ? null : new RolDeAplicacionDto((int) $fila->id, $fila->aplicacion, $fila->name);
    }

    public function tiene(int $personaId, int $rolId): bool
    {
        return DB::table('role_user')->where('user_id', $personaId)->where('role_id', $rolId)->exists();
    }

    public function asignar(int $personaId, int $rolId): void
    {
        DB::table('role_user')->insert(['user_id' => $personaId, 'role_id' => $rolId]);
    }

    public function quitar(int $personaId, int $rolId): void
    {
        DB::table('role_user')->where('user_id', $personaId)->where('role_id', $rolId)->delete();
    }

    public function permisosDeAplicacion(string $aplicacion): array
    {
        return DB::table('oauth_scopes')
            ->join('applications', 'applications.id', '=', 'oauth_scopes.application_id')
            ->where('applications.name', $aplicacion)
            ->orderBy('oauth_scopes.name')
            ->pluck('oauth_scopes.name')
            ->all();
    }

    public function otrosActivosConRol(int $rolId, int $personaId): int
    {
        return DB::table('role_user')
            ->join('users', 'users.id', '=', 'role_user.user_id')
            ->where('role_user.role_id', $rolId)
            ->where('role_user.user_id', '<>', $personaId)
            ->whereNull('users.disabled_at')
            ->count();
    }
}
