<?php
# d:\projects\msa + dawah\MSA Platform\backend\tests\Feature\Api\ApiRoutingTest.php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_unauthenticated_request_returns_401()
    {
        $this->getJson('/api/v1/users/me')
            ->assertStatus(401);
    }

    public function test_api_authenticated_user_profile()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/users/me')
            ->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email']
            ]);
    }

    public function test_api_validation_error_format()
    {
        $this->postJson(route('api.auth.register'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password', 'name']);
    }
}
