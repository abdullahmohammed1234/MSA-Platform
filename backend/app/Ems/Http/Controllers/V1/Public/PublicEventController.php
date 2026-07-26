<?php

namespace App\Ems\Http\Controllers\V1\Public;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Requests\Public\CalendarPublicEventRequest;
use App\Ems\Http\Requests\Public\CheckoutEventRequest;
use App\Ems\Http\Requests\Public\IndexPublicEventRequest;
use App\Ems\Http\Requests\Public\JoinWaitlistRequest;
use App\Ems\Http\Requests\Public\RegisterForEventRequest;
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
}
