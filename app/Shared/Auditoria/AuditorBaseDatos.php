<?php

namespace App\Shared\Auditoria;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use UBO\Common\Tracing\Contracts\TraceContext;

/**
 * The only way to write the audit log (spec auditoria), in iam_auditoria. Entries are only
 * inserted: nothing in API-IAM updates or deletes them.
 */
class AuditorBaseDatos implements IAuditor
{
    /**
     * Keys that must never reach the log, at any depth: secrets are audited only as actions.
     */
    private const FORBIDDEN_KEYS = ['password', 'secret', 'secreto', 'contrasena', 'clave'];

    /**
     * Records an applied action. It must run inside the transaction of the change, so the
     * entry and the change are saved together or not at all.
     *
     * @param  array<string, mixed>|null  $antes  Only the auditable fields of the action
     * @param  array<string, mixed>|null  $despues  Only the auditable fields of the action
     */
    public function registrar(string $accion, string $entidadTipo, string $entidadId, ?array $antes = null, ?array $despues = null): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException("La acción {$accion} debe auditarse dentro de la transacción del cambio.");
        }

        $this->insert($accion, $entidadTipo, $entidadId, 'ok', null, $antes, $despues);
    }

    /**
     * Records an action refused by a business rule. There is no change to save with it.
     */
    public function rechazar(string $accion, string $entidadTipo, string $entidadId, string $motivo): void
    {
        $this->insert($accion, $entidadTipo, $entidadId, 'rechazado', $motivo, null, null);
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     */
    private function insert(string $accion, string $entidadTipo, string $entidadId, string $resultado, ?string $motivo, ?array $antes, ?array $despues): void
    {
        $this->assertNoSecrets($antes);
        $this->assertNoSecrets($despues);

        $actorUuid = Auth::guard('ubo')->check() ? Str::lower((string) Auth::guard('ubo')->id()) : null;

        DB::table('iam_auditoria')->insert([
            'ocurrido_en' => now(),
            'actor_tipo' => $actorUuid === null ? 'comando' : 'persona',
            'actor_uuid' => $actorUuid,
            'accion' => $accion,
            'entidad_tipo' => $entidadTipo,
            'entidad_id' => $entidadId,
            'resultado' => $resultado,
            'motivo' => $motivo,
            'antes' => $antes === null ? null : json_encode($antes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'despues' => $despues === null ? null : json_encode($despues, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'trace_id' => app()->bound(TraceContext::class) ? app(TraceContext::class)->traceId() : null,
        ]);
    }

    /**
     * @param  array<array-key, mixed>|null  $values
     */
    private function assertNoSecrets(?array $values): void
    {
        foreach ($values ?? [] as $key => $value) {
            if (is_string($key) && in_array(Str::lower($key), self::FORBIDDEN_KEYS, true)) {
                throw new LogicException("La auditoría no guarda secretos: quite el campo {$key}.");
            }

            if (is_array($value)) {
                $this->assertNoSecrets($value);
            }
        }
    }
}
