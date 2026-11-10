<?php

namespace Tests\Unit\Auditoria;

use App\Domains\Auditoria\Domain\Dto\ActorDto;
use App\Domains\Auditoria\Domain\Dto\EntradaAuditoriaDto;
use App\Domains\Auditoria\Domain\Dto\FiltroAuditoriaDto;
use App\Domains\Auditoria\Domain\Interfaces\IAuditoriaQueryRepository;
use App\Domains\Auditoria\Features\ListarAuditoria\ListarAuditoriaQueryHandler;
use App\Domains\Usuarios\Contracts\IDirectorioUsuariosService;
use App\Domains\Usuarios\Contracts\PersonaBasicaDto;
use App\Shared\Paginacion\Pagina;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * ListarAuditoria against a fake repository and a fake directory of Usuarios, without a database.
 */
class AuditoriaHandlersTest extends TestCase
{
    private const ANA = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';

    private const LUIS = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5c';

    private static function entrada(ActorDto $actor): EntradaAuditoriaDto
    {
        return new EntradaAuditoriaDto(CarbonImmutable::now(), $actor, 'rol.creacion', 'rol', '7', 'ok', null, null, null, null);
    }

    public function test_the_people_of_the_page_are_asked_once_and_completed(): void
    {
        $repositorio = new AuditoriaQueryRepositoryFalso([
            self::entrada(new ActorDto('persona', self::ANA)),
            self::entrada(new ActorDto('comando', null)),
            self::entrada(new ActorDto('persona', self::ANA)),
            self::entrada(new ActorDto('persona', self::LUIS)),
        ]);
        $directorio = new DirectorioFalso([self::ANA => new PersonaBasicaDto('Ana Pérez', 'ana@ubo.cl')]);
        $filtro = new FiltroAuditoriaDto(accion: 'cliente.', pagina: 2, porPagina: 4);

        $pagina = (new ListarAuditoriaQueryHandler($repositorio, $directorio))($filtro);

        $this->assertSame($filtro, $repositorio->filtro);
        $this->assertSame([[self::ANA, self::LUIS]], $directorio->pedidos);
        $this->assertEquals(new ActorDto('persona', self::ANA, 'Ana Pérez', 'ana@ubo.cl'), $pagina->items[0]->actor);
        $this->assertEquals(new ActorDto('comando', null), $pagina->items[1]->actor);
        $this->assertEquals(new ActorDto('persona', self::LUIS), $pagina->items[3]->actor);
        $this->assertSame([4, 2, 4], [$pagina->total, $pagina->pagina, $pagina->porPagina]);
    }

    public function test_without_people_the_directory_gets_an_empty_list(): void
    {
        $directorio = new DirectorioFalso([]);

        (new ListarAuditoriaQueryHandler(new AuditoriaQueryRepositoryFalso([self::entrada(new ActorDto('comando', null))]), $directorio))(new FiltroAuditoriaDto);

        $this->assertSame([[]], $directorio->pedidos);
    }
}

/**
 * A fake read repository that returns fixed entries and records the filter.
 */
final class AuditoriaQueryRepositoryFalso implements IAuditoriaQueryRepository
{
    public ?FiltroAuditoriaDto $filtro = null;

    /**
     * @param  list<EntradaAuditoriaDto>  $entradas
     */
    public function __construct(private readonly array $entradas) {}

    public function listar(FiltroAuditoriaDto $filtro): Pagina
    {
        $this->filtro = $filtro;

        return new Pagina($this->entradas, count($this->entradas), $filtro->pagina, $filtro->porPagina);
    }
}

/**
 * A fake directory of Usuarios that records each batch it is asked.
 */
final class DirectorioFalso implements IDirectorioUsuariosService
{
    /** @var list<list<string>> */
    public array $pedidos = [];

    /**
     * @param  array<string, PersonaBasicaDto>  $personas
     */
    public function __construct(private readonly array $personas) {}

    public function persona(string $uuid): ?PersonaBasicaDto
    {
        return $this->personas[$uuid] ?? null;
    }

    public function personas(array $uuids): array
    {
        $this->pedidos[] = $uuids;

        return array_intersect_key($this->personas, array_flip($uuids));
    }
}
