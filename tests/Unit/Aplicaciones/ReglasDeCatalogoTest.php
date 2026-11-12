<?php

namespace Tests\Unit\Aplicaciones;

use App\Domains\Aplicaciones\Domain\CatalogoDeIamAdminException;
use App\Domains\Aplicaciones\Domain\Dto\PermisoParaEscribirDto;
use App\Domains\Aplicaciones\Domain\ReglasDeCatalogo;
use PHPUnit\Framework\TestCase;

/**
 * Design D3 and D4 of aplicaciones-catalogo: the name formats and the protection of iam-admin.
 */
class ReglasDeCatalogoTest extends TestCase
{
    public function test_application_names_are_lowercase_slugs(): void
    {
        foreach (['iam-admin', 'pago-honorarios', 'fuas', 'app2', str_repeat('a', 100)] as $nombre) {
            $this->assertTrue(ReglasDeCatalogo::esNombreDeAplicacion($nombre), $nombre);
        }

        foreach (['Biblioteca', 'pago_honorarios', '-fuas', 'fuas-', 'pago--honorarios', 'mi app', 'á', '', str_repeat('a', 101)] as $nombre) {
            $this->assertFalse(ReglasDeCatalogo::esNombreDeAplicacion($nombre), $nombre);
        }
    }

    public function test_permission_names_admit_colon_dot_underscore_and_dash(): void
    {
        foreach (['iam:usuarios.leer', 'geografia:leer', 'introspect', 'user:read', 'biblioteca:prestamos.leer', 'a_b-c', '1x'] as $nombre) {
            $this->assertTrue(ReglasDeCatalogo::esNombreDePermiso($nombre), $nombre);
        }

        foreach (['Iam:usuarios.leer', ':leer', '.leer', 'leer todo', 'a/b', 'ñ', '', str_repeat('a', 101)] as $nombre) {
            $this->assertFalse(ReglasDeCatalogo::esNombreDePermiso($nombre), $nombre);
        }
    }

    public function test_role_names_admit_letters_digits_spaces_and_punctuation_but_never_a_slash(): void
    {
        foreach (['mesa-de-ayuda', 'Docente', 'administrador', 'Jefe de carrera', 'Año 1', 'rol_2.b'] as $nombre) {
            $this->assertTrue(ReglasDeCatalogo::esNombreDeRol($nombre), $nombre);
        }

        foreach (['iam-admin/administrador', ' docente', '-docente', 'docente:x', '', str_repeat('a', 101)] as $nombre) {
            $this->assertFalse(ReglasDeCatalogo::esNombreDeRol($nombre), $nombre);
        }
    }

    public function test_the_catalog_of_iam_admin_is_refused_in_any_case(): void
    {
        ReglasDeCatalogo::verificarQueNoEsIamAdmin('biblioteca');

        foreach (['iam-admin', 'IAM-Admin'] as $aplicacion) {
            try {
                ReglasDeCatalogo::verificarQueNoEsIamAdmin($aplicacion);
                $this->fail('Se esperaba CatalogoDeIamAdminException.');
            } catch (CatalogoDeIamAdminException $rechazo) {
                $this->assertSame('CATALOGO_DE_IAM_ADMIN', $rechazo->code());
                $this->assertSame(409, $rechazo->status());
            }
        }
    }

    public function test_permissions_of_other_applications_are_assignable_but_not_those_of_iam_admin(): void
    {
        $geografia = new PermisoParaEscribirDto(1, 'geografia', 'geografia:leer', 'Leer');
        $iam = new PermisoParaEscribirDto(2, 'iam-admin', 'iam:usuarios.administrar', 'Administrar');

        ReglasDeCatalogo::verificarPermisosAsignables([]);
        ReglasDeCatalogo::verificarPermisosAsignables([$geografia]);

        $this->expectException(CatalogoDeIamAdminException::class);
        ReglasDeCatalogo::verificarPermisosAsignables([$geografia, $iam]);
    }
}
