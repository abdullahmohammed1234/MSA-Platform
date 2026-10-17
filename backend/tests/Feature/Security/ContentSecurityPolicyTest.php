<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);

        Route::get('/__csp/boom', fn () => throw new \RuntimeException('boom'));
        Route::get('/__csp/pdf', fn () => response('%PDF-1.4 test', 200, ['Content-Type' => 'application/pdf']));
        Route::get('/__csp/download', fn () => response()->download(base_path('composer.json')));
    }

    private function policy(TestResponse $response): string
    {
        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertIsString($policy, 'Content-Security-Policy header missing');

        return $policy;
    }

    private function directive(string $policy, string $name): ?string
    {
        foreach (explode(';', $policy) as $part) {
            $part = trim($part);
            if ($part === $name || str_starts_with($part, $name.' ')) {
                return $part;
            }
        }

        return null;
    }

    private function assertNoUnsafeScript(string $policy): void
    {
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        $script = $this->directive($policy, 'script-src') ?? $this->directive($policy, 'default-src');
        $this->assertNotNull($script);
        $this->assertStringNotContainsString("'unsafe-inline'", $script);
    }

    public function test_unauthenticated_api_response_uses_locked_down_policy(): void
    {
        $response = $this->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->assertSame(SecurityHeaders::API_POLICY, $this->policy($response));
    }

    public function test_authenticated_api_response_uses_locked_down_policy(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/auth/me')->assertOk();

        $this->assertSame(SecurityHeaders::API_POLICY, $this->policy($response));
    }

    public function test_auth_validation_error_uses_locked_down_policy(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [])->assertStatus(422);

        $this->assertSame(SecurityHeaders::API_POLICY, $this->policy($response));
    }

    public function test_api_not_found_uses_locked_down_policy(): void
    {
        $response = $this->getJson('/api/v1/__does-not-exist')->assertNotFound();

        $this->assertSame(SecurityHeaders::API_POLICY, $this->policy($response));
    }

    public function test_json_server_error_uses_locked_down_policy_and_hides_details(): void
    {
        $response = $this->getJson('/__csp/boom')->assertStatus(500);

        $this->assertSame(SecurityHeaders::API_POLICY, $this->policy($response));
        $this->assertStringNotContainsString('boom', $response->getContent());
        $this->assertStringNotContainsString('RuntimeException', $response->getContent());
    }

    public function test_html_server_error_page_has_no_unsafe_script(): void
    {
        $response = $this->get('/__csp/boom')->assertStatus(500);

        $policy = $this->policy($response);
        $this->assertSame(SecurityHeaders::HTML_POLICY, $policy);
        $this->assertNoUnsafeScript($policy);
        $this->assertStringNotContainsString('RuntimeException', $response->getContent());
    }

    public function test_welcome_page_has_no_unsafe_script(): void
    {
        $response = $this->get('/')->assertOk();

        $policy = $this->policy($response);
        $this->assertSame(SecurityHeaders::HTML_POLICY, $policy);
        $this->assertNoUnsafeScript($policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
    }

    public function test_debug_html_allows_inline_script_only_when_debug_is_enabled(): void
    {
        config(['app.debug' => true]);

        $policy = $this->policy($this->get('/')->assertOk());

        $this->assertSame(SecurityHeaders::DEBUG_HTML_POLICY, $policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
    }

    public function test_debug_mode_does_not_relax_api_policy(): void
    {
        config(['app.debug' => true]);

        $this->assertSame(SecurityHeaders::API_POLICY, $this->policy($this->getJson('/api/v1/auth/me')));
    }

    public function test_inline_pdf_only_restricts_framing(): void
    {
        $response = $this->get('/__csp/pdf')->assertOk();

        $this->assertSame(SecurityHeaders::PDF_POLICY, $this->policy($response));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_file_download_gets_nosniff(): void
    {
        $this->get('/__csp/download')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    private function assertNoInlineStyle(TestResponse $response): void
    {
        $style = $this->directive($this->policy($response), 'style-src');
        $this->assertNotNull($style);
        $this->assertStringNotContainsString("'unsafe-inline'", $style);

        $html = $response->getContent();
        $this->assertStringNotContainsString('<style', $html);
        $this->assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $html);
    }

    public function test_welcome_page_styles_are_external(): void
    {
        $response = $this->get('/')->assertOk();

        $this->assertNoInlineStyle($response);
        $this->assertStringContainsString('<link rel="stylesheet" href="/css/welcome.css">', $response->getContent());
        $this->assertFileExists(public_path('css/welcome.css'));
    }

    public static function htmlErrorStatuses(): array
    {
        return array_map(fn ($code) => [$code], [401, 403, 404, 405, 409, 413, 419, 422, 429, 500, 502, 503]);
    }

    #[DataProvider('htmlErrorStatuses')]
    public function test_html_error_pages_have_no_inline_style(int $code): void
    {
        Route::get('/__csp/abort/{code}', fn (int $code) => abort($code));

        $response = $this->get("/__csp/abort/{$code}")->assertStatus($code);

        $this->assertSame(SecurityHeaders::HTML_POLICY, $this->policy($response));
        $this->assertNoInlineStyle($response);
        $this->assertStringContainsString('/css/errors.css', $response->getContent());
        $this->assertFileExists(public_path('css/errors.css'));
    }

    public function test_method_not_allowed_page_has_no_inline_style(): void
    {
        $response = $this->post('/__csp/pdf')->assertStatus(405);

        $this->assertNoInlineStyle($response);
    }

    public function test_no_policy_contains_unsafe_eval(): void
    {
        foreach ([SecurityHeaders::API_POLICY, SecurityHeaders::HTML_POLICY, SecurityHeaders::DEBUG_HTML_POLICY, SecurityHeaders::PDF_POLICY] as $policy) {
            $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        }
    }

    public function test_hsts_is_sent_when_https_is_forced(): void
    {
        config(['app.force_https' => true]);

        $this->getJson('/api/v1/auth/me')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
    }

    public function test_hsts_is_not_sent_over_plain_http_without_force(): void
    {
        config(['app.force_https' => false]);

        $this->getJson('/api/v1/auth/me')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_spoofed_forwarded_proto_does_not_trigger_hsts_by_default(): void
    {
        config(['app.force_https' => false]);

        $this->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->getJson('/api/v1/auth/me')
            ->assertHeaderMissing('Strict-Transport-Security');
    }
}
