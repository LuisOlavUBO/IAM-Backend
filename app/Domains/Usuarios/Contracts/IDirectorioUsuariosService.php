<?php

namespace App\Domains\Usuarios\Contracts;

/**
 * What Usuarios offers other domains: the basic data of a person by public uuid. Other domains
 * depend on this interface, never on the tables or classes of Usuarios (spec arquitectura-de-dominios).
 */
interface IDirectorioUsuariosService
{
    /** The person's name and email, or null when no person has that uuid (lowercase). */
    public function persona(string $uuid): ?PersonaBasicaDto;

    /**
     * Several people in one query, keyed by their lowercase uuid. Unknown uuids are left out.
     *
     * @param  list<string>  $uuids
     * @return array<string, PersonaBasicaDto>
     */
    public function personas(array $uuids): array;
}
