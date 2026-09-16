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

    public function test_external_metadata_lookup_success_open_library_direct_isbn(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        \Illuminate\Support\Facades\Http::fake([
            'https://openlibrary.org/isbn/*' => \Illuminate\Support\Facades\Http::response([
                'title' => 'Clean Code',
                'authors' => [['name' => 'Robert C. Martin']],
                'publishers' => ['Prentice Hall'],
                'publish_date' => '2008',
            ], 200),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/library/intake/lookup?isbn=9780132350884');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'FOUND')
            ->assertJsonPath('exists_in_catalog', false)
            ->assertJsonPath('suggested_data.title', 'Clean Code')
            ->assertJsonPath('provider', 'Open Library Direct ISBN');
    }

    public function test_external_metadata_lookup_success_open_library_books(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        \Illuminate\Support\Facades\Http::fake([
            'https://openlibrary.org/isbn/*' => \Illuminate\Support\Facades\Http::response(null, 404),
            'https://openlibrary.org/api/books*' => \Illuminate\Support\Facades\Http::response([
                'ISBN:9780132350884' => [
                    'title' => 'Clean Code',
                    'authors' => [['name' => 'Robert C. Martin']],
                    'publishers' => [['name' => 'Prentice Hall']],
                    'publish_date' => '2008',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/library/intake/lookup?isbn=9780132350884');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'FOUND')
            ->assertJsonPath('exists_in_catalog', false)
            ->assertJsonPath('suggested_data.title', 'Clean Code')
            ->assertJsonPath('provider', 'Open Library Books');
    }

    public function test_external_metadata_lookup_books_miss_search_hit(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        \Illuminate\Support\Facades\Http::fake([
            'https://openlibrary.org/isbn/*' => \Illuminate\Support\Facades\Http::response(null, 404),
            'https://openlibrary.org/api/books*' => \Illuminate\Support\Facades\Http::response([], 200),
            'https://openlibrary.org/search.json*' => \Illuminate\Support\Facades\Http::response([
                'docs' => [
                    [
                        'title' => 'Effective Java',
                        'author_name' => ['Joshua Bloch'],
                        'publisher' => ['Addison-Wesley'],
                        'first_publish_year' => 2017,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/library/intake/lookup?isbn=9780134685991');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'FOUND')
            ->assertJsonPath('suggested_data.title', 'Effective Java')
            ->assertJsonPath('provider', 'Open Library Search');
    }

    public function test_external_metadata_lookup_complete_miss_returns_not_found(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        \Illuminate\Support\Facades\Http::fake([
            'https://openlibrary.org/isbn/*' => \Illuminate\Support\Facades\Http::response(null, 404),
            'https://openlibrary.org/api/books*' => \Illuminate\Support\Facades\Http::response([], 200),
            'https://openlibrary.org/search.json*' => \Illuminate\Support\Facades\Http::response(['docs' => []], 200),
            'https://api.itbook.store/*' => \Illuminate\Support\Facades\Http::response(['error' => '0'], 200),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/library/intake/lookup?isbn=9781597840002');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'NOT_FOUND')
            ->assertJsonPath('exists_in_catalog', false)
            ->assertJsonPath('suggested_data', null);
    }

    public function test_external_metadata_lookup_provider_outage_returns_upstream_error(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        \Illuminate\Support\Facades\Http::fake([
            'https://openlibrary.org/isbn/*' => \Illuminate\Support\Facades\Http::response(null, 503),
            'https://openlibrary.org/api/books*' => \Illuminate\Support\Facades\Http::response(null, 503),
            'https://openlibrary.org/search.json*' => \Illuminate\Support\Facades\Http::response(null, 503),
            'https://api.itbook.store/*' => \Illuminate\Support\Facades\Http::response(null, 503),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/library/intake/lookup?isbn=9780132350884');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'UPSTREAM_ERROR')
            ->assertJsonPath('suggested_data', null);
    }

    public function test_external_metadata_lookup_invalid_isbn_returns_invalid_input(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/library/intake/lookup?isbn=12345');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'INVALID_INPUT')
            ->assertJsonPath('suggested_data', null);
    }

    public function test_google_books_is_never_called_regression_check(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        \Illuminate\Support\Facades\Http::fake([
            'https://openlibrary.org/isbn/*' => \Illuminate\Support\Facades\Http::response(null, 404),
            'https://openlibrary.org/api/books*' => \Illuminate\Support\Facades\Http::response([], 200),
            'https://openlibrary.org/search.json*' => \Illuminate\Support\Facades\Http::response(['docs' => []], 200),
            'https://api.itbook.store/*' => \Illuminate\Support\Facades\Http::response(['error' => '0'], 200),
        ]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/library/intake/lookup?isbn=9780132350884');

        \Illuminate\Support\Facades\Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'googleapis.com') || str_contains($request->url(), 'google');
        });
    }
}
