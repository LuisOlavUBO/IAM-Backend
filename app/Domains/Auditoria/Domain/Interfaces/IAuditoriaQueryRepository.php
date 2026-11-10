<?php

namespace App\Domains\Auditoria\Domain\Interfaces;

use App\Domains\Auditoria\Domain\Dto\EntradaAuditoriaDto;
use App\Domains\Auditoria\Domain\Dto\FiltroAuditoriaDto;
use App\Shared\Paginacion\Pagina;

/**
 * Read repository of the audit log, the query side of CQRS lite (spec arquitectura-de-dominios). It
 * never writes. The actors come with uuid only: their name and email belong to Usuarios.
 */
interface IAuditoriaQueryRepository
{
    /**
     * Entries that match the filter, newest first.
     *
     * @return Pagina<EntradaAuditoriaDto>
     */
    public function listar(FiltroAuditoriaDto $filtro): Pagina;
}
