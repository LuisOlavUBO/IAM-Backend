<?php

namespace App\Domains\Clientes\Features\RotarSecreto;

/**
 * The new secret of a client, shown once and never logged or audited, and the variable of API-IAM's .env
 * to update when the client is one of iam-admin (null otherwise).
 */
final readonly class SecretoRotadoDto
{
    public function __construct(
        public string $secreto,
        public ?string $variableEnv,
    ) {}
}
