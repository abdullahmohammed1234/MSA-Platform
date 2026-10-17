<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
    }

    public function test_up_returns_minimal_json_with_api_policy(): void
    {
        $response = $this->get('/up')->assertOk()->assertExactJson(['status' => 'up']);

        $this->assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertSame(SecurityHeaders::API_POLICY, $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('<script', $response->getContent());
    }

    public function test_up_does_not_start_a_session(): void
    {
        $response = $this->get('/up')->assertOk();

        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertNotSame(config('session.cookie'), $cookie->getName());
            $this->assertNotSame('XSRF-TOKEN', $cookie->getName());
        }
    }

    public function test_up_reports_failure_without_leaking_details(): void
    {
        Event::listen(DiagnosingHealth::class, fn () => throw new \RuntimeException('db-password-in-message'));

        $response = $this->get('/up')->assertStatus(500)->assertExactJson(['status' => 'down']);

        $this->assertStringNotContainsString('db-password-in-message', $response->getContent());
        $this->assertSame(SecurityHeaders::API_POLICY, $response->headers->get('Content-Security-Policy'));
    }

    public function test_up_stays_available_in_maintenance_mode(): void
    {
        config(['app.maintenance.driver' => 'cache', 'app.maintenance.store' => 'array']);
        $this->app->forgetInstance(\Illuminate\Foundation\MaintenanceModeManager::class);
        $this->app->maintenanceMode()->activate([]);

        try {
            $this->get('/up')->assertOk()->assertExactJson(['status' => 'up']);
            $this->get('/')->assertStatus(503);
        } finally {
            $this->app->maintenanceMode()->deactivate();
        }
    }
}
