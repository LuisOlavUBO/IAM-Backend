<?php

namespace App\Domains\Clientes\Domain\Dto;

/**
 * A client built by NuevoCliente and not saved yet (design D2 of clientes-crear): what
 * IClientesRepository::crear writes in oauth_clients, plus the secret in clear, which only goes back to who
 * shows it once. Never audited nor logged.
 */
final readonly class NuevoClienteDto
{
    /**
     * @param  string  $id  UUID v7 in lower case
     * @param  string  $tipo  NuevoCliente::AUTHORIZATION_CODE or NuevoCliente::CLIENT_CREDENTIALS
     * @param  list<string>  $redirecciones  Empty for client-credentials
     * @param  list<string>  $concesiones  The grant types
     * @param  string|null  $provider  'users' for authorization-code, null for client-credentials
     * @param  string  $hashSecreto  The bcrypt hash stored in oauth_clients.secret
     * @param  string  $secreto  The secret in clear, shown once
     */
    public function __construct(
        public string $id,
        public string $nombre,
        public string $tipo,
        public array $redirecciones,
        public array $concesiones,
        public ?string $provider,
        public string $hashSecreto,
        public string $secreto,
    ) {}
}
