<?php

namespace Tests\Feature\Security;

use App\Donations\Enums\DonationStatus;
use App\Donations\Models\Donation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DonationStatusPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function donation(array $attributes = []): Donation
    {
        return Donation::create(array_merge([
            'donor_name' => 'Maryam Khan',
            'donor_email' => 'maryam.private@example.com',
            'amount_cents' => 2500,
            'status' => DonationStatus::Paid,
            'is_anonymous' => false,
            'dedication' => 'In memory of my grandmother',
        ], $attributes));
    }

    public function test_public_status_returns_only_what_the_receipt_page_needs(): void
    {
        $donation = $this->donation();

        $response = $this->getJson("/api/v1/donations/{$donation->uuid}/status")
            ->assertOk()
            ->assertJsonPath('donation.donation_number', $donation->donation_number)
            ->assertJsonPath('donation.status', 'paid')
            ->assertJsonPath('donation.donor_name', 'Maryam Khan')
            ->assertJsonMissingPath('donation.donor_email')
            ->assertJsonMissingPath('donation.dedication');

        $this->assertStringNotContainsString('maryam.private@example.com', $response->getContent());
        $this->assertStringNotContainsString('grandmother', $response->getContent());
    }

    public function test_anonymous_donation_hides_the_name(): void
    {
        $donation = $this->donation(['is_anonymous' => true]);

        $response = $this->getJson("/api/v1/donations/{$donation->uuid}/status")
            ->assertOk()
            ->assertJsonPath('donation.donor_name', 'Anonymous');

        $this->assertStringNotContainsString('Maryam', $response->getContent());
    }

    public function test_another_signed_in_donor_gets_no_more_than_the_public_view(): void
    {
        $donation = $this->donation();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/donations/{$donation->uuid}/status")
            ->assertOk()
            ->assertJsonMissingPath('donation.donor_email');
    }

    public function test_guessed_or_malformed_identifiers_are_not_found(): void
    {
        $this->donation();

        $this->getJson('/api/v1/donations/'.Str::uuid().'/status')->assertNotFound();
        $this->getJson('/api/v1/donations/1/status')->assertNotFound();
    }

    public function test_status_lookups_are_rate_limited(): void
    {
        $donation = $this->donation();

        for ($i = 0; $i < 30; $i++) {
            $this->getJson("/api/v1/donations/{$donation->uuid}/status")->assertOk();
        }

        $this->getJson("/api/v1/donations/{$donation->uuid}/status")->assertStatus(429);
    }

    public function test_public_callers_cannot_change_a_donation_status(): void
    {
        $donation = $this->donation(['status' => DonationStatus::Pending]);

        $this->postJson("/api/v1/donations/{$donation->uuid}/status", ['status' => 'paid'])->assertStatus(405);
        $this->putJson("/api/v1/donations/{$donation->uuid}/status", ['status' => 'paid'])->assertStatus(405);
        $this->postJson("/api/v1/donations/admin/donations/{$donation->uuid}/refund", ['reason' => 'x'])->assertUnauthorized();

        $this->assertSame(DonationStatus::Pending, $donation->fresh()->status);
    }
}
