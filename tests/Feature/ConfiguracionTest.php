<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Configuración que comparte con API-Auth o que evita sus tablas (design D7).
 */
class ConfiguracionTest extends TestCase
{
    public function test_passwords_and_secrets_are_hashed_with_bcrypt_like_api_auth(): void
    {
        $this->assertSame('bcrypt', config('hashing.driver'));
        $this->assertStringStartsWith('$2y$', Hash::make('secreto'));
    }

    public function test_the_example_environment_keeps_sessions_cache_and_queue_out_of_the_shared_database(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^SESSION_DRIVER=file$/m', $example);
        $this->assertMatchesRegularExpression('/^CACHE_STORE=file$/m', $example);
        $this->assertMatchesRegularExpression('/^QUEUE_CONNECTION=sync$/m', $example);
        $this->assertMatchesRegularExpression('/^BCRYPT_ROUNDS=12$/m', $example);
        $this->assertMatchesRegularExpression('/^DB_DATABASE=IAM$/m', $example);
    }
}
