<?php

namespace App\Domains\Indicadores\Features\VerIndicadores;

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
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Response of GET /api/iam/v1/indicadores (Indicadores in OpenAPI): `data` has only the blocks that
 * were computed, and is an object even when empty.
 *
 * @property IndicadoresCalculadosDto $resource
 */
final class VerIndicadoresResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{data: object, meta: array{calculado_en: string}}
     */
    public function toArray(Request $request): array
    {
        $data = [];
        foreach ($this->resource->bloques as $bloque => $datos) {
            $data[$bloque] = match (true) {
                $datos instanceof EntornoDto => self::entorno($datos),
                $datos instanceof PersonasDto => self::personas($datos),
                $datos instanceof PrivilegiosDto => self::privilegios($datos),
                $datos instanceof ClientesDto => self::clientes($datos),
                $datos instanceof AuditoriaDto => self::auditoria($datos),
                $datos instanceof SenalesDto => self::senales($datos),
            };
        }

        return [
            'data' => (object) $data,
            'meta' => ['calculado_en' => $this->resource->calculadoEn->toIso8601String()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function entorno(EntornoDto $e): array
    {
        return [
            'aplicaciones' => $e->aplicaciones,
            'roles' => $e->roles,
            'permisos' => $e->permisos,
            'roles_sin_asignar' => $e->rolesSinAsignar,
            'roles_con_mas_personas' => array_map(fn (RolConPersonasDto $r) => [
                'aplicacion' => $r->aplicacion,
                'rol' => $r->rol,
                'personas' => $r->personas,
            ], $e->rolesConMasPersonas),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function personas(PersonasDto $p): array
    {
        return [
            'activas' => $p->activas,
            'de_baja' => $p->deBaja,
            'inactivas_30' => $p->inactivas30,
            'inactivas_90' => $p->inactivas90,
            'sin_actividad' => $p->sinActividad,
            'ingresos_por_dia' => array_map(fn (IngresosDelDiaDto $d) => [
                'fecha' => $d->fecha,
                'personas' => $d->personas,
            ], $p->ingresosPorDia),
            'sesiones_abiertas' => $p->sesionesAbiertas,
            'claves_temporales_vencidas' => $p->clavesTemporalesVencidas,
            'solo_clave' => $p->soloClave,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function privilegios(PrivilegiosDto $p): array
    {
        return [
            'administradores' => $p->administradores,
            'administradores_inactivos' => array_map(fn (PersonaInactivaDto $a) => [
                'uuid' => $a->uuid,
                'nombre' => $a->nombre,
                'ultima_actividad' => $a->ultimaActividad?->toIso8601String(),
            ], $p->administradoresInactivos),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function clientes(ClientesDto $c): array
    {
        return [
            'login' => $c->login,
            'servicio' => $c->servicio,
            'revocados' => $c->revocados,
            'tokens_24h' => $c->tokens24h,
            'tokens_7d' => $c->tokens7d,
            'tokens_por_cliente' => array_map(fn (TokensDeClienteDto $t) => [
                'id' => $t->id,
                'nombre' => $t->nombre,
                'tokens_24h' => $t->tokens24h,
                'tokens_7d' => $t->tokens7d,
            ], $c->tokensPorCliente),
            'sin_uso_30' => self::senalados($c->sinUso30, 'ultimo_uso'),
            'sin_roles' => array_map(fn (ClienteSenaladoDto $s) => ['id' => $s->id, 'nombre' => $s->nombre], $c->sinRoles),
            'secretos_sin_rotar' => self::senalados($c->secretosSinRotar, 'secreto_desde'),
        ];
    }

    /**
     * @param  list<ClienteSenaladoDto>  $clientes
     * @return list<array<string, ?string>>
     */
    private static function senalados(array $clientes, string $campoFecha): array
    {
        return array_map(fn (ClienteSenaladoDto $s) => [
            'id' => $s->id,
            'nombre' => $s->nombre,
            $campoFecha => $s->fecha?->toIso8601String(),
        ], $clientes);
    }

    /**
     * @return array<string, mixed>
     */
    private static function auditoria(AuditoriaDto $a): array
    {
        return [
            'rechazos' => (object) $a->rechazos,
            'cambios_7d' => $a->cambios7d,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function senales(SenalesDto $s): array
    {
        return [
            'sesiones_desde_varias_ips' => array_map(fn (PersonaConVariasIpsDto $p) => [
                'uuid' => $p->uuid,
                'nombre' => $p->nombre,
                'ips' => $p->ips,
            ], $s->sesionesDesdeVariasIps),
        ];
    }
}
