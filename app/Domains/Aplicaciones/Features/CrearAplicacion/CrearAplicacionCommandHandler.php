<?php

namespace App\Domains\Aplicaciones\Features\CrearAplicacion;

use App\Domains\Aplicaciones\Domain\AplicacionExistenteException;
use App\Domains\Aplicaciones\Domain\Dto\AplicacionParaEscribirDto;
use App\Domains\Aplicaciones\Domain\Interfaces\ICatalogoRepository;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;

/**
 * Use case: register a new application, without permissions or roles (spec aplicaciones, "Crear y describir
 * aplicaciones"). The application and its audit entry, with the fields iam:install writes, are one
 * transaction.
 */
final class CrearAplicacionCommandHandler
{
    public function __construct(
        private readonly ICatalogoRepository $catalogo,
        private readonly IAuditor $auditoria,
    ) {}

    /** @param  string  $nombre  Already validated against ReglasDeCatalogo::FORMATO_APLICACION */
    public function __invoke(string $nombre, ?string $descripcion): AplicacionParaEscribirDto
    {
        if ($this->catalogo->aplicacion($nombre) !== null) {
            throw new AplicacionExistenteException;
        }

        return DB::transaction(function () use ($nombre, $descripcion) {
            $id = $this->catalogo->crearAplicacion($nombre, $descripcion);
            $this->auditoria->registrar('aplicacion.creacion', 'aplicacion', (string) $id, null, ['nombre' => $nombre, 'descripcion' => $descripcion]);

            return new AplicacionParaEscribirDto($id, $nombre, $descripcion);
        });
    }
}
