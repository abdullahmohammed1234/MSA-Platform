<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SsoTicket;
use App\Models\User;
use App\Pm\Services\ProjectAccessService;
use App\Services\ApplicationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SsoController extends Controller
{
    /**
     * Supported service identifiers.
     */
    protected const SUPPORTED_SERVICES = ['pms'];

    /**
     * Ticket lifetime in seconds.
     */
    protected const TICKET_TTL_SECONDS = 60;

    public function __construct(
        protected ApplicationAccessService $appAccessService,
        protected ProjectAccessService $projectAccessService
    ) {}

    /**
     * Request a short-lived, single-use SSO exchange ticket.
     * Authenticated via main website Sanctum token.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'service' => ['required', 'string', 'in:' . implode(',', self::SUPPORTED_SERVICES)],
        ]);

        $service = $validated['service'];

        // 1. Verify user is active
        if (!$user->is_active) {
            return response()->json([
                'message' => 'User account is inactive.',
            ], 403);
        }

        // 2. Verify service-specific eligibility
        if ($service === 'pms') {
            if (!$this->appAccessService->canAccess($user, 'projects') || !$this->projectAccessService->canAccessProjects($user)) {
                return response()->json([
                    'message' => 'User is not eligible to access the Project Management System.',
                ], 403);
            }
        }

        // 3. Generate 32 bytes of cryptographic entropy (64 hex characters)
        $plainTicket = bin2hex(random_bytes(32));
        $ticketHash = hash('sha256', $plainTicket);
        $expiresAt = now()->addSeconds(self::TICKET_TTL_SECONDS);

        // 4. Store only hashed ticket
        SsoTicket::create([
            'ticket_hash' => $ticketHash,
            'user_id' => $user->id,
            'service' => $service,
            'expires_at' => $expiresAt,
            'consumed_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'ticket' => $plainTicket,
            'service' => $service,
            'expires_in' => self::TICKET_TTL_SECONDS,
        ], 201);
    }

    /**
     * Exchange a short-lived SSO ticket for a service-specific Sanctum token.
     * Unauthenticated because the ticket itself serves as the temporary credential.
     */
    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ticket' => ['required', 'string', 'min:32', 'max:128'],
            'service' => ['sometimes', 'string', 'in:' . implode(',', self::SUPPORTED_SERVICES)],
        ]);

        $plainTicket = $validated['ticket'];
        $service = $validated['service'] ?? 'pms';
        $ticketHash = hash('sha256', $plainTicket);

        // Atomically lock and consume the ticket
        $ssoTicket = DB::transaction(function () use ($ticketHash, $service) {
            /** @var SsoTicket|null $record */
            $record = SsoTicket::where('ticket_hash', $ticketHash)
                ->where('service', $service)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (!$record) {
                return null;
            }

            $record->consumed_at = now();
            $record->save();

            return $record;
        });

        if (!$ssoTicket) {
            // Provide exact failure diagnosis without leaking user details
            $existing = SsoTicket::where('ticket_hash', $ticketHash)->first();

            if ($existing) {
                if ($existing->service !== $service) {
                    return response()->json(['message' => 'SSO ticket audience mismatch.'], 401);
                }
                if ($existing->consumed_at !== null) {
                    return response()->json(['message' => 'SSO ticket has already been used.'], 401);
                }
                if ($existing->expires_at <= now()) {
                    return response()->json(['message' => 'SSO ticket has expired.'], 401);
                }
            }

            return response()->json(['message' => 'Invalid or malformed SSO ticket.'], 401);
        }

        /** @var User|null $user */
        $user = User::find($ssoTicket->user_id);

        if (!$user || !$user->is_active) {
            return response()->json(['message' => 'User account is inactive or disabled.'], 403);
        }

        // Verify PMS access eligibility
        if ($service === 'pms') {
            if (!$this->appAccessService->canAccess($user, 'projects') || !$this->projectAccessService->canAccessProjects($user)) {
                return response()->json([
                    'message' => 'User does not possess Project Management access privileges.',
                ], 403);
            }
        }

        // Issue a PMS-specific Sanctum token
        $pmsToken = $user->createToken('pms_sso_token', ['pms:access'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'SSO ticket exchanged successfully.',
            'token' => $pmsToken,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ],
        ]);
    }
}

