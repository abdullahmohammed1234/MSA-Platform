<?php
# d:\projects\msa + dawah\MSA Platform\backend\tests\Unit\Services\AuthServiceTest.php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AuthService $authService;
    protected UserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = new UserRepository();
        $this->authService = new AuthService($this->userRepository);
    }

    public function test_register_creates_a_new_user()
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
        ];

        $user = $this->authService->register($data);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertTrue(\Hash::check('secret123', $user->password));
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_login_authenticates_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => \Hash::make('password123'),
        ]);

        $result = $this->authService->login('login@example.com', 'password123');

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals($user->id, $result['user']->id);
        $this->assertNotEmpty($result['token']);
    }

    public function test_login_throws_validation_exception_for_invalid_credentials()
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => \Hash::make('password123'),
        ]);

        $this->expectException(ValidationException::class);
        $this->authService->login('login@example.com', 'wrongpassword');
    }

    public function test_logout_deletes_user_tokens()
    {
        $user = User::factory()->create();
        $user->createToken('auth_token');

        $this->assertCount(1, $user->tokens);

        $this->authService->logout($user);

        $this->assertCount(0, $user->fresh()->tokens);
    }
}
