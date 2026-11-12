<?php

namespace App\Domains\Clientes\Domain\Dto;

use App\Domains\Clientes\Domain\ReglasDeClientes;

/**
 * What the writes on a client (rotating its secret, revoking or reactivating it, its roles and redirections)
 * need to know of it: never its secret nor its hash.
 */
final readonly class ClienteParaEscribirDto
{
    /**
     * @param  ?string  $tipo  authorization-code or client-credentials by its grant types (design D2 of clientes-roles-y-redirecciones); null with neither
     * @param  list<string>  $redirecciones
     */
    public function __construct(
        public string $id,
        public string $nombre,
        public string $aplicacion,
        public bool $confidencial,
        public bool $revocado,
        public ?string $tipo,
        public array $redirecciones,
    ) {}

    /** API-IAM itself uses the clients of iam-admin: iam-admin-portal and iam-admin-sistema. */
    public function esDeIamAdmin(): bool
    {
        return $this->aplicacion === ReglasDeClientes::IAM_ADMIN;
    }
}
