<?php

namespace App\Domains\Aplicaciones\Infrastructure;

use App\Domains\Aplicaciones\Domain\Dto\AplicacionDetalleDto;
use App\Domains\Aplicaciones\Domain\Dto\AplicacionResumenDto;
use App\Domains\Aplicaciones\Domain\Dto\FiltroAplicacionesDto;
use App\Domains\Aplicaciones\Domain\Dto\PermisoDto;
use App\Domains\Aplicaciones\Domain\Dto\RolDto;
use App\Domains\Aplicaciones\Domain\Interfaces\IAplicacionQueryRepository;
use App\Shared\Paginacion\Pagina;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * IAplicacionQueryRepository against SQL Server with the query builder (design D3, D4). The numeric ids
 * are used only inside the queries and never leave this class.
 */
final class AplicacionQueryRepository implements IAplicacionQueryRepository
{
    public function listar(FiltroAplicacionesDto $filtro): Pagina
    {
        $consulta = DB::table('applications')
            ->when($filtro->buscar !== '', fn (Builder $q) => $this->buscar($q, $filtro->buscar));

        $total = (clone $consulta)->count();

        $items = $consulta
            ->orderBy('name')
            ->forPage($filtro->pagina, $filtro->porPagina)
            ->get([
                'name', 'description', 'disabled_at',
                DB::raw('(SELECT COUNT(*) FROM oauth_scopes WHERE oauth_scopes.application_id = applications.id) AS permisos'),
                DB::raw('(SELECT COUNT(*) FROM roles WHERE roles.application_id = applications.id) AS roles'),
            ])
            ->map(fn (object $fila) => new AplicacionResumenDto(
                $fila->name,
                $fila->description,
                (int) $fila->permisos,
                (int) $fila->roles,
                self::fecha($fila->disabled_at),
            ))
            ->all();

        return new Pagina($items, $total, $filtro->pagina, $filtro->porPagina);
    }

    public function detalle(string $nombre): ?AplicacionDetalleDto
    {
        $fila = DB::table('applications')
            ->where('name', $nombre)
            ->first(['id', 'name', 'description', 'created_at', 'updated_at', 'disabled_at']);

        if ($fila === null) {
            return null;
        }

        return new AplicacionDetalleDto(
            $fila->name,
            $fila->description,
            self::fecha($fila->created_at),
            self::fecha($fila->updated_at),
            $this->permisos((int) $fila->id),
            $this->roles((int) $fila->id),
            self::fecha($fila->disabled_at),
        );
    }

    /**
     * @return list<PermisoDto>
     */
    private function permisos(int $aplicacionId): array
    {
        return DB::table('oauth_scopes')
            ->where('application_id', $aplicacionId)
            ->orderBy('name')
            ->get(['name', 'description'])
            ->map(fn (object $fila) => new PermisoDto($fila->name, $fila->description))
            ->all();
    }

    /**
     * The roles with the permissions they grant, in one query grouped here.
     *
     * @return list<RolDto>
     */
    private function roles(int $aplicacionId): array
    {
        $filas = DB::table('roles')
            ->leftJoin('oauth_scope_role', 'oauth_scope_role.role_id', '=', 'roles.id')
            ->leftJoin('oauth_scopes', 'oauth_scopes.id', '=', 'oauth_scope_role.oauth_scope_id')
            ->where('roles.application_id', $aplicacionId)
            ->orderBy('roles.name')
            ->orderBy('oauth_scopes.name')
            ->get(['roles.name as rol', 'roles.description as descripcion', 'roles.disabled_at as baja', 'oauth_scopes.name as permiso']);

        /** @var array<string, RolDto> $roles */
        $roles = [];
        foreach ($filas as $fila) {
            $anterior = $roles[$fila->rol] ?? new RolDto($fila->rol, $fila->descripcion, [], self::fecha($fila->baja));
            $roles[$fila->rol] = $fila->permiso === null
                ? $anterior
                : new RolDto($anterior->nombre, $anterior->descripcion, [...$anterior->permisos, $fila->permiso], $anterior->bajaDesde);
        }

        return array_values($roles);
    }

    /**
     * Name or description containing the text (the CI collation ignores case). LIKE wildcards of SQL
     * Server are escaped.
     */
    private function buscar(Builder $consulta, string $texto): void
    {
        $patron = '%'.str_replace(['!', '%', '_', '['], ['!!', '!%', '!_', '!['], $texto).'%';

        $consulta->where(function (Builder $condicion) use ($patron) {
            $condicion->whereRaw("name LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("description LIKE ? ESCAPE '!'", [$patron]);
        });
    }

    /** SQL Server datetime columns carry no zone: they are read in the application's zone. */
    private static function fecha(?string $valor): ?CarbonImmutable
    {
        return $valor === null ? null : CarbonImmutable::parse($valor);
    }
}
