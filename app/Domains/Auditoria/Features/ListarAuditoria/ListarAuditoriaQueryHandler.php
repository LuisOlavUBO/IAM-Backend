<?php

namespace App\Domains\Auditoria\Features\ListarAuditoria;

use App\Domains\Auditoria\Domain\Dto\ActorDto;
use App\Domains\Auditoria\Domain\Dto\EntradaAuditoriaDto;
use App\Domains\Auditoria\Domain\Dto\FiltroAuditoriaDto;
use App\Domains\Auditoria\Domain\Interfaces\IAuditoriaQueryRepository;
use App\Domains\Usuarios\Contracts\IDirectorioUsuariosService;
use App\Shared\Paginacion\Pagina;

/**
 * Use case: read the audit log (spec auditoria). The name and email of the people come from Usuarios,
 * in one batch per page (design D3).
 */
final class ListarAuditoriaQueryHandler
{
    public function __construct(
        private readonly IAuditoriaQueryRepository $auditoria,
        private readonly IDirectorioUsuariosService $directorio,
    ) {}

    /**
     * @return Pagina<EntradaAuditoriaDto>
     */
    public function __invoke(FiltroAuditoriaDto $filtro): Pagina
    {
        $pagina = $this->auditoria->listar($filtro);

        $uuids = array_values(array_unique(array_filter(array_map(
            fn (EntradaAuditoriaDto $entrada) => $entrada->actor->uuid,
            $pagina->items,
        ))));
        $personas = $this->directorio->personas($uuids);

        $items = array_map(function (EntradaAuditoriaDto $entrada) use ($personas) {
            $persona = $entrada->actor->uuid === null ? null : ($personas[$entrada->actor->uuid] ?? null);

            return $persona === null
                ? $entrada
                : $entrada->conActor(new ActorDto($entrada->actor->tipo, $entrada->actor->uuid, $persona->nombre, $persona->correo));
        }, $pagina->items);

        return new Pagina($items, $pagina->total, $pagina->pagina, $pagina->porPagina);
    }
}
