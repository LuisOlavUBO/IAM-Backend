<?php

namespace App\Domains\Clientes\Contracts;

/**
 * What Clientes offers other domains: the current names of OAuth clients by id, in batch. Other domains
 * depend on this interface, never on oauth_clients (spec arquitectura-de-dominios).
 */
interface IDirectorioClientesService
{
    /**
     * @param  list<string>  $ids  Client ids, already UUIDs
     * @return array<string, string> lowercase id => client name; unknown ids are left out
     */
    public function clientes(array $ids): array;
}
