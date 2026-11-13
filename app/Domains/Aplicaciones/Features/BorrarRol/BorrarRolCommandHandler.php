<?php

namespace App\Domains\Aplicaciones\Features\BorrarRol;

use App\Domains\Aplicaciones\Domain\AplicacionNoEncontradaException;
use App\Domains\Aplicaciones\Domain\EnUsoException;
use App\Domains\Aplicaciones\Domain\Interfaces\ICatalogoRepository;
use App\Domains\Aplicaciones\Domain\ReglasDeCatalogo;
use App\Domains\Aplicaciones\Domain\RolNoEncontradoException;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: delete a role created by mistake (spec aplicaciones, "Borrar lo que no está en uso"; design D3 of
 * aplicaciones-renombrar-y-borrar). While some person or client has it, it is in use and nothing is deleted; the
 * permissions it granted stop being granted (cascade of oauth_scope_role) but remain. The roles of iam-admin are
 * refused and the attempt audited.
 */
final class BorrarRolCommandHandler
{
    private const ACCION = 'rol.borrado';

    public function __construct(
        private readonly ICatalogoRepository $catalogo,
        private readonly IAuditor $auditoria,
    ) {}

    public function __invoke(string $aplicacion, string $nombre): void
    {
        $app = $this->catalogo->aplicacion($aplicacion) ?? throw new AplicacionNoEncontradaException;
        $rol = $this->catalogo->rol($app->id, $nombre) ?? throw new RolNoEncontradoException;

        try {
            ReglasDeCatalogo::verificarQueNoEsIamAdmin($rol->aplicacion);
        } catch (DomainProblem $rechazo) {
            $this->auditoria->rechazar(self::ACCION, 'rol', (string) $rol->id, $rechazo->code());

            throw $rechazo;
        }

        $impacto = $this->catalogo->impactoDeRol($rol->id);
        if ($impacto->enUso) {
            throw new EnUsoException($impacto);
        }

        DB::transaction(function () use ($rol) {
            $this->catalogo->borrarRol($rol->id);
            $this->auditoria->registrar(self::ACCION, 'rol', (string) $rol->id, ['nombre' => $rol->nombreCompleto(), 'descripcion' => $rol->descripcion]);
        });
    }
}
