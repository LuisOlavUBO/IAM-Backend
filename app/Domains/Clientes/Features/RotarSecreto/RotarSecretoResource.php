<?php

namespace App\Domains\Clientes\Features\RotarSecreto;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The rotated secret (SecretoRotado in OpenAPI): the secret, shown only here, and the variable of API-IAM's
 * .env to update, or null.
 *
 * @property SecretoRotadoDto $resource
 */
final class RotarSecretoResource extends JsonResource
{
    /**
     * @return array{secreto: string, variable_env: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'secreto' => $this->resource->secreto,
            'variable_env' => $this->resource->variableEnv,
        ];
    }
}
