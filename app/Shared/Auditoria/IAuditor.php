<?php

namespace App\Shared\Auditoria;

/**
 * Port of the audit log (spec auditoria): every use case and command that changes data records
 * it here. Writing the log is cross-cutting; reading it is the Auditoria domain.
 */
interface IAuditor
{
    /**
     * Records an applied action. It must run inside the transaction of the change, so the entry
     * and the change are saved together or not at all.
     *
     * @param  array<string, mixed>|null  $antes  Only the auditable fields of the action
     * @param  array<string, mixed>|null  $despues  Only the auditable fields of the action
     */
    public function registrar(string $accion, string $entidadTipo, string $entidadId, ?array $antes = null, ?array $despues = null): void;

    /**
     * Records an action refused by a business rule. There is no change to save with it.
     */
    public function rechazar(string $accion, string $entidadTipo, string $entidadId, string $motivo): void;
}
