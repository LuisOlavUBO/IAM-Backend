<?php

namespace App\Domains\Usuarios\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A person of IAM (table users, shared with API-Auth). Read-only in this change: no $fillable.
 * The credentials are hidden; the HTTP resources build the responses from a whitelist (design D2).
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property ?string $password
 * @property ?string $rut
 * @property ?string $entra_oid
 * @property ?Carbon $disabled_at
 * @property ?Carbon $temporary_password_expires_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class Usuario extends Model
{
    protected $table = 'users';

    protected $hidden = ['password', 'remember_token', 'entra_oid'];

    protected function casts(): array
    {
        return [
            'disabled_at' => 'datetime',
            'temporary_password_expires_at' => 'datetime',
        ];
    }

    public function estaDeBaja(): bool
    {
        return $this->disabled_at !== null;
    }

    public function tieneClave(): bool
    {
        return $this->password !== null;
    }

    /** Already signed in with their Microsoft account (API-Auth stores the Entra object id). */
    public function microsoftVinculado(): bool
    {
        return $this->entra_oid !== null;
    }
}
