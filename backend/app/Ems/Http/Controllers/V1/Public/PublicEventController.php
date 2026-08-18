<?php

namespace App\Ems\Http\Controllers\V1\Public;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Requests\Public\CalendarPublicEventRequest;
use App\Ems\Http\Requests\Public\CancelCheckoutRequest;
use App\Ems\Http\Requests\Public\CheckoutEventRequest;
use App\Ems\Http\Requests\Public\IndexPublicEventRequest;
use App\Ems\Http\Requests\Public\JoinWaitlistRequest;
use App\Ems\Http\Requests\Public\RegisterForEventRequest;
use App\Ems\Http\Requests\Public\ResumeCheckoutRequest;
use App\Ems\Http\Resources\Public\PublicCalendarEventResource;
use App\Ems\Http\Resources\Public\PublicCategoryResource;
use App\Ems\Http\Resources\Public\PublicCheckoutResource;
use App\Ems\Http\Resources\Public\PublicEventDetailResource;
use App\Ems\Http\Resources\Public\PublicEventResource;
use App\Ems\Http\Resources\Public\PublicRegistrationResource;
use App\Ems\Http\Resources\Public\PublicTicketResource;
use App\Ems\Http\Resources\Public\PublicTicketTypeResource;
use App\Ems\Http\Resources\Public\PublicTicketValidationResource;
use App\Ems\Http\Resources\Public\PublicWaitlistResource;
use App\Ems\Services\CheckoutService;
use App\Ems\Services\CheckoutLifecycleService;
use App\Ems\Services\PublicEventService;
use App\Ems\Services\RegistrationService;
use App\Ems\Services\TicketTypeService;
use App\Ems\Services\Ticketing\QrCodeGenerator;
use App\Ems\Services\WaitlistService;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicEventController extends EmsController
{
    public function __construct(
        private readonly PublicEventService $events,
        private readonly RegistrationService $registrations,
        private readonly CheckoutService $checkout,
        private readonly CheckoutLifecycleService $checkoutLifecycle,
        private readonly TicketTypeService $ticketTypes,
        private readonly WaitlistService $waitlists,
        private readonly QrCodeGenerator $qr,
    ) {
    }

    /**
     * GET /api/v1/ems/public/events
     */
    public function index(IndexPublicEventRequest $request): JsonResponse
    {
        $paginator = $this->events->paginate($request->filters());

        return ApiResponse::paginated(
            $paginator,
            'Public events retrieved successfully.',
            PublicEventResource::class
        );
    }

    /**
     * GET /api/v1/ems/public/events/calendar
     */
    public function calendar(CalendarPublicEventRequest $request): JsonResponse
    {
        $items = $this->events->calendar($request->filters());

        return ApiResponse::success(
            PublicCalendarEventResource::collection($items),
            'Calendar events retrieved successfully.'
        );
    }

    /**
     * GET /api/v1/ems/public/events/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $event = $this->events->findBySlug($slug);

        if ($event === null) {
            throw new NotFoundHttpException('The requested event was not found.');
        }

        $event->increment('views_count');

        return ApiResponse::success(
            new PublicEventDetailResource($event),
            'Event retrieved successfully.'
        );
    }

    /**
     * GET /api/v1/ems/public/categories
     */
    public function categories(): JsonResponse
    {
        return ApiResponse::success(
            PublicCategoryResource::collection($this->events->categories()),
            'Categories retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/ems/public/events/{slug}/register
     */
    public function register(RegisterForEventRequest $request, string $slug): JsonResponse
    {
        $event = $this->events->findBySlug($slug);

        if ($event === null) {
            throw new NotFoundHttpException('The requested event was not found.');
        }

        $event->increment('registrations_started_count');

        $registration = $this->registrations->registerFree(
            $event,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::created(
            new PublicRegistrationResource($registration),
            'Registration successful. Your ticket has been issued.'
        );
    }

    /**
     * POST /api/v1/ems/public/events/{slug}/checkout
     */
    public function checkout(CheckoutEventRequest $request, string $slug): JsonResponse
    {
        $event = $this->events->findBySlug($slug);

        if ($event === null) {
            throw new NotFoundHttpException('The requested event was not found.');
        }

        $event->increment('registrations_started_count');

        $result = $this->checkout->checkout(
            $event,
            $request->validated(),
            $request->user()
        );

        $message = $result['checkout_url']
            ? 'Checkout created. Redirect to Square to complete payment.'
            : 'Registration successful. Your ticket has been issued.';

        return ApiResponse::created(
            new PublicCheckoutResource($result),
            $message
        );
    }

    /**
     * POST /api/v1/ems/public/events/{slug}/checkout/resume
     */
    public function resumeCheckout(ResumeCheckoutRequest $request, string $slug): JsonResponse
    {
        $event = $this->events->findBySlug($slug);

        if ($event === null) {
            throw new NotFoundHttpException('The requested event was not found.');
        }

        $data = $request->validated();
        $result = $this->checkoutLifecycle->resume(
            $event,
            strtolower(trim($data['email'])),
            $data['order_uuid'] ?? null
        );

        return ApiResponse::success(
            new PublicCheckoutResource($result),
            'Checkout resumed. Redirect to Square to complete payment.'
        );
    }

    /**
     * POST /api/v1/ems/public/events/{slug}/checkout/cancel
     */
    public function cancelCheckout(CancelCheckoutRequest $request, string $slug): JsonResponse
    {
        $event = $this->events->findBySlug($slug);

        if ($event === null) {
            throw new NotFoundHttpException('The requested event was not found.');
        }

        $data = $request->validated();
        $result = $this->checkoutLifecycle->resume(
            $event,
            strtolower(trim($data['email'])),
            $data['order_uuid']
        );

        $payment = $result['payment'];
        if ($payment === null) {
            throw new NotFoundHttpException('The requested checkout was not found.');
        }

        $this->checkoutLifecycle->cancel($payment, 'Checkout cancelled by buyer.');

        return ApiResponse::success(null, 'Checkout cancelled.');
    }

    /**
     * GET /api/v1/ems/public/events/{slug}/tickets
     */
    public function tickets(string $slug): JsonResponse
    {
        $event = $this->events->findBySlug($slug);

        if ($event === null) {
            throw new NotFoundHttpException('The requested event was not found.');
        }

        return ApiResponse::success(
            PublicTicketTypeResource::collection(
                $this->ticketTypes->listForEvent($event, publicOnly: true)
            ),
            'Ticket types retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/ems/public/events/{slug}/waitlist
     */
    public function joinWaitlist(JoinWaitlistRequest $request, string $slug): JsonResponse
    {
        $event = $this->events->findBySlug($slug);

        if ($event === null) {
            throw new NotFoundHttpException('The requested event was not found.');
        }

        $entry = $this->waitlists->join($event, $request->validated(), $request->user());

        return ApiResponse::created(
            new PublicWaitlistResource($entry),
            'You have been added to the waitlist.'
        );
    }

    /**
     * DELETE /api/v1/ems/public/events/{slug}/waitlist/{entry}
     */
    public function leaveWaitlist(Request $request, string $slug, string $entry): JsonResponse
    {
        $event = $this->events->findBySlug($slug);

        if ($event === null) {
            throw new NotFoundHttpException('The requested event was not found.');
        }

        $this->waitlists->leave(
            $event,
            $entry,
            $request->query('email') ? strtolower((string) $request->query('email')) : null
        );

        return ApiResponse::deleted('You have left the waitlist.');
    }

    /**
     * GET /api/v1/ems/public/tickets/{code}
     */
    public function showTicket(string $code): JsonResponse
    {
        $ticket = $this->events->findTicketByCode($code);

        if ($ticket === null) {
            throw new NotFoundHttpException('The requested ticket was not found.');
        }

        return ApiResponse::success(
            new PublicTicketResource($ticket),
            'Ticket retrieved successfully.'
        );
    }

    /**
     * GET /api/v1/ems/public/tickets/validate/{code}
     *
     * Infrastructure only — does not mark the ticket as used.
     */
    public function validateTicket(string $code): JsonResponse
    {
        $result = $this->events->validateTicket($code);

        return ApiResponse::success(
            new PublicTicketValidationResource($result['ticket']),
            'Ticket is valid.'
        );
    }

    /**
     * GET /api/v1/ems/public/tickets/{code}/qr
     */
    public function ticketQr(string $code): Response
    {
        $ticket = $this->events->findTicketByCode($code);

        if ($ticket === null) {
            throw new NotFoundHttpException('The requested ticket was not found.');
        }

        $png = $this->qr->png($ticket);
 
        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * GET /api/v1/ems/public/my-tickets
     */
    public function myTickets(Request $request): JsonResponse
    {
        $user = $request->user();
        $registrations = \App\Ems\Models\Registration::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere('attendee_email', $user->email);
            })
            ->with(['event.category', 'tickets', 'ticketType'])
            ->orderBy('created_at', 'desc')
            ->get();

        return ApiResponse::success(
            \App\Ems\Http\Resources\Public\PublicRegistrationResource::collection($registrations),
            'My registrations retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/ems/public/registrations/{registration}/cancel
     */
    public function cancelRegistration(Request $request, string $registration): JsonResponse
    {
        $user = $request->user();
        
        /** @var \App\Ems\Models\Registration|null $reg */
        $reg = \App\Ems\Models\Registration::query()
            ->where('uuid', $registration)
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere('attendee_email', $user->email);
            })
            ->first();

        if ($reg === null) {
            throw new NotFoundHttpException('Registration not found.');
        }

        if ($reg->status === \App\Ems\Enums\RegistrationStatus::Cancelled) {
            return ApiResponse::success(
                new \App\Ems\Http\Resources\Public\PublicRegistrationResource($reg),
                'Registration is already cancelled.'
            );
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($reg): void {
            $reg->status = \App\Ems\Enums\RegistrationStatus::Cancelled;
            $reg->cancelled_at = now();
            $reg->save();

            $reg->tickets()->update([
                'status' => \App\Ems\Enums\TicketStatus::Revoked->value,
                'revoked_at' => now(),
            ]);

            if ($reg->ticket_type_id) {
                $ticketType = \App\Ems\Models\TicketType::query()
                    ->whereKey($reg->ticket_type_id)
                    ->lockForUpdate()
                    ->first();

                if ($ticketType !== null) {
                    $ticketType->quantity_sold = max(0, $ticketType->quantity_sold - $reg->quantity);
                    $ticketType->save();
                }
            }

            app(\App\Ems\Contracts\EventNotificationDispatcher::class)
                ->notifyRegistration(
                    $reg,
                    \App\Ems\Enums\NotificationType::RegistrationCancelled->value,
                    ['idempotency_suffix' => 'user_cancel', 'force' => true]
                );

            app(\App\Ems\Services\WaitlistService::class)->promoteAvailable($reg->event);
        });

        return ApiResponse::success(
            new \App\Ems\Http\Resources\Public\PublicRegistrationResource($reg->fresh(['event', 'tickets', 'ticketType'])),
            'Registration cancelled successfully.'
        );
    }
}
