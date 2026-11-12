<?php

namespace App\Domains\Aplicaciones\Domain\Dto;

/**
 * An application found for a write. Its id is internal: it only reaches the audit log as entidad_id.
 */
final readonly class AplicacionParaEscribirDto
{
    public function __construct(
        public int $id,
        public string $nombre,
        public ?string $descripcion,
    ) {}
}
