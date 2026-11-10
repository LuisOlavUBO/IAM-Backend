<?php

namespace App\Domains\Usuarios\Infrastructure;

use App\Domains\Usuarios\Contracts\IDirectorioUsuariosService;
use App\Domains\Usuarios\Contracts\PersonaBasicaDto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * IDirectorioUsuariosService against SQL Server: only the name and email of users.
 */
final class DirectorioUsuariosService implements IDirectorioUsuariosService
{
    public function persona(string $uuid): ?PersonaBasicaDto
    {
        $fila = DB::table('users')->where('uuid', $uuid)->first(['name', 'email']);

        return $fila === null ? null : new PersonaBasicaDto($fila->name, $fila->email);
    }

    public function personas(array $uuids): array
    {
        if ($uuids === []) {
            return [];
        }

        $personas = [];
        foreach (DB::table('users')->whereIn('uuid', array_values(array_unique($uuids)))->get(['uuid', 'name', 'email']) as $fila) {
            // SQL Server returns uniqueidentifier in upper case.
            $personas[Str::lower($fila->uuid)] = new PersonaBasicaDto($fila->name, $fila->email);
        }

        return $personas;
    }
}
