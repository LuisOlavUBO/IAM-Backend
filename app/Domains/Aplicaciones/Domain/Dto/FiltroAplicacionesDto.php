<?php

namespace App\Domains\Aplicaciones\Domain\Dto;

/**
 * What the portal asks of the list of applications (spec aplicaciones).
 */
final readonly class FiltroAplicacionesDto
{
    public const POR_PAGINA = 20;

    public function __construct(
        public string $buscar = '',
        public int $pagina = 1,
        public int $porPagina = self::POR_PAGINA,
    ) {}
}
