<?php

namespace App\Domains\Clientes\Domain;

use App\Domains\Clientes\Domain\Dto\ClienteParaEscribirDto;
use App\Domains\Clientes\Domain\Dto\RolDeAplicacionDto;

/**
 * The rules for changing a client's roles and redirections (spec clientes, design D3 and D4 of
 * clientes-roles-y-redirecciones). Pure: the data comes as arguments, so they are tested without a database.
 */
final class ReglasDeClientes
{
    public const IAM_ADMIN = 'iam-admin';

    /** API-IAM uses the clients of iam-admin: the portal never changes them. */
    public static function verificarQueNoEsDeIamAdmin(ClienteParaEscribirDto $cliente): void
    {
        if ($cliente->esDeIamAdmin()) {
            throw new ClienteDeIamAdminException;
        }
    }

    /** Only the tokens of a service client carry its roles: those of a login client carry the person's. */
    public static function verificarQueAdmiteRoles(ClienteParaEscribirDto $cliente): void
    {
        if ($cliente->tipo !== NuevoCliente::CLIENT_CREDENTIALS) {
            throw new ClienteSinRolesException;
        }
    }

    /** Only a login client redirects. */
    public static function verificarQueAdmiteRedirecciones(ClienteParaEscribirDto $cliente): void
    {
        if ($cliente->tipo !== NuevoCliente::AUTHORIZATION_CODE) {
            throw new ClienteSinRedireccionesException;
        }
    }

    /** The roles of iam-admin are not given to clients. */
    public static function verificarRolAsignable(RolDeAplicacionDto $rol): void
    {
        if ($rol->aplicacion === self::IAM_ADMIN) {
            throw new RolDeIamAdminException;
        }
    }

    /**
     * Whether two lists hold the same redirections, regardless of order and repetitions.
     *
     * @param  list<string>  $antes
     * @param  list<string>  $despues
     */
    public static function mismasRedirecciones(array $antes, array $despues): bool
    {
        $antes = array_values(array_unique($antes));
        $despues = array_values(array_unique($despues));
        sort($antes);
        sort($despues);

        return $antes === $despues;
    }
}
