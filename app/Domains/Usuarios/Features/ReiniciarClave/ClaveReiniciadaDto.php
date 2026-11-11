<?php

namespace App\Domains\Usuarios\Features\ReiniciarClave;

use App\Domains\Usuarios\Domain\ClaveTemporal;

/**
 * The new temporary password, shown once and never logged or audited, and whether API-Auth closed the
 * person's sessions.
 */
final readonly class ClaveReiniciadaDto
{
    public function __construct(
        public ClaveTemporal $claveTemporal,
        public bool $sesionesRevocadas,
    ) {}
}
