<?php

namespace App\Domains\Aplicaciones\Domain;

use App\Domains\Aplicaciones\Domain\Dto\PermisoParaEscribirDto;

/**
 * The rules for writing the catalog (spec aplicaciones, design D3 and D4 of aplicaciones-catalogo). Pure: the
 * data comes as arguments, so they are tested without a database. The formats apply only to new names: the
 * existing ones are not validated.
 */
final class ReglasDeCatalogo
{
    public const IAM_ADMIN = 'iam-admin';

    public const LARGO_MAXIMO = 100;

    /** A lowercase slug: iam-admin, pago-honorarios. */
    public const FORMATO_APLICACION = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /** Lowercase, digits and ":._-", starting with a letter or digit: iam:usuarios.leer, geografia:leer, introspect. */
    public const FORMATO_PERMISO = '/^[a-z0-9][a-z0-9:._-]*$/';

    /** Letters, digits, spaces and "._-", starting with a letter or digit, never "/": mesa-de-ayuda, Docente. */
    public const FORMATO_ROL = '/^[\pL\pN][\pL\pN ._-]*$/u';

    public static function esNombreDeAplicacion(string $nombre): bool
    {
        return self::cumple(self::FORMATO_APLICACION, $nombre);
    }

    public static function esNombreDePermiso(string $nombre): bool
    {
        return self::cumple(self::FORMATO_PERMISO, $nombre);
    }

    public static function esNombreDeRol(string $nombre): bool
    {
        return self::cumple(self::FORMATO_ROL, $nombre);
    }

    /** The catalog of iam-admin is written only by iam:install: changing it from the portal would escalate privileges. */
    public static function verificarQueNoEsIamAdmin(string $aplicacion): void
    {
        if (strcasecmp($aplicacion, self::IAM_ADMIN) === 0) {
            throw new CatalogoDeIamAdminException;
        }
    }

    /**
     * The permissions of iam-admin are not given to roles of other applications.
     *
     * @param  list<PermisoParaEscribirDto>  $permisos
     */
    public static function verificarPermisosAsignables(array $permisos): void
    {
        foreach ($permisos as $permiso) {
            self::verificarQueNoEsIamAdmin($permiso->aplicacion);
        }
    }

    private static function cumple(string $formato, string $nombre): bool
    {
        return mb_strlen($nombre) <= self::LARGO_MAXIMO && preg_match($formato, $nombre) === 1;
    }
}
