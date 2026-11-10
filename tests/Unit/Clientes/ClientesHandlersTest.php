<?php

namespace Tests\Unit\Clientes;

use App\Domains\Clientes\Domain\ClienteNoEncontradoException;
use App\Domains\Clientes\Domain\Dto\ClienteDetalleDto;
use App\Domains\Clientes\Domain\Dto\FiltroClientesDto;
use App\Domains\Clientes\Domain\Interfaces\IClienteQueryRepository;
use App\Domains\Clientes\Features\ListarClientes\ListarClientesQueryHandler;
use App\Domains\Clientes\Features\VerCliente\VerClienteQueryHandler;
use App\Shared\Paginacion\Pagina;
use PHPUnit\Framework\TestCase;

/**
 * The use cases of Clientes against a fake IClienteQueryRepository, without a database (nor Laravel).
 */
class ClientesHandlersTest extends TestCase
{
    private const ID = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';

    public function test_the_filter_reaches_the_repository_as_is(): void
    {
        $repositorio = new ClienteQueryRepositoryFalso;
        $filtro = new FiltroClientesDto(buscar: 'portal', pagina: 2, porPagina: 5);

        (new ListarClientesQueryHandler($repositorio))($filtro);

        $this->assertSame($filtro, $repositorio->filtro);
    }

    public function test_an_id_that_is_not_a_uuid_is_not_found_without_asking_the_repository(): void
    {
        $repositorio = new ClienteQueryRepositoryFalso;

        try {
            (new VerClienteQueryHandler($repositorio))('no-es-uuid');
            $this->fail('Se esperaba ClienteNoEncontradoException.');
        } catch (ClienteNoEncontradoException) {
            $this->assertNull($repositorio->idPedido);
        }
    }

    public function test_an_unknown_client_is_not_found(): void
    {
        $this->expectException(ClienteNoEncontradoException::class);

        (new VerClienteQueryHandler(new ClienteQueryRepositoryFalso))(self::ID);
    }

    public function test_the_detail_comes_from_the_repository(): void
    {
        $detalle = new ClienteDetalleDto(self::ID, 'portal', 'fuas', [], false, [], false, null, null, []);

        $this->assertSame($detalle, (new VerClienteQueryHandler(new ClienteQueryRepositoryFalso($detalle)))(self::ID));
    }
}

/**
 * A fake read repository that records what the handlers ask.
 */
final class ClienteQueryRepositoryFalso implements IClienteQueryRepository
{
    public ?FiltroClientesDto $filtro = null;

    public ?string $idPedido = null;

    public function __construct(private readonly ?ClienteDetalleDto $detalle = null) {}

    public function listar(FiltroClientesDto $filtro): Pagina
    {
        $this->filtro = $filtro;

        return new Pagina([], 0, $filtro->pagina, $filtro->porPagina);
    }

    public function detalle(string $id): ?ClienteDetalleDto
    {
        $this->idPedido = $id;

        return $this->detalle;
    }
}
