<?php

namespace App\Domains\Usuarios\Domain\Dto;

/**
 * What the portal asks of the list of people (spec usuarios). `rut` is the searched text already
 * normalized when it is a valid RUT; ListarUsuariosQueryHandler decides it.
 */
final readonly class FiltroUsuariosDto
{
    public const POR_PAGINA = 20;

    /**
     * @param  'activos'|'baja'|'todos'  $estado
     */
    public function __construct(
        public string $buscar = '',
        public string $estado = 'todos',
        public int $pagina = 1,
        public int $porPagina = self::POR_PAGINA,
        public ?string $rut = null,
    ) {}

    public function conRut(?string $rut): self
    {
        return new self($this->buscar, $this->estado, $this->pagina, $this->porPagina, $rut);
    }
}
