<?php

namespace App\Domains\Aplicaciones\Contracts;

/**
 * What Aplicaciones offers other domains: the current names of applications, roles and permissions by
 * their numeric id, in batch. Other domains depend on this interface, never on the tables of Aplicaciones
 * (spec arquitectura-de-dominios). Ids that do not exist are left out.
 */
interface IDirectorioAplicacionesService
{
    /**
     * @param  list<int>  $ids
     * @return array<int, string> id => application name
     */
    public function aplicaciones(array $ids): array;

    /**
     * @param  list<int>  $ids
     * @return array<int, string> id => "application/role", as the audit log writes it
     */
    public function roles(array $ids): array;

    /**
     * @param  list<int>  $ids
     * @return array<int, string> id => permission name
     */
    public function permisos(array $ids): array;

    /** The id of the application with that name, without distinguishing case, or null. */
    public function idDeAplicacion(string $nombre): ?int;
}
