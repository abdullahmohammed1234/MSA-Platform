<?php

namespace App\Ems\Enums;

/**
 * Square PaymentRefund status as stored by EMS.
 *
 * Square lifecycle: PENDING → COMPLETED | FAILED | REJECTED.
 *
 * Transitions are monotonic with respect to terminal states:
 * - pending may move to completed, failed, or rejected
 * - completed / failed / rejected never move to a different status
 * - applying the same terminal status again is a no-op (no re-fulfillment)
 *
 * A stale PENDING/FAILED webhook must not undo COMPLETED.
 */
enum SquareRefundStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Rejected => 'Rejected',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Rejected], true);
    }

    /**
     * Whether this stored status may be replaced by $incoming from Square.
     *
     * Same-status updates return false so callers skip side effects.
     */
    public function canAdvanceTo(self $incoming): bool
    {
        if ($this === $incoming) {
            return false;
        }

        return $this === self::Pending;
    }

    public static function fromSquare(string $raw, self $fallback = self::Pending): self
    {
        return match (strtoupper($raw)) {
            'COMPLETED' => self::Completed,
            'FAILED' => self::Failed,
            'REJECTED' => self::Rejected,
            'PENDING' => self::Pending,
            default => $fallback,
        };
    }
}
