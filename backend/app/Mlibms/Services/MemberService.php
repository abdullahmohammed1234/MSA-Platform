<?php

namespace App\Mlibms\Services;

use App\Models\User;
use App\Mlibms\Models\Loan;
use App\Mlibms\Models\Member;
use Illuminate\Support\Str;

class MemberService
{
    /**
     * Get or provision a Member profile for an authenticated User.
     */
    public function getOrProvisionMember(User $user): Member
    {
        $member = Member::where('user_id', $user->id)->first();

        if (!$member) {
            $latest = (Member::max('id') ?? 0) + 1;
            $cardNumber = 'LIB-CARD-' . str_pad((string) $latest, 6, '0', STR_PAD_LEFT);

            $member = Member::create([
                'user_id' => $user->id,
                'library_card_number' => $cardNumber,
                'name' => $user->name,
                'email' => $user->email,
                'membership_type' => 'student',
                'status' => 'active',
                'max_active_loans' => 3,
                'registered_at' => now(),
            ]);
        }

        return $member;
    }

    /**
     * Create a guest walk-in borrower (staff-assisted only, user_id = null).
     */
    public function createGuestMember(array $data): Member
    {
        $latest = (Member::max('id') ?? 0) + 1;
        $cardNumber = 'LIB-GUEST-' . str_pad((string) $latest, 6, '0', STR_PAD_LEFT);

        return Member::create([
            'user_id' => null,
            'library_card_number' => $cardNumber,
            'name' => trim($data['name']),
            'email' => trim($data['email']),
            'phone' => $data['phone'] ?? null,
            'membership_type' => 'community_guest',
            'status' => 'active',
            'max_active_loans' => $data['max_active_loans'] ?? 2,
            'notes' => $data['notes'] ?? 'Walk-in guest borrower (staff-assisted only).',
            'registered_at' => now(),
        ]);
    }

    /**
     * Validate whether a member can borrow books right now.
     */
    public function checkBorrowingEligibility(Member $member): array
    {
        if ($member->status === 'suspended') {
            return [
                'eligible' => false,
                'reason' => "Borrowing privileges for {$member->name} are currently suspended (" . ($member->suspension_reason ?? 'Overdue items') . ").",
            ];
        }

        if ($member->status === 'expired') {
            return [
                'eligible' => false,
                'reason' => "Membership card {$member->library_card_number} has expired.",
            ];
        }

        // Check active loan limit
        $activeLoansCount = Loan::where('member_id', $member->id)->where('status', 'active')->count();
        if ($activeLoansCount >= $member->max_active_loans) {
            return [
                'eligible' => false,
                'reason' => "Maximum active loans limit ({$member->max_active_loans}) reached.",
            ];
        }

        // Check if member has severe overdue items (> 7 days)
        $severelyOverdueCount = Loan::where('member_id', $member->id)
            ->whereIn('status', ['active', 'overdue'])
            ->whereNull('returned_at')
            ->where('due_at', '<', now()->subDays(7))
            ->count();

        if ($severelyOverdueCount > 0) {
            // Auto-suspend member
            $member->update([
                'status' => 'suspended',
                'suspended_at' => now(),
                'suspension_reason' => 'Auto-suspended: Has loans overdue by more than 7 days.',
            ]);

            return [
                'eligible' => false,
                'reason' => "Account auto-suspended due to loans overdue by more than 7 days.",
            ];
        }

        return [
            'eligible' => true,
            'reason' => null,
        ];
    }
}
