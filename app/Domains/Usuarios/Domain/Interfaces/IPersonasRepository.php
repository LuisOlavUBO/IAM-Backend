<?php

namespace App\Domains\Usuarios\Domain\Interfaces;

use App\Domains\Usuarios\Domain\ClaveTemporal;
use App\Domains\Usuarios\Domain\CorreoEnUsoException;
use App\Domains\Usuarios\Domain\Dto\NuevaPersonaDto;
use App\Domains\Usuarios\Domain\RutEnUsoException;

/**
 * Write repository of people (users), the command side of CQRS lite (design D2).
 */
interface IPersonasRepository
{
    /** Whether a person uses that email (the collation ignores case). */
    public function correoEnUso(string $correo): bool;

    /** Whether a person uses that RUT, already normalized. */
    public function rutEnUso(string $rut): bool;

    /**
     * Inserts the person with that uuid and, when given, the hash and expiry of the temporary password.
     *
     * @throws CorreoEnUsoException|RutEnUsoException When a unique index rejects the insert (design D4)
     */
    public function crear(string $uuid, NuevaPersonaDto $persona, ?ClaveTemporal $clave): void;
}
