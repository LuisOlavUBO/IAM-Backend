<?php

namespace App\Domains\Clientes\Domain\Dto;

/**
 * What the writes on a client (rotating its secret, revoking or reactivating it) need to know of it: never
 * its secret nor its hash.
 */
final readonly class ClienteParaEscribirDto
{
    public function __construct(
        public string $id,
        public string $nombre,
        public string $aplicacion,
        public bool $confidencial,
        public bool $revocado,
    ) {}

    /** API-IAM itself uses the clients of iam-admin: iam-admin-portal and iam-admin-sistema. */
    public function esDeIamAdmin(): bool
    {
        return $this->aplicacion === 'iam-admin';
    }
}
