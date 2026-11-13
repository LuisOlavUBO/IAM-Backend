<?php

namespace App\Domains\Aplicaciones\Domain\Dto;

/**
 * Who a change to an element of the catalog reaches (design D2 of aplicaciones-renombrar-y-borrar): counts and
 * names, never people. It goes in the problem of a deletion of something in use, and says whether the element is
 * in use (design D3).
 */
final readonly class ImpactoDto
{
    /**
     * @param  array<string, int|list<string>>  $detalle  The members of "impacto", as the API answers them
     */
    private function __construct(
        public array $detalle,
        public bool $enUso,
    ) {}

    /** An application is in use while it has permissions, roles or clients. */
    public static function deAplicacion(int $permisos, int $roles, int $clientes): self
    {
        return new self(
            ['permisos' => $permisos, 'roles' => $roles, 'clientes' => $clientes],
            $permisos + $roles + $clientes > 0,
        );
    }

    /**
     * Who loses access when the application is disabled (aplicaciones-baja): the distinct people with roles in it
     * and its clients.
     */
    public static function deBajaDeAplicacion(int $personas, int $clientes): self
    {
        return new self(['personas' => $personas, 'clientes' => $clientes], $personas + $clientes > 0);
    }

    /**
     * A permission is in use while some role grants it.
     *
     * @param  list<string>  $roles  The roles that grant it, as "application/role"
     * @param  int  $personas  Distinct people that receive it through those roles
     * @param  int  $clientes  Distinct clients that receive it through those roles
     */
    public static function dePermiso(array $roles, int $personas, int $clientes): self
    {
        return new self(['roles' => $roles, 'personas' => $personas, 'clientes' => $clientes], $roles !== []);
    }

    /**
     * A role is in use while some person or client has it; the permissions it grants do not count.
     *
     * @param  list<string>  $permisos  The names of the permissions it grants
     */
    public static function deRol(int $personas, int $clientes, array $permisos): self
    {
        return new self(['personas' => $personas, 'clientes' => $clientes, 'permisos' => $permisos], $personas + $clientes > 0);
    }
}
