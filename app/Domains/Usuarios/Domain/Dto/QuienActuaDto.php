<?php

namespace App\Domains\Usuarios\Domain\Dto;

/**
 * The person of the portal's session who asks for a change: their uuid (lowercase) and their iam:*
 * permissions, as their token carries them.
 */
final readonly class QuienActuaDto
{
    /**
     * @param  list<string>  $permisos
     */
    public function __construct(
        public string $uuid,
        public array $permisos,
    ) {}
}
