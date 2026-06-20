<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Security\SanitizationService;
use App\Services\Security\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test global security headers are injected into responses.
     */
    public function test_global_security_headers_are_present()
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');
        $response->assertHeader('Content-Security-Policy');
    }

    /**
     * Test that XSS protection service strips harmful tags and event handlers.
     */
    public function test_html_sanitizer_removes_malicious_code()
    {
        $sanitizer = app(SanitizationService::class);

        $maliciousHtml = "<p>Hello</p><script>alert('XSS');</script><div onclick='runXss()'>World</div><a href='javascript:exploit()'>Link</a>";
        $safeHtml = $sanitizer->sanitizeHtml($maliciousHtml);

        $this->assertStringNotContainsString('<script>', $safeHtml);
        $this->assertStringNotContainsString('onclick', $safeHtml);
        $this->assertStringNotContainsString('javascript:', $safeHtml);
        $this->assertStringContainsString('<p>Hello</p>', $safeHtml);
        $this->assertStringContainsString('<div>World</div>', $safeHtml);
    }

    /**
     * Test secure file upload validations.
     */
    public function test_file_upload_validates_and_renames_securely()
    {
        $uploadService = app(FileUploadService::class);

        // 1. Upload valid image
        $validFile = UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');
        $result = $uploadService->upload($validFile, 'avatars');

        $this->assertEquals('avatar.jpg', $result['filename']);
        $this->assertNotEquals('avatar.jpg', $result['secure_filename']); // UUID renamed
        $this->assertStringEndsWith('.jpg', $result['secure_filename']);
        
        // 2. Reject disallowed extensions
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $invalidFile = UploadedFile::fake()->create('exploit.php', 100, 'text/php');
        $uploadService->upload($invalidFile);
    }

    /**
     * Test double extensions are blocked.
     */
    public function test_file_upload_blocks_double_extensions()
    {
        $uploadService = app(FileUploadService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $doubleExtFile = UploadedFile::fake()->create('malicious.php.jpg', 100, 'image/jpeg');
        $uploadService->upload($doubleExtFile);
    }

    /**
     * Test that authentication failures log a security event.
     */
    public function test_failed_login_logs_security_event()
    {
        $user = User::factory()->create([
            'email' => 'victim@sfu.ca',
            'password' => Hash::make('correct_password'),
        ]);

        // Attempt login with incorrect password
        $response = $this->postJson(route('api.auth.login'), [
            'email' => 'victim@sfu.ca',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('security_events', [
            'event_type' => 'failed_login',
            'severity' => 'medium',
        ]);
        
        $event = SecurityEvent::where('event_type', 'failed_login')->first();
        $this->assertNotNull($event->ip_address);
        $this->assertNotNull($event->user_agent);
    }

    /**
     * Test rate limiters block abusive requests and log events.
     */
    public function test_auth_rate_limiting_blocks_after_limit()
    {
        RateLimiter::clear('auth');

        // Trigger rate limiter (limit: 5 attempts per minute)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson(route('api.auth.login'), [
                'email' => 'test@example.com',
                'password' => 'secret',
            ]);
        }

        $response->assertStatus(429);

        $this->assertDatabaseHas('security_events', [
            'event_type' => 'rate_limit_violation',
            'severity' => 'low',
        ]);
    }

    /**
     * Test Security Dashboard route permission constraints.
     */
    public function test_security_dashboard_requires_permission()
    {
        $volunteer = User::where('email', 'volunteer@example.com')->first();
        $admin = User::where('email', 'admin@example.com')->first();

        // 1. Volunteer access forbidden
        $response = $this->actingAs($volunteer)
            ->getJson(route('api.admin.security.dashboard'));
        $response->assertStatus(403);

        // 2. Admin access approved
        $response = $this->actingAs($admin)
            ->getJson(route('api.admin.security.dashboard'));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'metrics',
            'recent_security_events',
            'recent_audit_logs',
            'system_health',
            'chart_data',
        ]);
    }
}
