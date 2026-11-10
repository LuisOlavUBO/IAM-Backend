<?php

namespace App\Domains\Auditoria\Infrastructure;

use App\Domains\Auditoria\Domain\Dto\ActorDto;
use App\Domains\Auditoria\Domain\Dto\EntradaAuditoriaDto;
use App\Domains\Auditoria\Domain\Dto\FiltroAuditoriaDto;
use App\Domains\Auditoria\Domain\Interfaces\IAuditoriaQueryRepository;
use App\Shared\Paginacion\Pagina;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * IAuditoriaQueryRepository against SQL Server (design D4, D5). The filters use the indexes of
 * iam_auditoria; the row id only breaks ties in the order and never leaves this class.
 */
final class AuditoriaQueryRepository implements IAuditoriaQueryRepository
{
    public function listar(FiltroAuditoriaDto $filtro): Pagina
    {
        $consulta = DB::table('iam_auditoria');
        $this->filtrar($consulta, $filtro);

        $total = (clone $consulta)->count();

        $items = $consulta
            ->orderByDesc('ocurrido_en')
            ->orderByDesc('id')
            ->forPage($filtro->pagina, $filtro->porPagina)
            ->get([
                'ocurrido_en', 'actor_tipo', 'actor_uuid', 'accion', 'entidad_tipo', 'entidad_id',
                'resultado', 'motivo', 'antes', 'despues', 'trace_id',
            ])
            ->map(fn (object $fila) => new EntradaAuditoriaDto(
                CarbonImmutable::parse($fila->ocurrido_en),
                new ActorDto($fila->actor_tipo, $fila->actor_uuid === null ? null : Str::lower($fila->actor_uuid)),
                $fila->accion,
                $fila->entidad_tipo,
                $fila->entidad_id,
                $fila->resultado,
                $fila->motivo,
                self::json($fila->antes),
                self::json($fila->despues),
                $fila->trace_id,
            ))
            ->all();

        return new Pagina($items, $total, $filtro->pagina, $filtro->porPagina);
    }

    private function filtrar(Builder $consulta, FiltroAuditoriaDto $filtro): void
    {
        if ($filtro->desde !== null) {
            $consulta->where('ocurrido_en', '>=', CarbonImmutable::parse($filtro->desde)->startOfDay());
        }
        if ($filtro->hasta !== null) {
            $consulta->where('ocurrido_en', '<', CarbonImmutable::parse($filtro->hasta)->addDay()->startOfDay());
        }
        if ($filtro->actor !== null) {
            $consulta->where('actor_uuid', Str::lower($filtro->actor));
        }
        if ($filtro->accion !== null) {
            $patron = str_replace(['!', '%', '_', '['], ['!!', '!%', '!_', '!['], $filtro->accion).'%';
            $consulta->whereRaw("accion LIKE ? ESCAPE '!'", [$patron]);
        }
        if ($filtro->entidadTipo !== null) {
            $consulta->where('entidad_tipo', $filtro->entidadTipo);
        }
        if ($filtro->entidadId !== null) {
            $consulta->where('entidad_id', $filtro->entidadId);
        }
        if ($filtro->resultado !== null) {
            $consulta->where('resultado', $filtro->resultado);
        }
    }

    /**
     * The before/after values as written by AuditorBaseDatos; anything that is not a JSON object or
     * list counts as null (design D5).
     *
     * @return array<array-key, mixed>|null
     */
    private static function json(?string $valor): ?array
    {
        $decodificado = $valor === null ? null : json_decode($valor, true);

        return is_array($decodificado) ? $decodificado : null;
    }
}
