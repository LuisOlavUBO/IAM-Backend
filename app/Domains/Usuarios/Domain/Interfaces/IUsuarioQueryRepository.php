<?php

namespace App\Domains\Usuarios\Domain\Interfaces;

use App\Domains\Usuarios\Domain\Dto\FiltroUsuariosDto;
use App\Domains\Usuarios\Domain\Dto\UsuarioDetalleDto;
use App\Domains\Usuarios\Domain\Dto\UsuarioResumenDto;
use App\Shared\Paginacion\Pagina;

/**
 * Read repository of the people of IAM, the query side of CQRS lite (spec arquitectura-de-dominios,
 * "Lecturas por repositorios de consulta"). It returns DTOs with only the columns the responses use,
 * and never writes.
 */
interface IUsuarioQueryRepository
{
    /**
     * People that match the filter, ordered by name (and uuid to break ties).
     *
     * @return Pagina<UsuarioResumenDto>
     */
    public function listar(FiltroUsuariosDto $filtro): Pagina;

    /** By public uuid (already checked to have the UUID format), in any state, with its roles. */
    public function detalle(string $uuid): ?UsuarioDetalleDto;
}
