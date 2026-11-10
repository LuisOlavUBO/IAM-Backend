<?php

namespace App\Domains\Usuarios\Features\VerUsuario;

use App\Domains\Usuarios\Domain\Dto\UsuarioDetalleDto;
use App\Domains\Usuarios\Domain\Interfaces\IUsuarioQueryRepository;
use App\Domains\Usuarios\Domain\UsuarioNoEncontradoException;
use Illuminate\Support\Str;

/**
 * Use case: one person of IAM with their roles (spec usuarios).
 */
final class VerUsuarioQueryHandler
{
    public function __construct(private readonly IUsuarioQueryRepository $usuarios) {}

    public function __invoke(string $uuid): UsuarioDetalleDto
    {
        // A value that is not a UUID would not convert to uniqueidentifier in SQL Server.
        if (! Str::isUuid($uuid)) {
            throw new UsuarioNoEncontradoException;
        }

        return $this->usuarios->detalle($uuid) ?? throw new UsuarioNoEncontradoException;
    }
}
