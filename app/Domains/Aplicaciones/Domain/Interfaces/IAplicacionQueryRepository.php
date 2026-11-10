<?php

namespace App\Domains\Aplicaciones\Domain\Interfaces;

use App\Domains\Aplicaciones\Domain\Dto\AplicacionDetalleDto;
use App\Domains\Aplicaciones\Domain\Dto\AplicacionResumenDto;
use App\Domains\Aplicaciones\Domain\Dto\FiltroAplicacionesDto;
use App\Shared\Paginacion\Pagina;

/**
 * Read repository of the applications of IAM, the query side of CQRS lite (spec
 * arquitectura-de-dominios). It returns DTOs without internal ids, and never writes.
 */
interface IAplicacionQueryRepository
{
    /**
     * Applications that match the filter, ordered by name.
     *
     * @return Pagina<AplicacionResumenDto>
     */
    public function listar(FiltroAplicacionesDto $filtro): Pagina;

    /** By name (the collation ignores case), with its permissions and roles. */
    public function detalle(string $nombre): ?AplicacionDetalleDto;
}
