<?php

namespace App\Domains\Clientes\Domain\Interfaces;

use App\Domains\Clientes\Domain\Dto\ClienteParaRotarDto;

/**
 * Write repository of OAuth clients (oauth_clients, owned by API-IAM), the command side of CQRS lite
 * (design D2 of clientes-rotar-secreto).
 */
interface IClientesRepository
{
    /** The client with that id (a UUID, in any case), or null. */
    public function buscar(string $id): ?ClienteParaRotarDto;

    /**
     * The clients with exactly that name: names are not unique in oauth_clients.
     *
     * @return list<ClienteParaRotarDto>
     */
    public function buscarPorNombre(string $nombre): array;

    /** Replaces the secret's hash and touches updated_at. */
    public function cambiarSecreto(string $id, string $hash): void;
}
