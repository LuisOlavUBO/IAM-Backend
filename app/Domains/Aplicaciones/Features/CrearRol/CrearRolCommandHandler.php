<?php

namespace App\Domains\Aplicaciones\Features\CrearRol;

use App\Domains\Aplicaciones\Domain\AplicacionNoEncontradaException;
use App\Domains\Aplicaciones\Domain\Dto\RolParaEscribirDto;
use App\Domains\Aplicaciones\Domain\Interfaces\ICatalogoRepository;
use App\Domains\Aplicaciones\Domain\ReglasDeCatalogo;
use App\Domains\Aplicaciones\Domain\RolExistenteException;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use UBO\Common\Problems\Exceptions\DomainProblem;

/**
 * Use case: create a role of an application, without permissions (spec aplicaciones, "Crear y describir
 * roles"). Its name is unique within the application, without distinguishing case. Nothing is created in
 * iam-admin: the attempt is audited against the application. The role and its audit entry are one
 * transaction.
 */
final class CrearRolCommandHandler
{
    private const ACCION = 'rol.creacion';

    public function __construct(
        private readonly ICatalogoRepository $catalogo,
        private readonly IAuditor $auditoria,
    ) {}

    /** @param  string  $nombre  Already validated against ReglasDeCatalogo::FORMATO_ROL */
    public function __invoke(string $aplicacion, string $nombre, ?string $descripcion): RolParaEscribirDto
    {
        $app = $this->catalogo->aplicacion($aplicacion) ?? throw new AplicacionNoEncontradaException;

        try {
            ReglasDeCatalogo::verificarQueNoEsIamAdmin($app->nombre);
        } catch (DomainProblem $rechazo) {
            $this->auditoria->rechazar(self::ACCION, 'aplicacion', (string) $app->id, $rechazo->code());

            throw $rechazo;
        }

        if ($this->catalogo->rol($app->id, $nombre) !== null) {
            throw new RolExistenteException;
        }

        return DB::transaction(function () use ($app, $nombre, $descripcion) {
            $id = $this->catalogo->crearRol($app->id, $nombre, $descripcion);
            $rol = new RolParaEscribirDto($id, $app->nombre, $nombre, $descripcion);
            $this->auditoria->registrar(self::ACCION, 'rol', (string) $id, null, ['nombre' => $rol->nombreCompleto(), 'descripcion' => $descripcion]);

            return $rol;
        });
    }
}
