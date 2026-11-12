<?php

namespace App\Domains\Clientes\Domain\Interfaces;

use App\Domains\Clientes\Domain\Dto\ClienteParaEscribirDto;
use App\Domains\Clientes\Domain\Dto\NuevoClienteDto;
use App\Domains\Clientes\Domain\Dto\RolDeAplicacionDto;

/**
 * Write repository of OAuth clients (oauth_clients and oauth_client_role, owned by API-IAM), the command side
 * of CQRS lite (design D2 of clientes-rotar-secreto, of clientes-revocar and of clientes-crear; D3 of
 * clientes-roles-y-redirecciones).
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

    /** Whether some client already uses that name, without distinguishing case. */
    public function nombreEnUso(string $nombre): bool;

    /** Inserts the client built by NuevoCliente in that application: only the secret's hash is stored. */
    public function crear(NuevoClienteDto $cliente, int $aplicacionId): void;

    /** Replaces the secret's hash and touches updated_at. */
    public function cambiarSecreto(string $id, string $hash): void;

    /** Sets or clears revoked and touches updated_at; false when the client was already in that state. */
    public function marcarRevocado(string $id, bool $revocado): bool;

    /** The role with that name in that application, of any application, or null. */
    public function rol(string $aplicacion, string $nombre): ?RolDeAplicacionDto;

    /** Whether the client has that role (oauth_client_role). */
    public function tieneRol(string $id, int $rolId): bool;

    public function asignarRol(string $id, int $rolId): void;

    public function quitarRol(string $id, int $rolId): void;

    /**
     * Replaces redirect_uris and touches updated_at.
     *
     * @param  list<string>  $redirecciones
     */
    public function cambiarRedirecciones(string $id, array $redirecciones): void;
}
