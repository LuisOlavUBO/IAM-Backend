<?php

namespace App\Domains\Usuarios\Domain\Interfaces;

use App\Domains\Usuarios\Domain\ClaveTemporal;
use App\Domains\Usuarios\Domain\CorreoEnUsoException;
use App\Domains\Usuarios\Domain\Dto\EstadoPersonaDto;
use App\Domains\Usuarios\Domain\Dto\NuevaPersonaDto;
use App\Domains\Usuarios\Domain\Dto\RolDeAplicacionDto;
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

    /** The internal id of the person with that uuid and whether they are active, or null. */
    public function estado(string $uuid): ?EstadoPersonaDto;

    /**
     * The person's roles of iam-admin.
     *
     * @return list<RolDeAplicacionDto>
     */
    public function rolesDeIamAdmin(int $personaId): array;

    /** Sets disabled_at if the person was active; false when another request already did it. */
    public function darDeBaja(int $personaId): bool;

    /** Clears disabled_at if the person was disabled; false when another request already did it. */
    public function reactivar(int $personaId): bool;

    /** Replaces the password with the hash and expiry of the temporary one. */
    public function cambiarClave(int $personaId, ClaveTemporal $clave): void;
}
