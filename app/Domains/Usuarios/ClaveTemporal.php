<?php

namespace App\Domains\Usuarios;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;

/**
 * A temporary password: shown once to the administrator, who usually reads or types it to the
 * person, so it avoids look-alike characters. API-Auth forces the change on the first login.
 */
final readonly class ClaveTemporal
{
    /** Letters and digits without 0, O, 1, l and I. */
    public const ALFABETO = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private function __construct(
        public string $clave,
        public CarbonImmutable $venceEn,
    ) {}

    /**
     * Twelve random characters in three groups of four ("Xk7m-P2qr-9Wzt").
     */
    public static function generar(): self
    {
        $grupos = [];

        for ($g = 0; $g < 3; $g++) {
            $grupo = '';
            for ($i = 0; $i < 4; $i++) {
                $grupo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
            }
            $grupos[] = $grupo;
        }

        return new self(implode('-', $grupos), CarbonImmutable::now()->addHours((int) config('iam.clave_temporal_horas', 72)));
    }

    /**
     * The columns of users that store it: the bcrypt hash and its expiry.
     *
     * @return array{password: string, temporary_password_expires_at: CarbonImmutable}
     */
    public function columnas(): array
    {
        return ['password' => Hash::make($this->clave), 'temporary_password_expires_at' => $this->venceEn];
    }
}
