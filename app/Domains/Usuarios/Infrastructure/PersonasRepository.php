<?php

namespace App\Domains\Usuarios\Infrastructure;

use App\Domains\Usuarios\Domain\ClaveTemporal;
use App\Domains\Usuarios\Domain\CorreoEnUsoException;
use App\Domains\Usuarios\Domain\Dto\NuevaPersonaDto;
use App\Domains\Usuarios\Domain\Interfaces\IPersonasRepository;
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
