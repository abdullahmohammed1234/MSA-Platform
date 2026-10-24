<?php

namespace Tests\Feature\Phase431;

use App\Models\CMS\Media;
use App\Models\CMS\MediaCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaRangeAndStreamTest extends TestCase
{
    use RefreshDatabase;

    protected User $maleUser;
    protected MediaCategory $category;
    protected Media $videoMedia;
    protected string $malePlainTextToken;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        Role::firstOrCreate(['slug' => 'member'], ['name' => 'Member']);

        $this->maleUser = User::factory()->create([
            'email' => 'brother@sfu.ca',
            'gender' => 'male',
        ]);
        $this->maleUser->assignRole('member');
        $this->malePlainTextToken = $this->maleUser->createToken('test_token')->plainTextToken;

        $this->category = MediaCategory::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Lectures',
            'slug' => 'lectures',
        ]);

        // Create a 100-byte dummy video payload
        $videoPayload = str_repeat('ABCDEFGHIJ', 10); // 100 bytes
        $videoPath = 'private/uploads/cms/halqa-lecture.mp4';
        Storage::disk('local')->put($videoPath, $videoPayload);

        $videoUuid = (string) Str::uuid();
        $this->videoMedia = Media::create([
            'uuid' => $videoUuid,
            'filename' => 'halqa-lecture.mp4',
            'display_name' => 'Halqa Lecture Video',
            'filepath' => $videoPath,
            'url' => "/api/v1/website/media/{$videoUuid}/file",
            'mime_type' => 'video/mp4',
            'size' => 100,
            'audience' => 'brothers',
            'category_id' => $this->category->id,
        ]);
    }

    /**
     * Test full media download returns 200 OK.
     */
    public function test_full_media_stream_returns_200(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->malePlainTextToken)
            ->get("/api/v1/website/media/{$this->videoMedia->uuid}/file");

        $response->assertStatus(200);
        $this->assertEquals('video/mp4', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    /**
     * Test HTTP Range request returns 206 Partial Content when Range header is provided.
     */
    public function test_range_request_returns_206_partial_content(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->malePlainTextToken,
            'Range' => 'bytes=0-9',
        ])->get("/api/v1/website/media/{$this->videoMedia->uuid}/file");

        // Symfony BinaryFileResponse natively supports Range requests
        $response->assertStatus(206);
        $this->assertEquals('bytes 0-9/100', $response->headers->get('Content-Range'));
        $this->assertEquals('10', $response->headers->get('Content-Length'));
        $this->assertEquals('ABCDEFGHIJ', $response->streamedContent());
    }

    /**
     * Test seeking to the middle of the stream using byte range.
     */
    public function test_range_request_middle_seeking(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->malePlainTextToken,
            'Range' => 'bytes=10-19',
        ])->get("/api/v1/website/media/{$this->videoMedia->uuid}/file");

        $response->assertStatus(206);
        $this->assertEquals('bytes 10-19/100', $response->headers->get('Content-Range'));
        $this->assertEquals('10', $response->headers->get('Content-Length'));
        $this->assertEquals('ABCDEFGHIJ', $response->streamedContent());
    }
}
