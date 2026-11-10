<?php

namespace Tests\Unit\Aplicaciones;

use App\Domains\Aplicaciones\Domain\AplicacionNoEncontradaException;
use App\Domains\Aplicaciones\Domain\Dto\AplicacionDetalleDto;
use App\Domains\Aplicaciones\Domain\Dto\FiltroAplicacionesDto;
use App\Domains\Aplicaciones\Domain\Interfaces\IAplicacionQueryRepository;
use App\Domains\Aplicaciones\Features\ListarAplicaciones\ListarAplicacionesQueryHandler;
use App\Domains\Aplicaciones\Features\VerAplicacion\VerAplicacionQueryHandler;
use App\Shared\Paginacion\Pagina;
use PHPUnit\Framework\TestCase;

/**
 * The use cases of Aplicaciones against a fake IAplicacionQueryRepository, without a database (nor Laravel).
 */
class AplicacionesHandlersTest extends TestCase
{
    public function test_the_filter_reaches_the_repository_as_is(): void
    {
        $repositorio = new AplicacionQueryRepositoryFalso;
        $filtro = new FiltroAplicacionesDto(buscar: 'honor', pagina: 2, porPagina: 5);

        (new ListarAplicacionesQueryHandler($repositorio))($filtro);

        $this->assertSame($filtro, $repositorio->filtro);
    }

    public function test_an_unknown_application_is_not_found(): void
    {
        $this->expectException(AplicacionNoEncontradaException::class);

        (new VerAplicacionQueryHandler(new AplicacionQueryRepositoryFalso))('no-existe');
    }

    public function test_the_detail_comes_from_the_repository(): void
    {
        $detalle = new AplicacionDetalleDto('fuas', 'FUAS', null, null, [], []);

        $this->assertSame($detalle, (new VerAplicacionQueryHandler(new AplicacionQueryRepositoryFalso($detalle)))('fuas'));
    }
}

/**
 * A fake read repository that records what the handlers ask.
 */
final class AplicacionQueryRepositoryFalso implements IAplicacionQueryRepository
{
    public ?FiltroAplicacionesDto $filtro = null;

    public function __construct(private readonly ?AplicacionDetalleDto $detalle = null) {}

    public function listar(FiltroAplicacionesDto $filtro): Pagina
    {
        $this->filtro = $filtro;

        return new Pagina([], 0, $filtro->pagina, $filtro->porPagina);
    }

    public function detalle(string $nombre): ?AplicacionDetalleDto
    {
        return $this->detalle;
    }
}
