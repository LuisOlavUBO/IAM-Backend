<?php

namespace App\Domains\Clientes\Features\ListarClientes;

use App\Domains\Clientes\Domain\Dto\ClienteResumenDto;
use App\Domains\Clientes\Domain\Dto\FiltroClientesDto;
use App\Domains\Clientes\Domain\Interfaces\IClienteQueryRepository;
use App\Shared\Paginacion\Pagina;

/**
 * Use case: list the OAuth clients registered in IAM (spec clientes).
 */
final class ListarClientesQueryHandler
{
    public function __construct(private readonly IClienteQueryRepository $clientes) {}

    /**
     * @return Pagina<ClienteResumenDto>
     */
    public function __invoke(FiltroClientesDto $filtro): Pagina
    {
        return $this->clientes->listar($filtro);
    }
}
