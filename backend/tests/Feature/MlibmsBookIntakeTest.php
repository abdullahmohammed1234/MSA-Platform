<?php

namespace Tests\Feature;

use App\Models\User;
use App\Mlibms\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MlibmsBookIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_intake_lookup_detects_existing_book(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Book::create([
            'title' => 'Tafsir Ibn Kathir',
            'slug' => 'tafsir-ibn-kathir',
            'isbn_13' => '9781597840000',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/library/intake/lookup?isbn=9781597840000');

        $response->assertStatus(200)
            ->assertJsonPath('exists_in_catalog', true)
            ->assertJsonPath('data.title', 'Tafsir Ibn Kathir');
    }

    public function test_intake_store_creates_book_and_physical_copies(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $payload = [
            'title' => 'Al-Muqaddimah',
            'author_names' => ['Ibn Khaldun'],
            'publisher_name' => 'Dar Al-Kutub',
            'isbn_13' => '9780691166728',
            'publication_year' => 1377,
            'copies' => [
                ['condition' => 'good', 'notes' => 'Copy 1'],
                ['condition' => 'new', 'notes' => 'Copy 2'],
            ],
        ];

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/library/intake/store', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Al-Muqaddimah');

        $this->assertDatabaseHas('mlibms_books', [
            'isbn_13' => '9780691166728',
        ]);
        $this->assertDatabaseHas('mlibms_authors', [
            'name' => 'Ibn Khaldun',
        ]);
        $this->assertDatabaseCount('mlibms_copies', 2);
    }
}
