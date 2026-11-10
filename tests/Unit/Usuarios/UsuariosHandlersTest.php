<?php

namespace Tests\Unit\Usuarios;

use App\Domains\Usuarios\Domain\Dto\FiltroUsuariosDto;
use App\Domains\Usuarios\Domain\Dto\RolAsignadoDto;
use App\Domains\Usuarios\Domain\Dto\UsuarioDetalleDto;
use App\Domains\Usuarios\Domain\Interfaces\IUsuarioQueryRepository;
use App\Domains\Usuarios\Domain\UsuarioNoEncontradoException;
use App\Domains\Usuarios\Features\ListarUsuarios\ListarUsuariosQueryHandler;
use App\Domains\Usuarios\Features\VerUsuario\VerUsuarioQueryHandler;
use App\Shared\Paginacion\Pagina;
use PHPUnit\Framework\TestCase;

/**
 * The use cases of Usuarios against a fake IUsuarioQueryRepository: their rules, without a database
 * (nor Laravel).
 */
class UsuariosHandlersTest extends TestCase
{
    private const ANA = '0190f2a4-3c1e-7d2b-9a6f-1b2c3d4e5f60';

    private function repositorio(?UsuarioDetalleDto $detalle = null): UsuarioQueryRepositoryFalso
    {
        return new UsuarioQueryRepositoryFalso($detalle);
    }

    public function test_a_rut_with_dots_reaches_the_port_normalized(): void
    {
        $consultas = $this->repositorio();

        (new ListarUsuariosQueryHandler($consultas))(new FiltroUsuariosDto(buscar: '12.345.678-5'));

        $this->assertSame('12345678-5', $consultas->filtro->rut);
        $this->assertSame('12.345.678-5', $consultas->filtro->buscar);
    }

    public function test_a_text_that_is_not_a_valid_rut_searches_only_by_name_and_email(): void
    {
        $consultas = $this->repositorio();

        (new ListarUsuariosQueryHandler($consultas))(new FiltroUsuariosDto(buscar: '12.345.678-9'));

        $this->assertNull($consultas->filtro->rut);
    }

    public function test_a_malformed_uuid_does_not_reach_the_port(): void
    {
        $consultas = $this->repositorio();

        try {
            (new VerUsuarioQueryHandler($consultas))('no-es-un-uuid');
            $this->fail('Se esperaba UsuarioNoEncontradoException.');
        } catch (UsuarioNoEncontradoException) {
            $this->assertSame(0, $consultas->detalles);
        }
    }

    public function test_an_unknown_person_is_not_found(): void
    {
        $this->expectException(UsuarioNoEncontradoException::class);

        (new VerUsuarioQueryHandler($this->repositorio()))(self::ANA);
    }

    public function test_the_detail_comes_from_the_port(): void
    {
        $detalle = new UsuarioDetalleDto(self::ANA, 'Ana', 'ana@ubo.cl', null, null, false, true, null, null, null,
            [new RolAsignadoDto('iam-admin', null, 'administrador', null)]);

        $this->assertSame($detalle, (new VerUsuarioQueryHandler($this->repositorio($detalle)))(self::ANA));
    }
}

/**
 * A fake read repository that records what the handlers ask.
 */
final class UsuarioQueryRepositoryFalso implements IUsuarioQueryRepository
{
    public ?FiltroUsuariosDto $filtro = null;

    public int $detalles = 0;

    public function __construct(private readonly ?UsuarioDetalleDto $detalle) {}

    public function listar(FiltroUsuariosDto $filtro): Pagina
    {
        $this->filtro = $filtro;

        return new Pagina([], 0, $filtro->pagina, $filtro->porPagina);
    }

    public function detalle(string $uuid): ?UsuarioDetalleDto
    {
        $this->detalles++;

        return $this->detalle;
    }
}
