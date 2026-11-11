<?php

namespace App\Domains\Clientes\Features\CrearCliente;

/**
 * What the portal asks to create, already validated (design D4 of clientes-crear).
 */
final readonly class DatosNuevoClienteDto
{
    /**
     * @param  string  $aplicacion  The application's name, resolved by the handler
     * @param  string  $tipo  NuevoCliente::AUTHORIZATION_CODE or NuevoCliente::CLIENT_CREDENTIALS
     * @param  list<string>  $redirecciones  Empty for client-credentials
     */
    public function __construct(
        public string $nombre,
        public string $aplicacion,
        public string $tipo,
        public array $redirecciones,
    ) {}
}
