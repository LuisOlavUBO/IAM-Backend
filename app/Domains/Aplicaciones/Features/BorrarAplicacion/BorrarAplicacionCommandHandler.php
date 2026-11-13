<?php

namespace App\Domains\Aplicaciones\Features\BorrarAplicacion;

use App\Domains\Aplicaciones\Domain\AplicacionNoEncontradaException;
use App\Domains\Aplicaciones\Domain\EnUsoException;
use App\Domains\Aplicaciones\Domain\Interfaces\ICatalogoRepository;
use App\Domains\Aplicaciones\Domain\ReglasDeCatalogo;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: delete an application created by mistake (spec aplicaciones, "Borrar lo que no está en uso"; design
 * D3 of aplicaciones-renombrar-y-borrar). With permissions, roles or clients it is in use and nothing is deleted.
 * iam-admin is refused and the attempt audited. The deletion and its audit entry are one transaction.
 */
final class BorrarAplicacionCommandHandler
{
    private const ACCION = 'aplicacion.borrado';

    public function __construct(
        private readonly ICatalogoRepository $catalogo,
        private readonly IAuditor $auditoria,
    ) {}

    public function __invoke(string $nombre): void
    {
        $app = $this->catalogo->aplicacion($nombre) ?? throw new AplicacionNoEncontradaException;

        try {
            ReglasDeCatalogo::verificarQueNoEsIamAdmin($app->nombre);
        } catch (DomainProblem $rechazo) {
            $this->auditoria->rechazar(self::ACCION, 'aplicacion', (string) $app->id, $rechazo->code());

            throw $rechazo;
        }

        $impacto = $this->catalogo->impactoDeAplicacion($app->id);
        if ($impacto->enUso) {
            throw new EnUsoException($impacto);
        }

        DB::transaction(function () use ($app) {
            $this->catalogo->borrarAplicacion($app->id);
            $this->auditoria->registrar(self::ACCION, 'aplicacion', (string) $app->id, ['nombre' => $app->nombre, 'descripcion' => $app->descripcion]);
        });
    }
}
