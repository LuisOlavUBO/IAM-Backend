<?php

namespace Tests\Unit;

use App\Domains\Usuarios\Application\FiltroUsuarios;
use App\Domains\Usuarios\Application\RolAsignado;
use App\Domains\Usuarios\Application\UsuarioService;
use App\Domains\Usuarios\Contracts\IUsuarioRepository;
use App\Domains\Usuarios\Exceptions\UsuarioNoEncontrado;
use App\Domains\Usuarios\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginador;
use Tests\TestCase;

/**
 * UsuarioService against a fake IUsuarioRepository: the rules of the query, without a database.
 */
class UsuarioServiceTest extends TestCase
{
    private function repositorio(?Usuario $usuario = null): IUsuarioRepository
    {
        return new class($usuario) implements IUsuarioRepository
        {
            public ?FiltroUsuarios $filtro = null;

            public int $busquedas = 0;

            public function __construct(private readonly ?Usuario $usuario) {}

            public function listar(FiltroUsuarios $filtro): LengthAwarePaginator
            {
                $this->filtro = $filtro;

                return new Paginador([], 0, $filtro->porPagina, $filtro->pagina);
            }

            public function buscarPorUuid(string $uuid): ?Usuario
            {
                $this->busquedas++;

                return $this->usuario;
            }

            public function rolesDe(Usuario $usuario): array
            {
                return [new RolAsignado('iam-admin', null, 'administrador', null)];
            }
        };
    }

    public function test_a_rut_with_dots_reaches_the_repository_normalized(): void
    {
        $repositorio = $this->repositorio();

        (new UsuarioService($repositorio))->listar(new FiltroUsuarios(buscar: '12.345.678-5'));

        $this->assertSame('12345678-5', $repositorio->filtro->rut);
        $this->assertSame('12.345.678-5', $repositorio->filtro->buscar);
    }

    public function test_a_text_that_is_not_a_valid_rut_searches_only_by_name_and_email(): void
    {
        $repositorio = $this->repositorio();

        (new UsuarioService($repositorio))->listar(new FiltroUsuarios(buscar: '12.345.678-9'));

        $this->assertNull($repositorio->filtro->rut);
    }

    public function test_a_malformed_uuid_does_not_reach_the_repository(): void
    {
        $repositorio = $this->repositorio(new Usuario);

        try {
            (new UsuarioService($repositorio))->obtener('no-es-un-uuid');
            $this->fail('Se esperaba UsuarioNoEncontrado.');
        } catch (UsuarioNoEncontrado) {
            $this->assertSame(0, $repositorio->busquedas);
        }
    }

    public function test_an_unknown_person_is_not_found(): void
    {
        $this->expectException(UsuarioNoEncontrado::class);

        (new UsuarioService($this->repositorio()))->obtener('0190f2a4-3c1e-7d2b-9a6f-1b2c3d4e5f60');
    }

    public function test_the_detail_has_the_person_and_their_roles(): void
    {
        $usuario = new Usuario;

        $detalle = (new UsuarioService($this->repositorio($usuario)))->obtener('0190f2a4-3c1e-7d2b-9a6f-1b2c3d4e5f60');

        $this->assertSame($usuario, $detalle->usuario);
        $this->assertSame('administrador', $detalle->roles[0]->rol);
    }
}
