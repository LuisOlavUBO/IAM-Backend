<?php

namespace App\Domains\Clientes\Domain\Interfaces;

use App\Domains\Clientes\Domain\Dto\ClienteDetalleDto;
use App\Domains\Clientes\Domain\Dto\ClienteResumenDto;
use App\Domains\Clientes\Domain\Dto\FiltroClientesDto;
use App\Shared\Paginacion\Pagina;

/**
 * Read repository of the OAuth clients of IAM, the query side of CQRS lite (spec
 * arquitectura-de-dominios). It returns DTOs without secrets or numeric ids, and never writes.
 */
interface IClienteQueryRepository
{
    /**
     * Clients that match the filter, ordered by name and id, revoked ones included.
     *
     * @return Pagina<ClienteResumenDto>
     */
    public function listar(FiltroClientesDto $filtro): Pagina;

    /** By id, which must already be a UUID, with its roles. */
    public function detalle(string $id): ?ClienteDetalleDto;
}
