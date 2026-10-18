<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_check_endpoint_returns_healthy_status(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/health');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database' => ['status'],
                'cache' => ['status'],
                'storage' => ['status'],
            ],
        ]);
        $response->assertJsonPath('status', 'healthy');
    }

    public function test_health_check_hides_environment_details_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/health');

        $response->assertJsonMissingPath('php_version');
        $response->assertJsonMissingPath('laravel_version');
        $response->assertJsonMissingPath('checks.database.driver');
    }

    public function test_health_check_includes_details_in_debug_mode(): void
    {
        config(['app.debug' => true]);

        $response = $this->get('/health');

        $response->assertJsonStructure([
            'php_version',
            'laravel_version',
            'response_time_ms',
            'checks' => [
                'database' => ['status', 'driver', 'latency_ms'],
                'cache' => ['status', 'store'],
            ],
        ]);
    }

    public function test_api_health_endpoint_returns_json(): void
    {
        $response = $this->get('/api/health');

        $response->assertStatus(200);
        $response->assertJsonPath('checks.database.status', 'connected');
    }
}
