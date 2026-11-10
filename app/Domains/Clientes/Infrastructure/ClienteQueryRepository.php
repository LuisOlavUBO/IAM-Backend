<?php

namespace App\Domains\Clientes\Infrastructure;

use App\Domains\Clientes\Domain\Dto\ClienteDetalleDto;
use App\Domains\Clientes\Domain\Dto\ClienteResumenDto;
use App\Domains\Clientes\Domain\Dto\FiltroClientesDto;
use App\Domains\Clientes\Domain\Dto\RolClienteDto;
use App\Domains\Clientes\Domain\Interfaces\IClienteQueryRepository;
use App\Shared\Paginacion\Pagina;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * IClienteQueryRepository against SQL Server with the query builder (design D3, D4, D5). Only the columns
 * of the response are selected: the secret's hash never leaves the query (confidencial is computed in
 * SQL), and the numeric ids are used only inside the joins.
 */
final class ClienteQueryRepository implements IClienteQueryRepository
{
    public function listar(FiltroClientesDto $filtro): Pagina
    {
        $consulta = DB::table('oauth_clients')
            ->join('applications', 'applications.id', '=', 'oauth_clients.application_id')
            ->when($filtro->buscar !== '', fn (Builder $q) => $this->buscar($q, $filtro->buscar));

        $total = (clone $consulta)->count();

        $items = $consulta
            ->orderBy('oauth_clients.name')
            ->orderBy('oauth_clients.id')
            ->forPage($filtro->pagina, $filtro->porPagina)
            ->get([
                'oauth_clients.id', 'oauth_clients.name', 'applications.name as aplicacion', 'oauth_clients.revoked',
                DB::raw('(SELECT COUNT(*) FROM oauth_client_role WHERE oauth_client_role.client_id = oauth_clients.id) AS roles'),
            ])
            ->map(fn (object $fila) => new ClienteResumenDto(
                Str::lower($fila->id),
                $fila->name,
                $fila->aplicacion,
                (bool) $fila->revoked,
                (int) $fila->roles,
            ))
            ->all();

        return new Pagina($items, $total, $filtro->pagina, $filtro->porPagina);
    }

    public function detalle(string $id): ?ClienteDetalleDto
    {
        $fila = DB::table('oauth_clients')
            ->join('applications', 'applications.id', '=', 'oauth_clients.application_id')
            ->where('oauth_clients.id', $id)
            ->first([
                'oauth_clients.id', 'oauth_clients.name', 'applications.name as aplicacion',
                'oauth_clients.grant_types', 'oauth_clients.redirect_uris', 'oauth_clients.revoked',
                'oauth_clients.created_at', 'oauth_clients.updated_at',
                DB::raw('CASE WHEN oauth_clients.secret IS NULL THEN 0 ELSE 1 END AS confidencial'),
            ]);

        if ($fila === null) {
            return null;
        }

        return new ClienteDetalleDto(
            Str::lower($fila->id),
            $fila->name,
            $fila->aplicacion,
            self::lista($fila->grant_types),
            (bool) $fila->confidencial,
            self::lista($fila->redirect_uris),
            (bool) $fila->revoked,
            self::fecha($fila->created_at),
            self::fecha($fila->updated_at),
            $this->roles($fila->id),
        );
    }

    /**
     * The client's roles with the application each one belongs to, ordered by application and name.
     *
     * @return list<RolClienteDto>
     */
    private function roles(string $clienteId): array
    {
        return DB::table('oauth_client_role')
            ->join('roles', 'roles.id', '=', 'oauth_client_role.role_id')
            ->join('applications', 'applications.id', '=', 'roles.application_id')
            ->where('oauth_client_role.client_id', $clienteId)
            ->orderBy('applications.name')
            ->orderBy('roles.name')
            ->get(['applications.name as aplicacion', 'roles.name', 'roles.description'])
            ->map(fn (object $fila) => new RolClienteDto($fila->aplicacion, $fila->name, $fila->description))
            ->all();
    }

    /**
     * Client name or application name containing the text (the CI collation ignores case), or the exact id
     * when the text is a UUID. LIKE wildcards of SQL Server are escaped.
     */
    private function buscar(Builder $consulta, string $texto): void
    {
        $patron = '%'.str_replace(['!', '%', '_', '['], ['!!', '!%', '!_', '!['], $texto).'%';

        $consulta->where(function (Builder $condicion) use ($patron, $texto) {
            $condicion->whereRaw("oauth_clients.name LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("applications.name LIKE ? ESCAPE '!'", [$patron]);

            if (Str::isUuid($texto)) {
                $condicion->orWhere('oauth_clients.id', $texto);
            }
        });
    }

    /**
     * A JSON list of strings (grant_types, redirect_uris). Anything else counts as an empty list (design D4).
     *
     * @return list<string>
     */
    private static function lista(?string $json): array
    {
        $valor = json_decode((string) $json, true);

        return is_array($valor) && array_is_list($valor)
            ? array_values(array_filter($valor, 'is_string'))
            : [];
    }

    /** SQL Server datetime columns carry no zone: they are read in the application's zone. */
    private static function fecha(?string $valor): ?CarbonImmutable
    {
        return $valor === null ? null : CarbonImmutable::parse($valor);
    }
}
