<?php

namespace App\Domains\Aplicaciones\Features\ListarAplicaciones;

use App\Domains\Aplicaciones\Dto\AplicacionResumenDto;
use App\Domains\Aplicaciones\Dto\FiltroAplicacionesDto;
use App\Domains\Aplicaciones\Interfaces\IAplicacionQueryRepository;
use App\Shared\Paginacion\Pagina;

/**
 * Use case: list the applications registered in IAM (spec aplicaciones).
 */
final class ListarAplicacionesQueryHandler
{
    public function __construct(private readonly IAplicacionQueryRepository $aplicaciones) {}

    /**
     * @return Pagina<AplicacionResumenDto>
     */
    public function __invoke(FiltroAplicacionesDto $filtro): Pagina
    {
        return $this->aplicaciones->listar($filtro);
    }
}
