<?php

namespace Tests\Feature;

use App\Models\User;
use App\Mlibms\Models\Author;
use App\Mlibms\Models\Book;
use App\Mlibms\Models\Category;
use App\Mlibms\Models\Copy;
use App\Mlibms\Models\Publisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MlibmsBookPersistenceAndInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function mlibmsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        \Illuminate\Support\Facades\DB::table('application_access')->insertOrIgnore([
            'user_id' => $admin->id,
            'application' => 'mlibms',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $admin;
    }

    public function test_manual_book_creation_persists_book_and_generates_copy_records(): void
    {
        $admin = $this->mlibmsAdmin();

        $payload = [
            'title' => 'Riyad As-Salihin',
            'subtitle' => 'The Gardens of the Righteous',
            'author_names' => ['Imam An-Nawawi'],
            'publisher_name' => 'Darussalam',
            'isbn_13' => '9789960892008',
            'publication_year' => 1999,
            'copies' => [], // Empty copies payload should still generate at least 1 default physical copy
        ];

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/library/intake/store', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Riyad As-Salihin')
            ->assertJsonPath('data.isbn_13', '9789960892008');

        $this->assertDatabaseHas('mlibms_books', [
            'title' => 'Riyad As-Salihin',
            'isbn_13' => '9789960892008',
        ]);

        $book = Book::where('isbn_13', '9789960892008')->firstOrFail();
        $this->assertGreaterThanOrEqual(1, $book->copies()->count());
        $copy = $book->copies()->first();
        $this->assertNotNull($copy->barcode);
        $this->assertNotNull($copy->accession_number);
        $this->assertSame('available', $copy->status->value);
    }

    public function test_standard_books_rest_endpoint_creates_book_with_copies(): void
    {
        $admin = $this->mlibmsAdmin();

        $payload = [
            'title' => 'Fortress of the Muslim',
            'author_names' => ['Said bin Ali bin Wahf Al-Qahtani'],
            'isbn_13' => '9789960892015',
            'copies' => [
                ['condition' => 'new', 'notes' => 'Donated copy'],
            ],
        ];

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/library/books', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Fortress of the Muslim');

        $this->assertDatabaseHas('mlibms_books', [
            'title' => 'Fortress of the Muslim',
        ]);
        $this->assertDatabaseHas('mlibms_copies', [
            'notes' => 'Donated copy',
        ]);
    }

    public function test_manual_book_creation_validation_rejects_missing_title(): void
    {
        $admin = $this->mlibmsAdmin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/library/intake/store', [
            'title' => '',
            'isbn_13' => '9781234567890',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_soft_deleted_copy_records_do_not_cause_duplicate_identifier_collisions(): void
    {
        $admin = $this->mlibmsAdmin();

        // Create book 1 with a copy
        $book1 = Book::create([
            'title' => 'Temporary Book',
            'slug' => 'temporary-book',
        ]);
        $copy1 = Copy::create([
            'book_id' => $book1->id,
            'barcode' => 'MLIB-C-000001',
            'accession_number' => 'MLIB-A-000001',
            'status' => 'available',
        ]);

        // Soft-delete copy 1
        $copy1->delete();
        $this->assertSoftDeleted('mlibms_copies', ['id' => $copy1->id]);

        // Now create another book with automatic copy generation
        $payload = [
            'title' => 'New Active Book',
            'author_names' => ['Scholar A'],
            'copies' => [
                ['condition' => 'good'],
            ],
        ];

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/library/intake/store', $payload);

        $response->assertStatus(201);
        $newCopy = Copy::where('book_id', $response->json('data.id'))->firstOrFail();
        $this->assertNotEquals('MLIB-C-000001', $newCopy->barcode);
        $this->assertNotEquals('MLIB-A-000001', $newCopy->accession_number);
    }

    public function test_book_update_endpoint_updates_bibliographic_details_and_authors(): void
    {
        $admin = $this->mlibmsAdmin();

        $category = Category::create([
            'name' => 'Hadith Sciences',
            'slug' => 'hadith-sciences',
        ]);

        $book = Book::create([
            'title' => 'Old Title',
            'slug' => 'old-title',
            'isbn_13' => '9781111111111',
            'language' => 'Arabic',
        ]);

        $updatePayload = [
            'title' => 'Corrected Hadith Collection',
            'subtitle' => 'Volume 1',
            'primary_category_id' => $category->id,
            'publisher_name' => 'Dar Ibn Kathir',
            'author_names' => ['Imam Bukhari', 'Imam Muslim'],
            'language' => 'Arabic',
            'summary' => 'Comprehensive authentic hadith collection.',
            'default_loan_days' => 21,
            'is_reference_only' => true,
        ];

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/admin/library/books/{$book->uuid}", $updatePayload);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Corrected Hadith Collection')
            ->assertJsonPath('data.is_reference_only', true);

        $book->refresh();
        $this->assertSame('Corrected Hadith Collection', $book->title);
        $this->assertSame('Volume 1', $book->subtitle);
        $this->assertSame($category->id, $book->primary_category_id);
        $this->assertTrue($book->is_reference_only);
        $this->assertSame(21, $book->default_loan_days);
        $this->assertCount(2, $book->authors);
    }

    public function test_inventory_pagination_returns_all_books_across_pages(): void
    {
        $admin = $this->mlibmsAdmin();

        // Seed 25 books
        for ($i = 1; $i <= 25; $i++) {
            Book::create([
                'title' => "Catalog Book Volume {$i}",
                'slug' => "catalog-book-volume-{$i}",
                'isbn_13' => '978000000' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            ]);
        }

        // Page 1 with per_page 10
        $page1 = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/library/books?page=1&per_page=10');
        $page1->assertStatus(200)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonCount(10, 'data');

        // Page 2
        $page2 = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/library/books?page=2&per_page=10');
        $page2->assertStatus(200)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(10, 'data');

        // Page 3
        $page3 = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/library/books?page=3&per_page=10');
        $page3->assertStatus(200)
            ->assertJsonPath('meta.current_page', 3)
            ->assertJsonCount(5, 'data');
    }

    public function test_unauthorized_user_cannot_create_or_edit_books(): void
    {
        $user = User::factory()->create(); // Regular user without mlibms application access

        $postRes = $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/library/books', [
            'title' => 'Unauthorized Book',
        ]);
        $postRes->assertStatus(403);

        $book = Book::create([
            'title' => 'Protected Book',
            'slug' => 'protected-book',
        ]);

        $putRes = $this->actingAs($user, 'sanctum')->putJson("/api/v1/admin/library/books/{$book->uuid}", [
            'title' => 'Hacked Book',
        ]);
        $putRes->assertStatus(403);
    }
}
