<?php

namespace App\Domains\Clientes\Infrastructure;

use App\Domains\Clientes\Contracts\IDirectorioClientesService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * IDirectorioClientesService against SQL Server: one query, none for an empty list. Never reads the secret.
 */
final class DirectorioClientesService implements IDirectorioClientesService
{
    public function clientes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $nombres = [];
        foreach (DB::table('oauth_clients')->whereIn('id', array_values(array_unique($ids)))->get(['id', 'name']) as $fila) {
            // SQL Server returns uniqueidentifier in upper case.
            $nombres[Str::lower($fila->id)] = $fila->name;
        }

        return $nombres;
    }
}
