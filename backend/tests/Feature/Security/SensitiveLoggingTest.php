<?php

namespace Tests\Feature\Security;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Security\SecurityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SensitiveLoggingTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT = 'CurrentSecret#123';
    private const NEW = 'BrandNewSecret#456';

    public function test_rate_limited_password_change_does_not_persist_passwords(): void
    {
        $user = User::factory()->create(['password' => Hash::make(self::CURRENT)]);
        Sanctum::actingAs($user);
        Log::spy();

        $statuses = [];
        for ($i = 0; $i < 6; $i++) {
            $statuses[] = $this->putJson('/api/v1/account/password', [
                'current_password' => self::CURRENT.$i,
                'new_password' => self::NEW,
                'new_password_confirmation' => self::NEW,
            ])->status();
        }

        $this->assertSame(429, end($statuses), 'Statuses: '.implode(',', $statuses));

        $event = SecurityEvent::where('event_type', 'rate_limit_violation')->latest('id')->first();
        $this->assertNotNull($event);
        $stored = json_encode($event->payload);
        $this->assertStringNotContainsString(self::CURRENT, $stored);
        $this->assertStringNotContainsString(self::NEW, $stored);

        Log::shouldNotHaveReceived('info', [\Mockery::any(), \Mockery::on(
            fn ($context) => str_contains(json_encode($context), self::NEW) || str_contains(json_encode($context), self::CURRENT)
        )]);
    }

    public function test_scrubber_redacts_any_password_token_or_secret_like_key(): void
    {
        $event = app(SecurityLogger::class)->log('test_event', 'low', null, [
            'email' => 'person@sfu.ca',
            'current_password' => 'a',
            'new_password' => 'b',
            'new_password_confirmation' => 'c',
            'Password' => 'd',
            'access_token' => 'e',
            'refreshToken' => 'f',
            'X-XSRF-TOKEN' => 'g',
            'client_secret' => 'h',
            'stripe_secret_key' => 'i',
            'api_key' => 'j',
            'nested' => ['reset_token' => 'k', 'name' => 'kept'],
        ]);

        $payload = $event->fresh()->payload;

        $this->assertSame('person@sfu.ca', $payload['email']);
        $this->assertSame('kept', $payload['nested']['name']);
        foreach (['current_password', 'new_password', 'new_password_confirmation', 'Password', 'access_token', 'refreshToken', 'X-XSRF-TOKEN', 'client_secret', 'stripe_secret_key', 'api_key'] as $key) {
            $this->assertSame('[SCRUBBED]', $payload[$key], "{$key} was not scrubbed");
        }
        $this->assertSame('[SCRUBBED]', $payload['nested']['reset_token']);
    }
}
