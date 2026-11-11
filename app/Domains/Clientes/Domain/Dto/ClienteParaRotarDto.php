<?php

namespace App\Domains\Clientes\Domain\Dto;

/**
 * What the rotation of a secret needs to know of a client: never its secret nor its hash.
 */
final readonly class ClienteParaRotarDto
{
    public function __construct(
        public string $id,
        public string $nombre,
        public string $aplicacion,
        public bool $confidencial,
    ) {}
}
