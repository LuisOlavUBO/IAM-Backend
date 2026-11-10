<?php

namespace App\Domains\Auditoria\Domain\Dto;

/**
 * What the portal asks of the audit log (spec auditoria). Every filter is optional; dates are
 * Y-m-d days in the application's zone, both included.
 */
final readonly class FiltroAuditoriaDto
{
    public const POR_PAGINA = 20;

    public function __construct(
        public ?string $desde = null,
        public ?string $hasta = null,
        public ?string $actor = null,
        public ?string $accion = null,
        public ?string $entidadTipo = null,
        public ?string $entidadId = null,
        public ?string $resultado = null,
        public int $pagina = 1,
        public int $porPagina = self::POR_PAGINA,
    ) {}
}
