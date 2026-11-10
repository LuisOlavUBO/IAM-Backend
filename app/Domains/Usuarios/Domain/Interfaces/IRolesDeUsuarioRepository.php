<?php

namespace App\Domains\Usuarios\Domain\Interfaces;

use App\Domains\Usuarios\Domain\Dto\RolDeAplicacionDto;

/**
 * Write repository of the roles of people (role_user), the command side of CQRS lite (design D4). The
 * internal ids it returns stay inside Usuarios.
 */
interface IRolesDeUsuarioRepository
{
    /** Internal id of the person with that uuid, or null. */
    public function idDePersona(string $uuid): ?int;

    /** Whether the person has no disabled_at. */
    public function activa(int $personaId): bool;

    /** The role by application and role names (the collation ignores case), or null. */
    public function rol(string $aplicacion, string $nombre): ?RolDeAplicacionDto;

    public function tiene(int $personaId, int $rolId): bool;

    public function asignar(int $personaId, int $rolId): void;

    public function quitar(int $personaId, int $rolId): void;

    /**
     * Names of the permissions of an application.
     *
     * @return list<string>
     */
    public function permisosDeAplicacion(string $aplicacion): array;

    /** Active people with the role, besides this person. */
    public function otrosActivosConRol(int $rolId, int $personaId): int;
}
