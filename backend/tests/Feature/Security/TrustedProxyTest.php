<?php

namespace Tests\Feature\Security;

use App\Support\TrustedProxies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    private const PROXY = '10.0.0.5';
    private const CLIENT = '203.0.113.9';
    private const SPOOFED = '198.51.100.77';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__proxy-probe', fn (Request $request) => response()->json([
            'ip' => $request->ip(),
            'secure' => $request->secure(),
            'url' => url('/x'),
        ]));
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    private function trust(?string $raw): void
    {
        TrustProxies::at(TrustedProxies::resolve($raw));
    }

    private function probe(string $remoteAddr): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $remoteAddr])
            ->withHeaders([
                'X-Forwarded-For' => self::SPOOFED,
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Port' => '443',
            ])
            ->getJson('/__proxy-probe');
    }

    public function test_resolver_fails_closed_for_unset_empty_and_blank(): void
    {
        $this->assertSame([], TrustedProxies::resolve(null));
        $this->assertSame([], TrustedProxies::resolve(''));
        $this->assertSame([], TrustedProxies::resolve('   '));
    }

    public function test_resolver_accepts_ipv4_ipv6_and_cidr(): void
    {
        $this->assertSame(
            ['10.0.0.5', '192.168.0.0/16', '2001:db8::1', '2001:db8::/32'],
            TrustedProxies::resolve(' 10.0.0.5, 192.168.0.0/16 ,2001:db8::1,2001:db8::/32 ')
        );
    }

    public function test_resolver_drops_invalid_entries(): void
    {
        $this->assertSame(['10.0.0.5'], TrustedProxies::resolve('not-an-ip, 10.0.0.5, 10.0.0.0/33, 300.1.1.1, REMOTE_ADDR, 10.0.0.0/x'));
        $this->assertSame([], TrustedProxies::resolve('garbage'));
        $this->assertSame([], TrustedProxies::resolve('**'));
    }

    public function test_resolver_honours_wildcard_only_when_it_is_the_whole_value(): void
    {
        $this->assertSame('*', TrustedProxies::resolve('*'));
        $this->assertSame(['10.0.0.5'], TrustedProxies::resolve('*, 10.0.0.5'));
    }

    public function test_bootstrap_uses_the_resolver(): void
    {
        $property = new \ReflectionProperty(TrustProxies::class, 'alwaysTrustProxies');

        $this->assertSame(TrustedProxies::resolve(env('TRUSTED_PROXIES')), $property->getValue());
    }

    public function test_unset_configuration_ignores_forwarded_headers(): void
    {
        $this->trust(null);

        $this->probe(self::CLIENT)->assertOk()
            ->assertJsonPath('ip', self::CLIENT)
            ->assertJsonPath('secure', false);
    }

    public function test_invalid_configuration_ignores_forwarded_headers(): void
    {
        $this->trust('garbage, 999.0.0.1');

        $this->probe(self::PROXY)->assertOk()
            ->assertJsonPath('ip', self::PROXY)
            ->assertJsonPath('secure', false);
    }

    public function test_configured_proxy_forwards_client_ip_and_https(): void
    {
        $this->trust(self::PROXY);

        $this->probe(self::PROXY)->assertOk()
            ->assertJsonPath('ip', self::SPOOFED)
            ->assertJsonPath('secure', true);
    }

    public function test_configured_cidr_matches_proxy(): void
    {
        $this->trust('10.0.0.0/24');

        $this->probe(self::PROXY)->assertOk()
            ->assertJsonPath('ip', self::SPOOFED)
            ->assertJsonPath('secure', true);
    }

    public function test_configured_proxy_does_not_trust_other_peers(): void
    {
        $this->trust(self::PROXY);

        $this->probe(self::CLIENT)->assertOk()
            ->assertJsonPath('ip', self::CLIENT)
            ->assertJsonPath('secure', false);
    }

    public function test_explicit_wildcard_trusts_any_peer(): void
    {
        $this->trust('*');

        $this->probe(self::CLIENT)->assertOk()
            ->assertJsonPath('ip', self::SPOOFED)
            ->assertJsonPath('secure', true);
    }

    public function test_spoofed_forwarded_for_cannot_bypass_login_throttle_by_default(): void
    {
        $this->trust(null);
        $statuses = [];

        for ($i = 1; $i <= 6; $i++) {
            $statuses[] = $this->withServerVariables(['REMOTE_ADDR' => self::CLIENT])
                ->withHeaders(['X-Forwarded-For' => "198.51.100.{$i}"])
                ->postJson('/api/v1/auth/login', [
                    'email' => 'nobody@sfu.ca',
                    'password' => 'wrong-password',
                ])->status();
        }

        $this->assertSame(429, end($statuses), 'Statuses: '.implode(',', $statuses));
    }
}
