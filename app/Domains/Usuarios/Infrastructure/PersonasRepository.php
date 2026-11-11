<?php

namespace App\Domains\Usuarios\Infrastructure;

use App\Domains\Usuarios\Domain\ClaveTemporal;
use App\Domains\Usuarios\Domain\CorreoEnUsoException;
use App\Domains\Usuarios\Domain\Dto\EstadoPersonaDto;
use App\Domains\Usuarios\Domain\Dto\NuevaPersonaDto;
use App\Domains\Usuarios\Domain\Dto\RolDeAplicacionDto;
use App\Domains\Usuarios\Domain\Interfaces\IPersonasRepository;
use App\Domains\Usuarios\Domain\ReglasDeRoles;
use App\Domains\Usuarios\Domain\RutEnUsoException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * IPersonasRepository against SQL Server. It writes only users, which API-IAM owns.
 */
final class PersonasRepository implements IPersonasRepository
{
    public function correoEnUso(string $correo): bool
    {
        return DB::table('users')->where('email', $correo)->exists();
    }

    public function rutEnUso(string $rut): bool
    {
        return DB::table('users')->where('rut', $rut)->exists();
    }

    public function crear(string $uuid, NuevaPersonaDto $persona, ?ClaveTemporal $clave): void
    {
        try {
            DB::table('users')->insert([
                'uuid' => $uuid,
                'name' => $persona->nombre,
                'email' => $persona->correo,
                'rut' => $persona->rut,
                'created_at' => now(),
                'updated_at' => now(),
                ...($clave?->columnas() ?? []),
            ]);
        } catch (UniqueConstraintViolationException $violacion) {
            throw self::traducir($violacion);
        }
    }

    public function estado(string $uuid): ?EstadoPersonaDto
    {
        $fila = DB::table('users')->where('uuid', $uuid)->first(['id', 'disabled_at']);

        return $fila === null ? null : new EstadoPersonaDto((int) $fila->id, $fila->disabled_at === null);
    }

    public function rolesDeIamAdmin(int $personaId): array
    {
        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->join('applications', 'applications.id', '=', 'roles.application_id')
            ->where('role_user.user_id', $personaId)
            ->where('applications.name', ReglasDeRoles::IAM_ADMIN)
            ->orderBy('roles.name')
            ->get(['roles.id', 'applications.name as aplicacion', 'roles.name'])
            ->map(fn (object $fila) => new RolDeAplicacionDto((int) $fila->id, $fila->aplicacion, $fila->name))
            ->all();
    }

    public function darDeBaja(int $personaId): bool
    {
        return DB::table('users')->where('id', $personaId)->whereNull('disabled_at')
            ->update(['disabled_at' => now(), 'updated_at' => now()]) > 0;
    }

    public function reactivar(int $personaId): bool
    {
        return DB::table('users')->where('id', $personaId)->whereNotNull('disabled_at')
            ->update(['disabled_at' => null, 'updated_at' => now()]) > 0;
    }

    public function cambiarClave(int $personaId, ClaveTemporal $clave): void
    {
        DB::table('users')->where('id', $personaId)->update([...$clave->columnas(), 'updated_at' => now()]);
    }

    /**
     * Two simultaneous requests can both pass the checks; the unique index (2601/2627) decides, and its
     * name says which field was repeated.
     */
    private static function traducir(UniqueConstraintViolationException $violacion): Throwable
    {
        $indice = strtolower((string) $violacion->index);

        return match (true) {
            str_contains($indice, 'email') => new CorreoEnUsoException,
            str_contains($indice, 'rut') => new RutEnUsoException,
            default => $violacion,
        };
    }
}
