<?php

namespace App\Domains\Indicadores\Infrastructure;

use App\Domains\Indicadores\Domain\Dto\AuditoriaDto;
use App\Domains\Indicadores\Domain\Dto\ClientesDto;
use App\Domains\Indicadores\Domain\Dto\ClienteSenaladoDto;
use App\Domains\Indicadores\Domain\Dto\EntornoDto;
use App\Domains\Indicadores\Domain\Dto\IngresosDelDiaDto;
use App\Domains\Indicadores\Domain\Dto\PersonaConVariasIpsDto;
use App\Domains\Indicadores\Domain\Dto\PersonaInactivaDto;
use App\Domains\Indicadores\Domain\Dto\PersonasDto;
use App\Domains\Indicadores\Domain\Dto\PrivilegiosDto;
use App\Domains\Indicadores\Domain\Dto\RolConPersonasDto;
use App\Domains\Indicadores\Domain\Dto\SenalesDto;
use App\Domains\Indicadores\Domain\Dto\TokensDeClienteDto;
use App\Domains\Indicadores\Domain\Interfaces\IIndicadoresQueryRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * IIndicadoresQueryRepository against SQL Server with the query builder (design D1, D2, D5). Only
 * aggregates and the public columns leave the database: no numeric ids, passwords, secrets or tokens.
 *
 * oauth_access_tokens and sessions belong to API-Auth and are only read here. oauth_access_tokens has
 * an index only on user_id and no purge: the aggregates by client or date go through the table, which
 * is why VerIndicadores caches every block (design D4).
 */
final class IndicadoresQueryRepository implements IIndicadoresQueryRepository
{
    /** Sessions of the central login with activity in this window count as open. */
    private const HORAS_SESION_ABIERTA = 2;

    private const DIAS_INGRESOS = 30;

    private const SERVICIO = '%"client[_]credentials"%';

    private const LOGIN = '%"authorization[_]code"%';

    public function entorno(): EntornoDto
    {
        $conteos = DB::selectOne(<<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM applications) AS aplicaciones,
                (SELECT COUNT(*) FROM roles) AS roles,
                (SELECT COUNT(*) FROM oauth_scopes) AS permisos,
                (SELECT COUNT(*) FROM roles
                    WHERE NOT EXISTS (SELECT 1 FROM role_user WHERE role_user.role_id = roles.id)
                    AND NOT EXISTS (SELECT 1 FROM oauth_client_role WHERE oauth_client_role.role_id = roles.id)
                ) AS roles_sin_asignar
            SQL);

        $masPersonas = DB::table('roles')
            ->join('applications', 'applications.id', '=', 'roles.application_id')
            ->join('role_user', 'role_user.role_id', '=', 'roles.id')
            ->join('users', 'users.id', '=', 'role_user.user_id')
            ->whereNull('users.disabled_at')
            ->groupBy('applications.name', 'roles.name')
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('applications.name')
            ->orderBy('roles.name')
            ->limit(5)
            ->get(['applications.name as aplicacion', 'roles.name as rol', DB::raw('COUNT(*) AS personas')])
            ->map(fn (object $fila) => new RolConPersonasDto($fila->aplicacion, $fila->rol, (int) $fila->personas))
            ->all();

