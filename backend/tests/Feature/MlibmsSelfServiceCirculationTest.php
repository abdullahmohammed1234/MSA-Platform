<?php

namespace Tests\Feature;

use App\Models\User;
use App\Mlibms\Models\Book;
use App\Mlibms\Models\Copy;
use App\Mlibms\Models\Loan;
use App\Mlibms\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MlibmsSelfServiceCirculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_authenticated_user_can_self_service_checkout_available_book(): void
    {
        $user = User::factory()->create();
        $book = Book::create(['title' => 'Sahih Al-Bukhari', 'slug' => 'sahih-al-bukhari']);
        $copy = Copy::create(['book_id' => $book->id, 'barcode' => 'MLIB-C-000001', 'accession_number' => 'MLIB-A-000001', 'status' => 'available']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/library/scan/checkout', [
            'copy_barcode' => 'MLIB-C-000001',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('mlibms_loans', [
            'copy_id' => $copy->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('mlibms_copies', [
            'id' => $copy->id,
            'status' => 'checked_out',
        ]);
    }

    public function test_self_service_checkout_rejected_if_book_is_reference_only(): void
    {
        $user = User::factory()->create();
        $book = Book::create(['title' => 'Rare Manuscript', 'slug' => 'rare-manuscript', 'is_reference_only' => true]);
        $copy = Copy::create(['book_id' => $book->id, 'barcode' => 'MLIB-C-000002', 'accession_number' => 'MLIB-A-000002', 'status' => 'available']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/library/scan/checkout', [
            'copy_barcode' => 'MLIB-C-000002',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'This item is marked Reference Only and must remain in the library.');
    }

    public function test_self_service_return_allowed_only_for_loan_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::create(['title' => 'Riyad As-Salihin', 'slug' => 'riyad-as-salihin']);
        $copy = Copy::create(['book_id' => $book->id, 'barcode' => 'MLIB-C-000003', 'accession_number' => 'MLIB-A-000003', 'status' => 'checked_out']);
        $member = Member::create(['user_id' => $owner->id, 'library_card_number' => 'CARD-001', 'name' => $owner->name, 'email' => $owner->email]);

        Loan::create([
            'copy_id' => $copy->id,
            'member_id' => $member->id,
            'checked_out_at' => now(),
            'due_at' => now()->addDays(14),
            'status' => 'active',
        ]);

        // Attempt return by another user -> Should be rejected
        $mismatchResponse = $this->actingAs($otherUser, 'sanctum')->postJson('/api/v1/library/scan/return', [
            'copy_barcode' => 'MLIB-C-000003',
        ]);

        $mismatchResponse->assertStatus(422)
            ->assertJsonPath('message', 'This copy is currently checked out to another member. Please have the borrower return it or contact library staff.');

        // Attempt return by loan owner -> Success
        $successResponse = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/library/scan/return', [
            'copy_barcode' => 'MLIB-C-000003',
        ]);

        $successResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'returned');

        $this->assertDatabaseHas('mlibms_copies', [
            'id' => $copy->id,
            'status' => 'available',
        ]);
    }

    public function test_staff_admin_can_perform_administrative_return_override(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $borrower = User::factory()->create();
        $book = Book::create(['title' => 'Fiqh us-Sunnah', 'slug' => 'fiqh-us-sunnah']);
        $copy = Copy::create(['book_id' => $book->id, 'barcode' => 'MLIB-C-000004', 'accession_number' => 'MLIB-A-000004', 'status' => 'checked_out']);
        $member = Member::create(['user_id' => $borrower->id, 'library_card_number' => 'CARD-002', 'name' => $borrower->name, 'email' => $borrower->email]);

        Loan::create([
            'copy_id' => $copy->id,
            'member_id' => $member->id,
            'checked_out_at' => now(),
            'due_at' => now()->addDays(14),
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/library/loans/override-return', [
            'copy_barcode' => 'MLIB-C-000004',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Administrative return successfully executed.');
    }
}
