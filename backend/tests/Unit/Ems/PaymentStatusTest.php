<?php

namespace Tests\Unit\Ems;

use App\Ems\Enums\PaymentStatus;
use PHPUnit\Framework\TestCase;

class PaymentStatusTest extends TestCase
{
    public function test_cancelled_cannot_transition_to_paid(): void
    {
        $this->assertFalse(PaymentStatus::Cancelled->canTransitionTo(PaymentStatus::Paid));
    }

    public function test_abandoned_can_still_transition_to_paid(): void
    {
        $this->assertTrue(PaymentStatus::Abandoned->canTransitionTo(PaymentStatus::Paid));
    }
}
