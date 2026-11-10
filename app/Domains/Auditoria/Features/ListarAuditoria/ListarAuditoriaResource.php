<?php

namespace App\Domains\Auditoria\Features\ListarAuditoria;

use App\Domains\Auditoria\Domain\Dto\EntradaAuditoriaDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry of the audit log (EntradaAuditoria in OpenAPI). before/after go as JSON objects (or null),
 * so an empty PHP array is sent as {} and not as [].
 *
 * @property EntradaAuditoriaDto $resource
 */
final class ListarAuditoriaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $e = $this->resource;

        return [
            'ocurrido_en' => $e->ocurridoEn->toIso8601String(),
            'actor' => [
                'tipo' => $e->actor->tipo,
                'uuid' => $e->actor->uuid,
                'nombre' => $e->actor->nombre,
                'correo' => $e->actor->correo,
            ],
            'accion' => $e->accion,
            'entidad' => ['tipo' => $e->entidadTipo, 'id' => $e->entidadId, 'nombre' => $e->entidadNombre],
            'resultado' => $e->resultado,
            'motivo' => $e->motivo,
            'antes' => self::objeto($e->antes),
            'despues' => self::objeto($e->despues),
            'trace_id' => $e->traceId,
        ];
    }

    /**
     * @param  array<array-key, mixed>|null  $valores
     * @return array<array-key, mixed>|object|null
     */
    private static function objeto(?array $valores): array|object|null
    {
        return $valores === [] ? new \stdClass : $valores;
    }
}
