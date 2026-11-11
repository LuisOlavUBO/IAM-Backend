<?php

namespace App\Domains\Aplicaciones\Infrastructure;

use App\Domains\Aplicaciones\Contracts\IDirectorioAplicacionesService;
use Illuminate\Support\Facades\DB;

/**
 * IDirectorioAplicacionesService against SQL Server: one query per call, none for an empty list.
 */
final class DirectorioAplicacionesService implements IDirectorioAplicacionesService
{
    public function aplicaciones(array $ids): array
    {
        return $ids === [] ? [] : self::mapa(DB::table('applications')->whereIn('id', self::unicos($ids))->get(['id', 'name']));
    }

    public function roles(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $filas = DB::table('roles')
            ->join('applications', 'applications.id', '=', 'roles.application_id')
            ->whereIn('roles.id', self::unicos($ids))
            ->get(['roles.id', DB::raw("applications.name + '/' + roles.name AS name")]);

        return self::mapa($filas);
    }

    public function permisos(array $ids): array
    {
        return $ids === [] ? [] : self::mapa(DB::table('oauth_scopes')->whereIn('id', self::unicos($ids))->get(['id', 'name']));
    }

    public function idDeAplicacion(string $nombre): ?int
    {
        $id = DB::table('applications')->where('name', $nombre)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private static function unicos(array $ids): array
    {
        return array_values(array_unique($ids));
    }

    /**
     * @param  iterable<object{id: int|string, name: string}>  $filas
     * @return array<int, string>
     */
    private static function mapa(iterable $filas): array
    {
        $nombres = [];
        foreach ($filas as $fila) {
            $nombres[(int) $fila->id] = $fila->name;
        }

        return $nombres;
    }
}
