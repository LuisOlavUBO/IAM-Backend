<?php

namespace App\Domains\Indicadores\Domain\Dto;

/**
 * Block `privilegios` (spec indicadores): the active people with iam-admin/administrador, and those
 * of them without activity in the last 30 days (never active included, with no date).
 */
final readonly class PrivilegiosDto
{
    /**
     * @param  list<PersonaInactivaDto>  $administradoresInactivos
     */
    public function __construct(
        public int $administradores,
        public array $administradoresInactivos,
    ) {}
}
