<?php

namespace App\Domains\Aplicaciones\Features\EditarAplicacion;

use App\Domains\Aplicaciones\Domain\AplicacionNoEncontradaException;
use App\Domains\Aplicaciones\Domain\Dto\AplicacionParaEscribirDto;
use App\Domains\Aplicaciones\Domain\Interfaces\ICatalogoRepository;
use App\Domains\Aplicaciones\Domain\ReglasDeCatalogo;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: change an application's description (spec aplicaciones, design D6 of aplicaciones-catalogo). The
 * same description changes nothing and is not audited; iam-admin is refused and the attempt audited.
 */
final class EditarAplicacionCommandHandler
{
    private const ACCION = 'aplicacion.edicion';

    public function __construct(
        private readonly ICatalogoRepository $catalogo,
        private readonly IAuditor $auditoria,
    ) {}

    public function __invoke(string $nombre, ?string $descripcion): AplicacionParaEscribirDto
    {
        $aplicacion = $this->catalogo->aplicacion($nombre) ?? throw new AplicacionNoEncontradaException;

        try {
            ReglasDeCatalogo::verificarQueNoEsIamAdmin($aplicacion->nombre);
        } catch (DomainProblem $rechazo) {
            $this->auditoria->rechazar(self::ACCION, 'aplicacion', (string) $aplicacion->id, $rechazo->code());

            throw $rechazo;
        }

        if ($aplicacion->descripcion === $descripcion) {
            return $aplicacion;
        }

        DB::transaction(function () use ($aplicacion, $descripcion) {
            $this->catalogo->describirAplicacion($aplicacion->id, $descripcion);
            $this->auditoria->registrar(self::ACCION, 'aplicacion', (string) $aplicacion->id, ['descripcion' => $aplicacion->descripcion], ['descripcion' => $descripcion]);
        });

        return new AplicacionParaEscribirDto($aplicacion->id, $aplicacion->nombre, $descripcion);
    }
}
