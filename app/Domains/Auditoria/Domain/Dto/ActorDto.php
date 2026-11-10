<?php

namespace App\Domains\Auditoria\Domain\Dto;

/**
 * Who did an audited action: a person (with uuid, and name and email while they exist) or a command.
 */
final readonly class ActorDto
{
    public function __construct(
        public string $tipo,
        public ?string $uuid,
        public ?string $nombre = null,
        public ?string $correo = null,
    ) {}
}
