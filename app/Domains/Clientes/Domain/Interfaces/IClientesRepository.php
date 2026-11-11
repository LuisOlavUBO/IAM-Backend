<?php

namespace App\Domains\Clientes\Domain\Interfaces;

use App\Domains\Clientes\Domain\Dto\ClienteParaEscribirDto;

/**
 * Write repository of OAuth clients (oauth_clients, owned by API-IAM), the command side of CQRS lite
 * (design D2 of clientes-rotar-secreto and of clientes-revocar).
 */
interface IClientesRepository
{
    /** The client with that id (a UUID, in any case), or null. */
    public function buscar(string $id): ?ClienteParaEscribirDto;

    /**
     * The clients with exactly that name: names are not unique in oauth_clients.
     *
     * @return list<ClienteParaEscribirDto>
     */
    public function buscarPorNombre(string $nombre): array;

    /** Replaces the secret's hash and touches updated_at. */
    public function cambiarSecreto(string $id, string $hash): void;

    /** Sets or clears revoked and touches updated_at; false when the client was already in that state. */
    public function marcarRevocado(string $id, bool $revocado): bool;
}
