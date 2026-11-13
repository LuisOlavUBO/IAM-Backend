<?php

namespace App\Domains\Usuarios\Infrastructure;

use App\Domains\Usuarios\Domain\Dto\FiltroUsuariosDto;
use App\Domains\Usuarios\Domain\Dto\RolAsignadoDto;
use App\Domains\Usuarios\Domain\Dto\UsuarioDetalleDto;
use App\Domains\Usuarios\Domain\Dto\UsuarioResumenDto;
use App\Domains\Usuarios\Domain\Interfaces\IUsuarioQueryRepository;
use App\Shared\Paginacion\Pagina;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * IUsuarioQueryRepository against SQL Server with the query builder. Selects only the columns of the
 * responses: password, remember_token and entra_oid never leave the database (the detail turns two
 * of them into booleans in SQL).
 */
final class UsuarioQueryRepository implements IUsuarioQueryRepository
{
    public function listar(FiltroUsuariosDto $filtro): Pagina
    {
        $consulta = DB::table('users')
            ->when($filtro->estado === 'activos', fn (Builder $q) => $q->whereNull('disabled_at'))
            ->when($filtro->estado === 'baja', fn (Builder $q) => $q->whereNotNull('disabled_at'))
            ->when($filtro->buscar !== '', fn (Builder $q) => $this->buscar($q, $filtro))
            ->when($filtro->inactivas !== null, fn (Builder $q) => $this->inactivas($q, $filtro->inactivas));

        $total = (clone $consulta)->count();

        $items = $consulta
            ->orderBy('name')
            ->orderBy('uuid')
            ->forPage($filtro->pagina, $filtro->porPagina)
            ->get(['uuid', 'name', 'email', 'rut', 'disabled_at'])
            ->map(fn (object $fila) => new UsuarioResumenDto(
                Str::lower($fila->uuid),
                $fila->name,
                $fila->email,
                $fila->rut,
                $fila->disabled_at !== null,
            ))
            ->all();

        return new Pagina($items, $total, $filtro->pagina, $filtro->porPagina);
    }

    public function detalle(string $uuid): ?UsuarioDetalleDto
    {
        $fila = DB::table('users')
            ->where('uuid', $uuid)
            ->first([
                'id', 'uuid', 'name', 'email', 'rut', 'disabled_at', 'temporary_password_expires_at',
                'created_at', 'updated_at',
                DB::raw('CASE WHEN password IS NULL THEN 0 ELSE 1 END AS tiene_clave'),
                DB::raw('CASE WHEN entra_oid IS NULL THEN 0 ELSE 1 END AS microsoft_vinculado'),
            ]);

        if ($fila === null) {
            return null;
        }

        return new UsuarioDetalleDto(
            Str::lower($fila->uuid),
            $fila->name,
            $fila->email,
            $fila->rut,
            self::fecha($fila->disabled_at),
            (bool) $fila->tiene_clave,
            (bool) $fila->microsoft_vinculado,
            self::fecha($fila->temporary_password_expires_at),
            self::fecha($fila->created_at),
            self::fecha($fila->updated_at),
            $this->roles((int) $fila->id),
        );
    }

    /**
     * @return list<RolAsignadoDto>
     */
    private function roles(int $userId): array
    {
        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->join('applications', 'applications.id', '=', 'roles.application_id')
            ->where('role_user.user_id', $userId)
            ->orderBy('applications.name')
            ->orderBy('roles.name')
            ->get([
                'applications.name as aplicacion', 'applications.description as aplicacion_descripcion',
                'roles.name as rol', 'roles.description as rol_descripcion',
            ])
            ->map(fn (object $fila) => new RolAsignadoDto(
                $fila->aplicacion,
                $fila->aplicacion_descripcion,
                $fila->rol,
                $fila->rol_descripcion,
            ))
            ->all();
    }

    /**
     * Name or email containing the text (the CI collation ignores case), or the exact RUT the handler
     * found in it. LIKE wildcards of SQL Server are escaped.
     */
    private function buscar(Builder $consulta, FiltroUsuariosDto $filtro): void
    {
        $patron = '%'.str_replace(['!', '%', '_', '['], ['!!', '!%', '!_', '!['], $filtro->buscar).'%';

        $consulta->where(function (Builder $condicion) use ($patron, $filtro) {
            $condicion->whereRaw("name LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("email LIKE ? ESCAPE '!'", [$patron])
                ->when($filtro->rut !== null, fn (Builder $q) => $q->orWhere('rut', $filtro->rut));
        });
    }

    /**
     * Without activity for more than 30 or 90 days, or never. The activity is the newest access token
     * API-Auth issued to the person (oauth_access_tokens, read only), with the same subquery as the
     * Indicadores domain (design D2 and D6 of indicadores-portal), so the list and the indicator match.
     *
     * @param  '30'|'90'|'nunca'  $inactivas
     */
    private function inactivas(Builder $consulta, string $inactivas): void
    {
        $actividad = DB::table('oauth_access_tokens')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('MAX(created_at) AS ultima'));

        $consulta->leftJoinSub($actividad, 'actividad', 'actividad.user_id', '=', 'users.id');

        if ($inactivas === 'nunca') {
            $consulta->whereNull('actividad.ultima');
        } else {
            $consulta->where('actividad.ultima', '<', CarbonImmutable::now()->subDays((int) $inactivas));
        }
    }

    /** SQL Server datetime columns carry no zone: they are read in the application's zone. */
    private static function fecha(?string $valor): ?CarbonImmutable
    {
        return $valor === null ? null : CarbonImmutable::parse($valor);
    }
}
