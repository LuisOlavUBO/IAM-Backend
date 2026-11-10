<?php

namespace App\Domains\Usuarios\Features\CrearUsuario;

use App\Domains\Usuarios\Domain\ClaveTemporal;
use App\Domains\Usuarios\Domain\CorreoEnUsoException;
use App\Domains\Usuarios\Domain\Dto\NuevaPersonaDto;
use App\Domains\Usuarios\Domain\Dto\PersonaCreadaDto;
use App\Domains\Usuarios\Domain\Interfaces\IPersonasRepository;
use App\Domains\Usuarios\Domain\RutEnUsoException;
use App\Shared\Auditoria\IAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Use case: create a person from the portal (spec usuarios, design D4 and D5). The person and their audit
 * entry are saved in one transaction. The temporary password lives only in memory and in the response: it
 * never reaches the audit log or the logs.
 */
final class CrearUsuarioCommandHandler
{
    public function __construct(
        private readonly IPersonasRepository $personas,
        private readonly IAuditor $auditoria,
    ) {}

    public function __invoke(NuevaPersonaDto $persona): PersonaCreadaDto
    {
        if ($this->personas->correoEnUso($persona->correo)) {
            throw new CorreoEnUsoException;
        }

        if ($persona->rut !== null && $this->personas->rutEnUso($persona->rut)) {
            throw new RutEnUsoException;
        }

        $uuid = Str::lower((string) Str::uuid7());
        $clave = $persona->acceso === NuevaPersonaDto::ACCESO_CLAVE ? ClaveTemporal::generar() : null;

        DB::transaction(function () use ($uuid, $persona, $clave) {
            $this->personas->crear($uuid, $persona, $clave);
            $this->auditoria->registrar('usuario.creacion', 'usuario', $uuid, null, [
                'nombre' => $persona->nombre, 'correo' => $persona->correo, 'rut' => $persona->rut, 'acceso' => $persona->acceso,
            ]);
        });

        return new PersonaCreadaDto($uuid, $clave);
    }
}
