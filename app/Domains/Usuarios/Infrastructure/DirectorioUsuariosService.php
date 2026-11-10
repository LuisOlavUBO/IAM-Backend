<?php

namespace App\Domains\Usuarios\Infrastructure;

use App\Domains\Usuarios\Contracts\IDirectorioUsuariosService;
use App\Domains\Usuarios\Contracts\PersonaBasicaDto;
use Illuminate\Support\Facades\DB;

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
}
