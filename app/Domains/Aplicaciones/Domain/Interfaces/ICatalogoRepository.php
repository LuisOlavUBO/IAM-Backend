<?php

namespace App\Domains\Aplicaciones\Domain\Interfaces;

use App\Domains\Aplicaciones\Domain\Dto\AplicacionParaEscribirDto;
use App\Domains\Aplicaciones\Domain\Dto\ImpactoDto;
use App\Domains\Aplicaciones\Domain\Dto\PermisoParaEscribirDto;
use App\Domains\Aplicaciones\Domain\Dto\RolParaEscribirDto;

/**
 * Write repository of the catalog (applications, oauth_scopes, roles and oauth_scope_role, owned by API-IAM),
 * the command side of CQRS lite (design D2 of aplicaciones-catalogo). Names are looked up without
 * distinguishing case, as the CI collation of the IAM database compares them; the DTO carry them as stored.
 */
interface ICatalogoRepository
{
    public function aplicacion(string $nombre): ?AplicacionParaEscribirDto;

    /** The permission with that name, of any application, or null. */
    public function permiso(string $nombre): ?PermisoParaEscribirDto;

    /**
     * The permissions with those names, of any application, ordered by name. Names that do not exist are
     * left out.
     *
     * @param  list<string>  $nombres
     * @return list<PermisoParaEscribirDto>
     */
    public function permisos(array $nombres): array;

    /** The role with that name in that application, or null. */
    public function rol(int $aplicacionId, string $nombre): ?RolParaEscribirDto;

    /** @return int The id of the new application */
    public function crearAplicacion(string $nombre, ?string $descripcion): int;

    /** @return int The id of the new permission */
    public function crearPermiso(int $aplicacionId, string $nombre, string $descripcion): int;

    /** @return int The id of the new role */
    public function crearRol(int $aplicacionId, string $nombre, ?string $descripcion): int;

    public function describirAplicacion(int $id, ?string $descripcion): void;

    public function describirPermiso(int $id, string $descripcion): void;

    public function describirRol(int $id, ?string $descripcion): void;

    /**
     * The permissions the role grants, of any application, ordered by name.
     *
     * @return list<PermisoParaEscribirDto>
     */
    public function permisosDeRol(int $rolId): array;

    public function asignarPermiso(int $rolId, int $permisoId): void;

    public function quitarPermiso(int $rolId, int $permisoId): void;

    /** How many permissions, roles and clients the application has (design D2 of aplicaciones-renombrar-y-borrar). */
    public function impactoDeAplicacion(int $id): ImpactoDto;

    /** The roles that grant the permission, and the distinct people and clients that receive it through them. */
    public function impactoDePermiso(int $id): ImpactoDto;

    /** The people and clients that have the role, and the permissions it grants. */
    public function impactoDeRol(int $id): ImpactoDto;

    /** Only an application without permissions, roles or clients: their foreign keys do not cascade. */
    public function borrarAplicacion(int $id): void;

    /** The roles that granted it lose it by the cascade of oauth_scope_role. */
    public function borrarPermiso(int $id): void;

    /** Its rows of oauth_scope_role go with it by the cascade; the permissions remain. */
    public function borrarRol(int $id): void;
}
