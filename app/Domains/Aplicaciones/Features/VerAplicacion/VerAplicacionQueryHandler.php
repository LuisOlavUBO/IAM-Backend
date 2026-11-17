<?php

namespace App\Domains\Aplicaciones\Features\VerAplicacion;

use App\Domains\Aplicaciones\Dto\AplicacionDetalleDto;
use App\Domains\Aplicaciones\Exceptions\AplicacionNoEncontradaException;
use App\Domains\Aplicaciones\Interfaces\IAplicacionQueryRepository;

/**
 * Use case: one application with its permissions and roles (spec aplicaciones).
 */
final class VerAplicacionQueryHandler
{
    public function __construct(private readonly IAplicacionQueryRepository $aplicaciones) {}

    public function __invoke(string $nombre): AplicacionDetalleDto
    {
        return $this->aplicaciones->detalle($nombre) ?? throw new AplicacionNoEncontradaException;
    }
}
