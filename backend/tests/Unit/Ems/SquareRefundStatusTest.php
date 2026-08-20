<?php

namespace Tests\Unit\Ems;

use App\Ems\Enums\SquareRefundStatus;
use PHPUnit\Framework\TestCase;

class SquareRefundStatusTest extends TestCase
{
    public function test_pending_can_advance_to_terminal_states(): void
    {
        $pending = SquareRefundStatus::Pending;

        $this->assertTrue($pending->canAdvanceTo(SquareRefundStatus::Completed));
        $this->assertTrue($pending->canAdvanceTo(SquareRefundStatus::Failed));
        $this->assertTrue($pending->canAdvanceTo(SquareRefundStatus::Rejected));
        $this->assertFalse($pending->canAdvanceTo(SquareRefundStatus::Pending));
    }

    public function test_terminal_states_do_not_advance(): void
    {
        foreach ([
            SquareRefundStatus::Completed,
            SquareRefundStatus::Failed,
            SquareRefundStatus::Rejected,
        ] as $terminal) {
            $this->assertFalse($terminal->canAdvanceTo(SquareRefundStatus::Pending));
            $this->assertFalse($terminal->canAdvanceTo(SquareRefundStatus::Completed));
            $this->assertFalse($terminal->canAdvanceTo(SquareRefundStatus::Failed));
            $this->assertFalse($terminal->canAdvanceTo(SquareRefundStatus::Rejected));
            $this->assertTrue($terminal->isTerminal());
        }
    }

    public function test_from_square_maps_known_statuses(): void
    {
        $this->assertSame(SquareRefundStatus::Completed, SquareRefundStatus::fromSquare('COMPLETED'));
        $this->assertSame(SquareRefundStatus::Failed, SquareRefundStatus::fromSquare('FAILED'));
        $this->assertSame(SquareRefundStatus::Rejected, SquareRefundStatus::fromSquare('REJECTED'));
        $this->assertSame(SquareRefundStatus::Pending, SquareRefundStatus::fromSquare('PENDING'));
        $this->assertSame(SquareRefundStatus::Pending, SquareRefundStatus::fromSquare('UNKNOWN'));
    }
}
