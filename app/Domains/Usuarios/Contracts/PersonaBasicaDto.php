<?php

namespace App\Domains\Usuarios\Contracts;

/**
 * The basic data of a person that other domains may read.
 */
final readonly class PersonaBasicaDto
{
    public function __construct(
        public string $nombre,
        public string $correo,
    ) {}
}
