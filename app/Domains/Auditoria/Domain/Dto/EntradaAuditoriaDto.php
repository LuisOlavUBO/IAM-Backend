<?php

namespace App\Domains\Auditoria\Domain\Dto;

use Carbon\CarbonImmutable;

/**
 * One entry of the audit log as the portal shows it (spec auditoria).
 */
final readonly class EntradaAuditoriaDto
{
    /**
     * @param  array<array-key, mixed>|null  $antes
     * @param  array<array-key, mixed>|null  $despues
     */
    public function __construct(
        public CarbonImmutable $ocurridoEn,
        public ActorDto $actor,
        public string $accion,
        public string $entidadTipo,
        public string $entidadId,
        public string $resultado,
        public ?string $motivo,
        public ?array $antes,
        public ?array $despues,
        public ?string $traceId,
    ) {}

    public function conActor(ActorDto $actor): self
    {
        return new self(
            $this->ocurridoEn, $actor, $this->accion, $this->entidadTipo, $this->entidadId, $this->resultado,
            $this->motivo, $this->antes, $this->despues, $this->traceId,
        );
    }
}
