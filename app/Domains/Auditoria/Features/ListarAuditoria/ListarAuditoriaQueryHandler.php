<?php

namespace App\Domains\Auditoria\Features\ListarAuditoria;

use App\Domains\Aplicaciones\Contracts\IDirectorioAplicacionesService;
use App\Domains\Auditoria\Domain\Dto\ActorDto;
use App\Domains\Auditoria\Domain\Dto\EntradaAuditoriaDto;
use App\Domains\Auditoria\Domain\Dto\FiltroAuditoriaDto;
use App\Domains\Auditoria\Domain\Interfaces\IAuditoriaQueryRepository;
use App\Domains\Clientes\Contracts\IDirectorioClientesService;
use App\Domains\Usuarios\Contracts\IDirectorioUsuariosService;
use App\Shared\Paginacion\Pagina;
use Illuminate\Support\Str;

/**
 * Use case: read the audit log (spec auditoria). The log keeps ids; the names of the actor and of the
 * entity come from the domains that own them, through their Contracts, in one batch per type and page
 * (design D3, D3b).
 */
final class ListarAuditoriaQueryHandler
{
    public function __construct(
        private readonly IAuditoriaQueryRepository $auditoria,
        private readonly IDirectorioUsuariosService $usuarios,
        private readonly IDirectorioAplicacionesService $aplicaciones,
        private readonly IDirectorioClientesService $clientes,
    ) {}

    /**
     * @return Pagina<EntradaAuditoriaDto>
     */
    public function __invoke(FiltroAuditoriaDto $filtro): Pagina
    {
        $pagina = $this->auditoria->listar($filtro);
        $entradas = $pagina->items;

        $actores = array_map(fn (EntradaAuditoriaDto $e) => $e->actor->uuid, $entradas);
        $personas = $this->usuarios->personas(self::uuids([...$actores, ...self::ids($entradas, 'usuario')]));

        $nombres = [
            'usuario' => array_map(fn ($persona) => $persona->nombre, $personas),
            'cliente' => $this->clientes->clientes(self::uuids(self::ids($entradas, 'cliente'))),
            'aplicacion' => $this->aplicaciones->aplicaciones(self::numeros(self::ids($entradas, 'aplicacion'))),
            'rol' => $this->aplicaciones->roles(self::numeros(self::ids($entradas, 'rol'))),
            'permiso' => $this->aplicaciones->permisos(self::numeros(self::ids($entradas, 'permiso'))),
        ];

        $items = array_map(function (EntradaAuditoriaDto $e) use ($personas, $nombres) {
            $persona = $e->actor->uuid === null ? null : ($personas[$e->actor->uuid] ?? null);
            $actor = new ActorDto($e->actor->tipo, $e->actor->uuid, $persona?->nombre, $persona?->correo);
            $clave = $e->entidadTipo === 'usuario' || $e->entidadTipo === 'cliente' ? Str::lower($e->entidadId) : $e->entidadId;

            return $e->completar($actor, $nombres[$e->entidadTipo][$clave] ?? null);
        }, $entradas);

        return new Pagina($items, $pagina->total, $pagina->pagina, $pagina->porPagina);
    }

    /**
     * The entity ids of one type on the page.
     *
     * @param  list<EntradaAuditoriaDto>  $entradas
     * @return list<string>
     */
    private static function ids(array $entradas, string $tipo): array
    {
        return array_values(array_map(
            fn (EntradaAuditoriaDto $e) => $e->entidadId,
            array_filter($entradas, fn (EntradaAuditoriaDto $e) => $e->entidadTipo === $tipo),
        ));
    }

    /**
     * Distinct lowercase UUIDs; anything else would not convert to uniqueidentifier in SQL Server.
     *
     * @param  list<?string>  $valores
     * @return list<string>
     */
    private static function uuids(array $valores): array
    {
        $uuids = array_map(fn (string $v) => Str::lower($v), array_filter($valores, fn (?string $v) => $v !== null && Str::isUuid($v)));

        return array_values(array_unique($uuids));
    }

    /**
     * Distinct numeric ids; anything else is not a row of Aplicaciones.
     *
     * @param  list<string>  $valores
     * @return list<int>
     */
    private static function numeros(array $valores): array
    {
        $numeros = array_map('intval', array_filter($valores, fn (string $v) => ctype_digit($v) && strlen($v) <= 18));

        return array_values(array_unique($numeros));
    }
}
