<?php

namespace Tests\Unit\Auditoria;

use App\Domains\Aplicaciones\Contracts\IDirectorioAplicacionesService;
use App\Domains\Auditoria\Domain\Dto\ActorDto;
use App\Domains\Auditoria\Domain\Dto\EntradaAuditoriaDto;
use App\Domains\Auditoria\Domain\Dto\FiltroAuditoriaDto;
use App\Domains\Auditoria\Domain\Interfaces\IAuditoriaQueryRepository;
use App\Domains\Auditoria\Features\ListarAuditoria\ListarAuditoriaQueryHandler;
use App\Domains\Clientes\Contracts\IDirectorioClientesService;
use App\Domains\Usuarios\Contracts\IDirectorioUsuariosService;
use App\Domains\Usuarios\Contracts\PersonaBasicaDto;
use App\Shared\Paginacion\Pagina;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * ListarAuditoria against a fake repository and fake directories of the other domains, without a database.
 */
class AuditoriaHandlersTest extends TestCase
{
    private const ANA = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';

    private const LUIS = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5c';

    private const CLIENTE = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5d';

    private static function entrada(ActorDto $actor, string $tipo = 'rol', string $id = '7'): EntradaAuditoriaDto
    {
        return new EntradaAuditoriaDto(CarbonImmutable::now(), $actor, 'rol.creacion', $tipo, $id, 'ok', null, null, null, null);
    }

    private static function comando(string $tipo, string $id): EntradaAuditoriaDto
    {
        return self::entrada(new ActorDto('comando', null), $tipo, $id);
    }

    public function test_the_people_of_the_page_are_asked_once_and_completed(): void
    {
        $repositorio = new AuditoriaQueryRepositoryFalso([
            self::entrada(new ActorDto('persona', self::ANA)),
            self::entrada(new ActorDto('comando', null)),
            self::entrada(new ActorDto('persona', self::ANA)),
            self::entrada(new ActorDto('persona', self::LUIS)),
        ]);
        $usuarios = new DirectorioUsuariosFalso([self::ANA => new PersonaBasicaDto('Ana Pérez', 'ana@ubo.cl')]);
        $filtro = new FiltroAuditoriaDto(accion: 'cliente.', pagina: 2, porPagina: 4);

        $pagina = (new ListarAuditoriaQueryHandler($repositorio, $usuarios, new DirectorioAplicacionesFalso, new DirectorioClientesFalso))($filtro);

        $this->assertSame($filtro, $repositorio->filtro);
        $this->assertSame([[self::ANA, self::LUIS]], $usuarios->pedidos);
        $this->assertEquals(new ActorDto('persona', self::ANA, 'Ana Pérez', 'ana@ubo.cl'), $pagina->items[0]->actor);
        $this->assertEquals(new ActorDto('comando', null), $pagina->items[1]->actor);
        $this->assertEquals(new ActorDto('persona', self::LUIS), $pagina->items[3]->actor);
        $this->assertSame([4, 2, 4], [$pagina->total, $pagina->pagina, $pagina->porPagina]);
    }

    public function test_entity_names_are_asked_once_per_type_and_invalid_ids_are_not_asked(): void
    {
        $repositorio = new AuditoriaQueryRepositoryFalso([
            self::comando('rol', '16'),
            self::comando('rol', '16'),
            self::comando('rol', 'x16'),
            self::comando('aplicacion', '7'),
            self::comando('permiso', '3'),
            self::comando('usuario', strtoupper(self::ANA)),
            self::comando('usuario', 'no-uuid'),
            self::comando('cliente', self::CLIENTE),
            self::comando('cliente', '42'),
            self::comando('otra', '1'),
        ]);
        $usuarios = new DirectorioUsuariosFalso([self::ANA => new PersonaBasicaDto('Ana Pérez', 'ana@ubo.cl')]);
        $aplicaciones = new DirectorioAplicacionesFalso;
        $clientes = new DirectorioClientesFalso;

        $pagina = (new ListarAuditoriaQueryHandler($repositorio, $usuarios, $aplicaciones, $clientes))(new FiltroAuditoriaDto);

        $this->assertSame([[self::ANA]], $usuarios->pedidos);
        $this->assertSame(['aplicaciones' => [[7]], 'roles' => [[16]], 'permisos' => [[3]]], $aplicaciones->pedidos);
        $this->assertSame([[self::CLIENTE]], $clientes->pedidos);
        $this->assertSame(
            ['iam-admin/mesa-de-ayuda', 'iam-admin/mesa-de-ayuda', null, 'iam-admin', 'iam:usuarios.leer', 'Ana Pérez', null, 'iam-admin-sistema', null, null],
            array_map(fn (EntradaAuditoriaDto $e) => $e->entidadNombre, $pagina->items),
        );
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
final class DirectorioUsuariosFalso implements IDirectorioUsuariosService
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

/**
 * A fake directory of Aplicaciones with application 7, role 16 and permission 3.
 */
final class DirectorioAplicacionesFalso implements IDirectorioAplicacionesService
{
    /** @var array<string, list<list<int>>> */
    public array $pedidos = [];

    public function aplicaciones(array $ids): array
    {
        return $this->responder('aplicaciones', $ids, [7 => 'iam-admin']);
    }

    public function roles(array $ids): array
    {
        return $this->responder('roles', $ids, [16 => 'iam-admin/mesa-de-ayuda']);
    }

    public function permisos(array $ids): array
    {
        return $this->responder('permisos', $ids, [3 => 'iam:usuarios.leer']);
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, string>  $nombres
     * @return array<int, string>
     */
    private function responder(string $metodo, array $ids, array $nombres): array
    {
        if ($ids !== []) {
            $this->pedidos[$metodo][] = $ids;
        }

        return array_intersect_key($nombres, array_flip($ids));
    }
}

/**
 * A fake directory of Clientes that knows one client.
 */
final class DirectorioClientesFalso implements IDirectorioClientesService
{
    /** @var list<list<string>> */
    public array $pedidos = [];

    public function clientes(array $ids): array
    {
        if ($ids !== []) {
            $this->pedidos[] = $ids;
        }

        return array_intersect_key(['0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5d' => 'iam-admin-sistema'], array_flip($ids));
    }
}