        return new EntornoDto(
            (int) $conteos->aplicaciones,
            (int) $conteos->roles,
            (int) $conteos->permisos,
            (int) $conteos->roles_sin_asignar,
            $masPersonas,
        );
    }

    public function personas(): PersonasDto
    {
        $ahora = CarbonImmutable::now();

        $conteos = DB::table('users')
            ->leftJoinSub(self::actividad(), 'actividad', 'actividad.user_id', '=', 'users.id')
            ->selectRaw(<<<'SQL'
                SUM(CASE WHEN users.disabled_at IS NULL THEN 1 ELSE 0 END) AS activas,
                SUM(CASE WHEN users.disabled_at IS NOT NULL THEN 1 ELSE 0 END) AS de_baja,
                SUM(CASE WHEN users.disabled_at IS NULL AND actividad.ultima < ? THEN 1 ELSE 0 END) AS inactivas_30,
                SUM(CASE WHEN users.disabled_at IS NULL AND actividad.ultima < ? THEN 1 ELSE 0 END) AS inactivas_90,
                SUM(CASE WHEN users.disabled_at IS NULL AND actividad.ultima IS NULL THEN 1 ELSE 0 END) AS sin_actividad,
                SUM(CASE WHEN users.disabled_at IS NULL AND users.temporary_password_expires_at < ? THEN 1 ELSE 0 END) AS claves_temporales_vencidas,
                SUM(CASE WHEN users.disabled_at IS NULL AND users.password IS NOT NULL AND users.entra_oid IS NULL THEN 1 ELSE 0 END) AS solo_clave
                SQL, [$ahora->subDays(30), $ahora->subDays(90), $ahora])
            ->first();

        $sesionesAbiertas = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $ahora->subHours(self::HORAS_SESION_ABIERTA)->getTimestamp())
            ->distinct()
            ->count('user_id');

        return new PersonasDto(
            (int) $conteos->activas,
            (int) $conteos->de_baja,
            (int) $conteos->inactivas_30,
            (int) $conteos->inactivas_90,
            (int) $conteos->sin_actividad,
            $this->ingresosPorDia($ahora),
            $sesionesAbiertas,
            (int) $conteos->claves_temporales_vencidas,
            (int) $conteos->solo_clave,
        );
    }

    public function privilegios(): PrivilegiosDto
    {
        $limite = CarbonImmutable::now()->subDays(30);

        $administradores = DB::table('users')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->join('applications', 'applications.id', '=', 'roles.application_id')
            ->leftJoinSub(self::actividad(), 'actividad', 'actividad.user_id', '=', 'users.id')
            ->where('applications.name', 'iam-admin')
            ->where('roles.name', 'administrador')
            ->whereNull('users.disabled_at')
            ->orderBy('users.name')
            ->orderBy('users.uuid')
            ->get(['users.uuid', 'users.name', 'actividad.ultima'])
            ->map(fn (object $fila) => new PersonaInactivaDto(Str::lower($fila->uuid), $fila->name, self::fecha($fila->ultima)));

        return new PrivilegiosDto(
            $administradores->count(),
            $administradores
                ->filter(fn (PersonaInactivaDto $admin) => $admin->ultimaActividad === null || $admin->ultimaActividad->lessThan($limite))
                ->values()
                ->all(),
        );
    }

    public function clientes(): ClientesDto
    {
        $ahora = CarbonImmutable::now();

        $conteos = DB::table('oauth_clients')
            ->selectRaw(<<<'SQL'
                SUM(CASE WHEN revoked = 0 AND grant_types LIKE ? THEN 1 ELSE 0 END) AS login,
                SUM(CASE WHEN revoked = 0 AND grant_types LIKE ? THEN 1 ELSE 0 END) AS servicio,
                SUM(CASE WHEN revoked = 1 THEN 1 ELSE 0 END) AS revocados
                SQL, [self::LOGIN, self::SERVICIO])
            ->first();

        $tokensPorCliente = DB::table('oauth_access_tokens')
            ->whereNull('user_id')
            ->where('created_at', '>=', $ahora->subDays(7))
            ->groupBy('client_id')
            ->select('client_id')
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS tokens_24h', [$ahora->subDay()])
            ->selectRaw('COUNT(*) AS tokens_7d');

        $porCliente = DB::table('oauth_clients')
            ->joinSub($tokensPorCliente, 'tokens', 'tokens.client_id', '=', 'oauth_clients.id')
            ->orderByDesc('tokens.tokens_7d')
            ->orderBy('oauth_clients.name')
            ->get(['oauth_clients.id', 'oauth_clients.name', 'tokens.tokens_24h', 'tokens.tokens_7d'])
            ->map(fn (object $fila) => new TokensDeClienteDto(Str::lower($fila->id), $fila->name, (int) $fila->tokens_24h, (int) $fila->tokens_7d));

        return new ClientesDto(
            (int) $conteos->login,
            (int) $conteos->servicio,
            (int) $conteos->revocados,
            $porCliente->sum(fn (TokensDeClienteDto $cliente) => $cliente->tokens24h),
            $porCliente->sum(fn (TokensDeClienteDto $cliente) => $cliente->tokens7d),
            $porCliente->take(10)->values()->all(),
            $this->sinUso($ahora->subDays(30)),
            $this->sinRoles(),
            $this->secretosSinRotar($ahora->subDays(180)),
        );
    }

    public function auditoria(): AuditoriaDto
    {
        $ahora = CarbonImmutable::now();

        $rechazos = DB::table('iam_auditoria')
            ->where('resultado', 'rechazado')
            ->whereNotNull('motivo')
            ->where('ocurrido_en', '>=', $ahora->subDays(30))
            ->groupBy('motivo')
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('motivo')
            ->get(['motivo', DB::raw('COUNT(*) AS total')])
            ->mapWithKeys(fn (object $fila) => [$fila->motivo => (int) $fila->total])
            ->all();

        $cambios = DB::table('iam_auditoria')
            ->where('resultado', 'ok')
            ->where('ocurrido_en', '>=', $ahora->subDays(7))
            ->count();

        return new AuditoriaDto($rechazos, $cambios);
    }

    public function senales(): SenalesDto
    {
        $desde = CarbonImmutable::now()->subHours(self::HORAS_SESION_ABIERTA)->getTimestamp();

        $personas = DB::table('sessions')
            ->join('users', 'users.id', '=', 'sessions.user_id')
            ->where('sessions.last_activity', '>=', $desde)
            ->whereNotNull('sessions.ip_address')
            ->groupBy('users.uuid', 'users.name')
            ->havingRaw('COUNT(DISTINCT sessions.ip_address) > 1')
            ->orderBy('users.name')
            ->orderBy('users.uuid')
            ->get(['users.uuid', 'users.name', DB::raw('COUNT(DISTINCT sessions.ip_address) AS ips')])
            ->map(fn (object $fila) => new PersonaConVariasIpsDto(Str::lower($fila->uuid), $fila->name, (int) $fila->ips))
            ->all();

        return new SenalesDto($personas);
    }

    /**
     * The last activity of each person (design D2): the newest access token API-Auth issued to them.
     * The same subquery filters ListarUsuarios by inactivity (design D6), so both numbers match.
     */
    private static function actividad(): Builder
    {
        return DB::table('oauth_access_tokens')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('MAX(created_at) AS ultima'));
    }

    /**
     * Different people with a token on each of the last 30 days, oldest first, with zeros.
     *
     * @return list<IngresosDelDiaDto>
     */
    private function ingresosPorDia(CarbonImmutable $ahora): array
    {
        $desde = $ahora->startOfDay()->subDays(self::DIAS_INGRESOS - 1);

        $porDia = DB::table('oauth_access_tokens')
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $desde)
            ->groupByRaw('CAST(created_at AS date)')
            ->get([DB::raw('CAST(created_at AS date) AS dia'), DB::raw('COUNT(DISTINCT user_id) AS personas')])
            ->mapWithKeys(fn (object $fila) => [substr((string) $fila->dia, 0, 10) => (int) $fila->personas]);

        $dias = [];
        for ($i = 0; $i < self::DIAS_INGRESOS; $i++) {
            $fecha = $desde->addDays($i)->format('Y-m-d');
            $dias[] = new IngresosDelDiaDto($fecha, $porDia->get($fecha, 0));
        }

        return $dias;
    }

    /**
     * Active service clients, older than the limit, without service tokens since then.
     *
     * @return list<ClienteSenaladoDto>
     */
    private function sinUso(CarbonImmutable $limite): array
    {
        $uso = DB::table('oauth_access_tokens')
            ->whereNull('user_id')
            ->groupBy('client_id')
            ->select('client_id', DB::raw('MAX(created_at) AS ultimo'));

        return self::serviciosActivos()
            ->leftJoinSub($uso, 'uso', 'uso.client_id', '=', 'oauth_clients.id')
            ->where('oauth_clients.created_at', '<', $limite)
            ->where(fn (Builder $q) => $q->whereNull('uso.ultimo')->orWhere('uso.ultimo', '<', $limite))
            ->orderBy('oauth_clients.name')
            ->orderBy('oauth_clients.id')
            ->get(['oauth_clients.id', 'oauth_clients.name', 'uso.ultimo as fecha'])
            ->map(fn (object $fila) => new ClienteSenaladoDto(Str::lower($fila->id), $fila->name, self::fecha($fila->fecha)))
            ->all();
    }

    /**
     * @return list<ClienteSenaladoDto>
     */
    private function sinRoles(): array
    {
        return self::serviciosActivos()
            ->whereNotExists(fn (Builder $q) => $q->from('oauth_client_role')->whereColumn('oauth_client_role.client_id', 'oauth_clients.id'))
            ->orderBy('oauth_clients.name')
            ->orderBy('oauth_clients.id')
            ->get(['oauth_clients.id', 'oauth_clients.name'])
            ->map(fn (object $fila) => new ClienteSenaladoDto(Str::lower($fila->id), $fila->name, null))
            ->all();
    }

    /**
     * Active confidential clients whose secret is older than the limit (design D5): the date of the
     * last cliente.secreto.rotacion in the audit log, or the creation when it was never rotated.
     *
     * @return list<ClienteSenaladoDto>
     */
    private function secretosSinRotar(CarbonImmutable $limite): array
    {
        $rotaciones = DB::table('iam_auditoria')
            ->where('accion', 'cliente.secreto.rotacion')
            ->where('entidad_tipo', 'cliente')
            ->where('resultado', 'ok')
            ->groupBy('entidad_id')
            ->select('entidad_id', DB::raw('MAX(ocurrido_en) AS rotado'));

        return DB::table('oauth_clients')
            ->leftJoinSub($rotaciones, 'rotacion', fn (JoinClause $join) => $join->on(
                DB::raw('CAST(oauth_clients.id AS nvarchar(36))'), '=', 'rotacion.entidad_id',
            ))
            ->where('oauth_clients.revoked', false)
            ->whereNotNull('oauth_clients.secret')
            ->whereRaw('COALESCE(rotacion.rotado, oauth_clients.created_at) < ?', [$limite])
            ->orderBy('oauth_clients.name')
            ->orderBy('oauth_clients.id')
            ->get(['oauth_clients.id', 'oauth_clients.name', DB::raw('COALESCE(rotacion.rotado, oauth_clients.created_at) AS fecha')])
            ->map(fn (object $fila) => new ClienteSenaladoDto(Str::lower($fila->id), $fila->name, self::fecha($fila->fecha)))
            ->all();
    }

    private static function serviciosActivos(): Builder
    {
        return DB::table('oauth_clients')
            ->where('oauth_clients.revoked', false)
            ->where('oauth_clients.grant_types', 'like', self::SERVICIO);
    }

    /** SQL Server datetime columns carry no zone: they are read in the application's zone. */
    private static function fecha(?string $valor): ?CarbonImmutable
    {
        return $valor === null ? null : CarbonImmutable::parse($valor);
    }
}
