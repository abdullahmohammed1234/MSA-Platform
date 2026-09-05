<?php

namespace Tests\Feature;

use App\Models\User;
use App\Mlibms\Models\Book;
use App\Mlibms\Models\Copy;
use App\Mlibms\Models\Loan;
use App\Mlibms\Models\Member;
use App\Mlibms\Models\Reservation;
use App\Mlibms\Services\IntakeService;
use App\Mlibms\Services\LoanService;
use App\Mlibms\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MlibmsIdorProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected User $userB;
    protected Member $memberA;
    protected Member $memberB;
    protected Book $book;
    protected Copy $copy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->userA = User::factory()->create(['email' => 'usera@sfu.ca']);
        $this->userB = User::factory()->create(['email' => 'userb@sfu.ca']);

        $this->memberA = Member::create([
            'user_id' => $this->userA->id,
            'library_card_number' => 'MLIB-M-000001',
            'name' => 'User A',
            'email' => $this->userA->email,
            'membership_type' => 'student',
            'status' => 'active',
            'max_active_loans' => 5,
            'registered_at' => now(),
        ]);

        $this->memberB = Member::create([
            'user_id' => $this->userB->id,
            'library_card_number' => 'MLIB-M-000002',
            'name' => 'User B',
            'email' => $this->userB->email,
            'membership_type' => 'student',
            'status' => 'active',
            'max_active_loans' => 5,
            'registered_at' => now(),
        ]);

        /** @var IntakeService $intake */
        $intake = app(IntakeService::class);
        $this->book = $intake->createBookWithCopies([
            'title' => 'IDOR Test Book',
            'isbn_13' => '9780000000001',
        ], [
            ['condition' => 'good']
        ]);

        $this->copy = $this->book->copies->first();
    }

    public function test_user_b_cannot_renew_user_a_loan(): void
    {
        /** @var LoanService $loanService */
        $loanService = app(LoanService::class);
        $loan = $loanService->selfServiceCheckout($this->copy->barcode, $this->userA);

        $response = $this->actingAs($this->userB)
            ->postJson("/api/v1/library/loans/{$loan->uuid}/renew");

        $response->assertStatus(404);
    }

    public function test_user_b_cannot_cancel_user_a_hold_reservation(): void
    {
        /** @var ReservationService $resService */
        $resService = app(ReservationService::class);
        $hold = $resService->placeHold($this->book, $this->userA);

        $response = $this->actingAs($this->userB)
            ->deleteJson("/api/v1/library/holds/{$hold->uuid}");

        $response->assertStatus(404);
    }

    public function test_public_catalog_does_not_leak_member_data(): void
    {
        $response = $this->getJson("/api/v1/library/books/{$this->book->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'IDOR Test Book')
            ->assertJsonMissing(['email', 'library_card_number', 'phone', 'user_id']);
    }

    public function test_hold_queue_priority_blocks_unauthorized_checkout(): void
    {
        /** @var ReservationService $resService */
        $resService = app(ReservationService::class);
        $resService->placeHold($this->book, $this->userA); // Member A holds 1 copy

        // Member B attempts self-service checkout for the only copy
        /** @var LoanService $loanService */
        $loanService = app(LoanService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("This copy is currently reserved for a member in the hold queue.");

        $loanService->selfServiceCheckout($this->copy->barcode, $this->userB);
    }

    public function test_csv_export_escapes_formulas(): void
    {
        $admin = User::factory()->create(['email' => 'admin@sfumsa.ca']);
        $admin->assignRole('admin');

        /** @var LoanService $loanService */
        $loanService = app(LoanService::class);
        $loanService->selfServiceCheckout($this->copy->barcode, $this->userA);

        // Mutate member name to formula injection string
        $this->memberA->update(['name' => '=CMD|"/C calc"!A0']);

        $response = $this->actingAs($admin)
            ->get('/api/v1/admin/library/reports/export-loans');

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('"\'=CMD|', $content);
    }
}
