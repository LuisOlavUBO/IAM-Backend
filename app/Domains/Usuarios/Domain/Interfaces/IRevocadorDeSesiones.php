<?php

namespace App\Domains\Usuarios\Domain\Interfaces;

/**
 * Closes every session of a person in API-Auth (design D5).
 */
interface IRevocadorDeSesiones
{
    /** True when API-Auth confirmed it; false on any failure, which is logged and never thrown. */
    public function revocar(string $uuid): bool;
}
