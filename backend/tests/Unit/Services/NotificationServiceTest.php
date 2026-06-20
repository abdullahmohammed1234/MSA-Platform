<?php
# d:\projects\msa + dawah\MSA Platform\backend\tests\Unit\Services\NotificationServiceTest.php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = new NotificationService();
    }

    public function test_send_in_app_does_not_throw_exception()
    {
        $user = User::factory()->create();

        $this->notificationService->sendInApp($user, 'Test Title', 'Test Message');

        $this->assertTrue(true); // Verifies it executes without throwing exceptions
    }

    public function test_send_email_does_not_throw_exception()
    {
        $user = User::factory()->create();

        $this->notificationService->sendEmail($user, 'Test Subject', 'Test Body');

        $this->assertTrue(true); // Verifies it executes without throwing exceptions
    }
}
