<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class DocsTest extends TestCase
{
    public function test_swagger_ui_loads_the_openapi_spec(): void
    {
        $this->get('/api/iam/docs/swagger')
            ->assertOk()
            ->assertSee('swagger-ui', false)
            ->assertSee('/api/iam/docs/openapi.yaml', false);
    }

    public function test_the_openapi_spec_is_served_as_yaml(): void
    {
        $response = $this->get('/api/iam/docs/openapi.yaml')->assertOk();

        $this->assertStringStartsWith('application/yaml', $response->headers->get('Content-Type'));
        $this->assertSame('3.1.0', Yaml::parse($response->streamedContent())['openapi']);
    }

    /**
     * Every /api/iam route (the docs aside) is documented and every documented path exists.
     */
    public function test_the_spec_matches_the_api_routes(): void
    {
        $documented = array_keys(Yaml::parseFile(base_path('docs/openapi/openapi.yaml'))['paths']);

        $apiRoutes = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => '/'.$route->uri())
            ->filter(fn (string $uri) => str_starts_with($uri, '/api/iam/') && ! str_starts_with($uri, '/api/iam/docs/'))
            ->unique()
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing($apiRoutes, $documented);
    }
}
