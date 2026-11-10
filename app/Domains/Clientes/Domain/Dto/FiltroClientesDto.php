<?php

namespace App\Domains\Clientes\Domain\Dto;

/**
 * What the portal asks of the list of OAuth clients (spec clientes).
 */
final readonly class FiltroClientesDto
{
    public const POR_PAGINA = 20;

    public function __construct(
        public string $buscar = '',
        public int $pagina = 1,
        public int $porPagina = self::POR_PAGINA,
    ) {}
}
