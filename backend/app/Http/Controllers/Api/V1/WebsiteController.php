<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ContactFormSubmission;
use App\Mail\EventRsvpConfirmation;
use App\Mail\VolunteerApplication;
use App\Models\CMS\Announcement;
use App\Models\CMS\Event;
use App\Models\CMS\EventRegistration;
use App\Models\CMS\Media;
use App\Models\CMS\TeamMember;
use App\Models\CMS\Resource;
use App\Services\CMS\EventCheckInQrService;
use App\Services\CMS\HomepageService;
use App\Services\Analytics\AnalyticsService;
use App\Services\NewsletterService;
use App\Services\PrayerTimesService;
use App\Support\CmsAssetUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class WebsiteController extends Controller
{
    protected $homepageService;

    protected $analyticsService;

    protected $newsletterService;

    protected $prayerTimesService;

    public function __construct(
        HomepageService $homepageService,
        AnalyticsService $analyticsService,
        NewsletterService $newsletterService,
        PrayerTimesService $prayerTimesService,
    ) {
        $this->homepageService = $homepageService;
        $this->analyticsService = $analyticsService;
        $this->newsletterService = $newsletterService;
        $this->prayerTimesService = $prayerTimesService;
    }

    public function homepage(): JsonResponse
    {
        $data = Cache::rememberForever('website_homepage', function () {
            return $this->homepageService->getHomepageData();
        });

        return response()->json([
            'homepage' => $this->transformHomepageData($data),
        ]);
    }

    public function media(): JsonResponse
    {
        $media = Cache::remember('website_media', 3600, function () {
            return Media::query()
                ->with('category:id,name')
                ->where(function ($query) {
                    $query->where('mime_type', 'like', 'image/%')
                        ->orWhere('mime_type', 'like', 'video/%');
                })
                ->orderByDesc('created_at')
                ->get()
                ->map(function (Media $item) {
                    $title = $item->display_name;
                    if ($title === null || trim($title) === '') {
                        $title = pathinfo($item->filename, PATHINFO_FILENAME);
                        $title = str_replace(['-', '_'], ' ', $title);
                        $title = ucwords($title);
                    }

                    $mediaType = $item->media_type;
                    if (!in_array($mediaType, ['image', 'video'], true)) {
                        $mediaType = str_starts_with((string) $item->mime_type, 'video/') ? 'video' : 'image';
                    }

                    return [
                        'id' => $item->uuid,
                        'url' => $item->url,
                        'title' => $title,
                        'description' => 'Uploaded via CMS media library.',
                        'category' => $item->category?->name ?: 'Community',
                        'date' => $item->created_at?->format('Y') ?? date('Y'),
                        'isLandscape' => true,
                        'media_type' => $mediaType,
                        'mime_type' => $item->mime_type,
                    ];
                })
                ->values()
                ->all();
        });

        return response()->json([
            'media' => $media,
        ]);
    }

    public function announcements(): JsonResponse
    {
        $announcements = Cache::remember('website_announcements', 43200, function () {
            $dbAnnouncements = Announcement::where('status', 'published')
                ->whereNotNull('published_at')
                ->orderBy('published_at', 'desc')
                ->get();

            if ($dbAnnouncements->isEmpty()) {
                return [
                    [
                        'id' => 'ann-1',
                        'title' => "Jumu'ah Location Update",
                        'content' => "Jumu'ah prayers this week will be held in the West Gym to accommodate more students.",
                        'date' => '2026-06-08',
                        'category' => 'Prayer'
                    ],
                    [
                        'id' => 'ann-2',
                        'title' => 'Volunteering Open',
                        'content' => 'Applications are now open for the 2026 MSA Board committees. Apply today!',
                        'date' => '2026-06-05',
                        'category' => 'Board'
                    ]
                ];
            }

            return $dbAnnouncements->map(function ($item) {
                return [
                    'id' => $item->uuid,
                    'title' => $item->title,
                    'content' => $item->content,
                    'date' => $item->published_at->format('Y-m-d'),
                    'category' => $item->summary ?? 'General',
                    'featured_image' => CmsAssetUrl::resolve($item->featured_image),
                ];
            })->toArray();
        });

        return response()->json([
            'announcements' => $announcements,
        ]);
    }

    public function events(): JsonResponse
    {
        $events = Cache::remember('website_events', 43200, function () {
            $dbEvents = Event::where('status', 'published')
                ->orderBy('start_date', 'asc')
                ->get();

            if ($dbEvents->isEmpty()) {
                return [
                    [
                        'id' => '1',
                        'title' => "The Heart's Journey: Spiritual Heights",
                        'date' => '2026-06-15',
                        'time' => '6:00 PM - 8:30 PM',
                        'location' => 'SFU Burnaby, WMC 3260',
                        'category' => 'Lecture',
                        'image' => 'https://images.unsplash.com/photo-1519751138087-5bf79df62d5b?auto=format&fit=crop&q=80',
                        'description' => 'An evening dedicated to exploring the depths of spiritual growth and finding peace in a chaotic world. Featuring guest speakers and interactive reflection sessions.',
                        'spotsLeft' => 45,
                        'featured' => true,
                        'registrationDeadline' => '2026-06-14',
                        'startDate' => '2026-06-15T18:00:00+00:00',
                        'endDate' => '2026-06-15T20:30:00+00:00',
                    ],
                    [
                        'id' => '2',
                        'title' => 'Weekly Friday Jummah Prayer',
                        'date' => 'Every Friday',
                        'time' => '1:30 PM',
                        'location' => 'SFU Multi-Faith Centre / MBC',
                        'category' => 'Jummah',
                        'image' => 'https://images.unsplash.com/photo-1542810634-71277d95dcbb?auto=format&fit=crop&q=80',
                        'description' => 'Join our weekly congregation for Jummah prayer on campus. Multiple shifts available depending on room capacity.',
                        'spotsLeft' => 200,
                        'registrationDeadline' => '2026-12-31',
                        'startDate' => '2026-06-12T13:30:00+00:00',
                        'endDate' => '2026-06-12T14:30:00+00:00',
                    ]
                ];
            }

            return $dbEvents->map(function ($item) {
                return [
                    'id' => $item->uuid,
                    'title' => $item->title,
                    'date' => $item->date,
                    'time' => $item->time,
                    'location' => $item->location,
                    'category' => $item->category,
                    'image' => CmsAssetUrl::resolve($item->image) ?? 'https://images.unsplash.com/photo-1519751138087-5bf79df62d5b?auto=format&fit=crop&q=80',
                    'description' => $item->description,
                    'spotsLeft' => $item->spots_left,
                    'featured' => $item->featured,
                    'registrationDeadline' => $item->registration_deadline ? $item->registration_deadline->format('Y-m-d') : '2026-12-31',
                    'startDate' => $item->start_date?->toIso8601String(),
                    'endDate' => $item->end_date?->toIso8601String(),
                ];
            })->toArray();
        });

        return response()->json([
            'events' => $events,
        ]);
    }

    public function showEvent(string $eventId): JsonResponse
    {
        $event = $this->findPublishedEvent($eventId);

        if (! $event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found.',
            ], 404);
        }

        return response()->json([
            'event' => $this->transformPublicEvent($event),
        ]);
    }

    public function team(): JsonResponse
    {
        $team = Cache::remember('website_team', 86400, function () {
            $dbTeam = TeamMember::where('status', 'published')
                ->orderBy('display_order', 'asc')
                ->get();

            if ($dbTeam->isEmpty()) {
                return config('website_defaults.team', []);
            }

            return $dbTeam->map(function ($item) {
                return [
                    'name' => $item->name,
                    'role' => $item->role,
                    'dept' => $item->dept,
                    'img' => CmsAssetUrl::resolve($item->img) ?? '/Team/Sample_User_Icon.webp',
                ];
            })->toArray();
        });

        if (empty($team)) {
            $team = config('website_defaults.team', []);
        }

        return response()->json([
            'team' => array_values($team),
        ]);
    }

    public function resources(): JsonResponse
    {
        $resources = Cache::remember('website_resources', 86400, function () {
            $dbResources = Resource::where('status', 'published')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($dbResources->isEmpty()) {
                return [
                    [
                        'id' => 'revert-guide-1',
                        'title' => 'New Muslim Starter Kit',
                        'description' => 'A comprehensive guide for those new to Islam, covering prayer basics, common terms, and community support.',
                        'category' => 'New Muslim',
                        'iconName' => 'Sparkles',
                        'link' => '#',
                        'isExternal' => false,
                        'tags' => ['revert', 'basics', 'guide']
                    ]
                ];
            }

            return $dbResources->map(function ($item) {
                return [
                    'id' => $item->uuid,
                    'title' => $item->title,
                    'description' => $item->description,
                    'category' => $item->category,
                    'iconName' => $item->icon_name,
                    'link' => CmsAssetUrl::resolve($item->link) ?? $item->link,
                    'thumbnail' => CmsAssetUrl::resolve($item->thumbnail),
                    'file' => CmsAssetUrl::resolve($item->file),
                    'isExternal' => $item->is_external,
                    'tags' => $item->tags ?? []
                ];
            })->toArray();
        });

        return response()->json([
            'resources' => $resources,
        ]);
    }

    public function prayerTimes(): JsonResponse
    {
        $times = $this->prayerTimesService->getPrayerTimesByCampus();

        if (empty($times)) {
            return response()->json([
                'message' => 'Prayer times are temporarily unavailable.',
            ], 503);
        }

        return response()->json([
            'times' => $times,
        ]);
    }

    public function sponsors(): JsonResponse
    {
        $sponsors = Cache::remember('website_sponsors', 86400, function () {
            return [
                ['id' => 'sp-1', 'name' => 'Halal Grill Co.', 'tier' => 'Platinum', 'logoUrl' => 'https://images.unsplash.com/photo-1498654896293-37aacf113fd9?w=300&auto=format&fit=crop&q=80'],
                ['id' => 'sp-2', 'name' => 'Al-Huda Bookstore', 'tier' => 'Gold', 'logoUrl' => 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=300&auto=format&fit=crop&q=80'],
                ['id' => 'sp-3', 'name' => 'Momin Clothing', 'tier' => 'Silver', 'logoUrl' => 'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=300&auto=format&fit=crop&q=80'],
                ['id' => 'sp-4', 'name' => 'GVA Halal Foods', 'tier' => 'Gold', 'logoUrl' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300&auto=format&fit=crop&q=80']
            ];
        });

        return response()->json([
            'sponsors' => $sponsors,
        ]);
    }

    private function transformHomepageData(array $data): array
    {
        if (isset($data['hero']['background_image'])) {
            $data['hero']['background_image'] = CmsAssetUrl::resolve($data['hero']['background_image']);
        }

        return $data;
    }

    public function subscribeNewsletter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        try {
            $result = $this->newsletterService->subscribe($validated['email']);
        } catch (Throwable $exception) {
            Log::error('Newsletter subscription failed', [
                'email' => $validated['email'],
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'We could not process your subscription right now. Please try again later.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
        ]);
    }

    public function submitContact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        try {
            Mail::to(config('website.contact_recipient'))
                ->send(new ContactFormSubmission(
                    $validated['name'],
                    $validated['email'],
                    $validated['subject'],
                    $validated['message'],
                ));
        } catch (Throwable $exception) {
            Log::error('Contact form email failed', [
                'error' => $exception->getMessage(),
                'sender_email' => $validated['email'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Your message could not be sent right now. Please try again later.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your message has been sent successfully. Our team will get back to you soon!',
        ]);
    }

    public function submitSponsor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'companyName' => 'required|string|max:255',
            'contactName' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'tierPreference' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your sponsorship inquiry has been received. Our sponsorship team will contact you shortly.',
        ]);
    }

    public function submitVolunteer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                function ($attribute, $value, $fail) {
                    $email = trim(strtolower($value));
                    if (!preg_match('/^[a-zA-Z0-9._%+-]+@(?:[a-zA-Z0-9-]+\.)*sfu\.ca$/i', $email)) {
                        $fail('Volunteers must register with an @sfu.ca email address.');
                    }
                },
            ],
            'student_number' => 'required|string|regex:/^\d{9}$/',
            'department' => 'required|string|max:255',
            'interests' => 'required|string|max:5000',
            'experience' => 'nullable|string|max:5000',
        ]);

        try {
            Mail::to(config('website.contact_recipient'))
                ->send(new VolunteerApplication(
                    $validated['name'],
                    $validated['email'],
                    $validated['student_number'],
                    $validated['department'],
                    $validated['interests'],
                    $validated['experience'] ?? null
                ));
        } catch (Throwable $exception) {
            Log::error('Volunteer application form email failed', [
                'error' => $exception->getMessage(),
                'sender_email' => $validated['email'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Your application could not be sent right now. Please try again later.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Jazakullah Khair! Your volunteer application has been received. Our department coordinator will reach out to you.',
        ]);
    }

    public function submitEventRsvp(Request $request, string $eventId): JsonResponse
    {
        $validated = $request->validate([
            'attendees' => 'nullable|array|min:1|max:10',
            'attendees.*.firstName' => 'required_with:attendees|string|max:255',
            'attendees.*.lastName' => 'required_with:attendees|string|max:255',
            'attendees.*.email' => 'required_with:attendees|email|max:255',
            'attendees.*.phone' => 'required_with:attendees|string|max:40',
            'firstName' => 'required_without:attendees|string|max:255',
            'lastName' => 'required_without:attendees|string|max:255',
            'email' => 'required_without:attendees|email|max:255',
            'phone' => 'required_without:attendees|string|max:40',
        ]);

        $attendees = collect($validated['attendees'] ?? [])
            ->map(function (array $attendee) {
                return [
                    'firstName' => trim($attendee['firstName']),
                    'lastName' => trim($attendee['lastName']),
                    'email' => strtolower(trim($attendee['email'])),
                    'phone' => trim($attendee['phone']),
                ];
            })
            ->values();

        if ($attendees->isEmpty() && ! empty($validated['email'])) {
            $attendees = collect([[
                'firstName' => trim((string) ($validated['firstName'] ?? '')),
                'lastName' => trim((string) ($validated['lastName'] ?? '')),
                'email' => strtolower(trim((string) $validated['email'])),
                'phone' => trim((string) ($validated['phone'] ?? '')),
            ]]);
        }

        if ($attendees->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide at least one attendee.',
            ], 422);
        }

        $duplicateEmails = $attendees->pluck('email')->duplicates()->values();
        if ($duplicateEmails->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Each attendee must use a unique email address.',
            ], 422);
        }

        $event = $this->findPublishedEvent($eventId);

        if (! $event) {
            return response()->json([
                'success' => false,
                'message' => 'This event is not available for registration.',
            ], 404);
        }

        if ($event->registration_deadline && now()->startOfDay()->gt($event->registration_deadline)) {
            return response()->json([
                'success' => false,
                'message' => 'Registration for this event has closed.',
            ], 422);
        }

        $partySize = $attendees->count();

        if ($event->spots_left < $partySize) {
            return response()->json([
                'success' => false,
                'message' => $event->spots_left <= 0
                    ? 'This event is full. No spots remain.'
                    : "Only {$event->spots_left} spot(s) remain for this event.",
            ], 422);
        }

        $existingEmails = EventRegistration::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [
                EventRegistration::STATUS_REGISTERED,
                EventRegistration::STATUS_ATTENDING,
            ])
            ->whereIn('email', $attendees->pluck('email')->all())
            ->pluck('email');

        if ($existingEmails->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'One or more emails are already registered for this event: '.$existingEmails->unique()->implode(', '),
            ], 422);
        }

        $groupId = (string) Str::uuid();

        try {
            $registrations = DB::transaction(function () use ($event, $attendees, $request, $partySize, $groupId) {
                $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

                if ($lockedEvent->spots_left < $partySize) {
                    throw new \RuntimeException('full');
                }

                $created = [];
                $hasPhone = Schema::hasColumn('event_registrations', 'phone');
                $hasGroupId = Schema::hasColumn('event_registrations', 'registration_group_id');

                foreach ($attendees as $attendee) {
                    $payload = [
                        'event_id' => $lockedEvent->id,
                        'user_id' => $request->user()?->id,
                        'first_name' => $attendee['firstName'],
                        'last_name' => $attendee['lastName'],
                        'email' => $attendee['email'],
                        'student_id' => '',
                        'status' => EventRegistration::STATUS_REGISTERED,
                    ];

                    if ($hasPhone) {
                        $payload['phone'] = $attendee['phone'];
                    }

                    if ($hasGroupId) {
                        $payload['registration_group_id'] = $groupId;
                    }

                    $created[] = EventRegistration::create($payload);
                }

                $lockedEvent->decrement('spots_left', $partySize);

                return collect($created);
            });
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'full') {
                return response()->json([
                    'success' => false,
                    'message' => 'This event is full. No spots remain.',
                ], 422);
            }

            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Event RSVP registration failed', [
                'error' => $exception->getMessage(),
                'event_id' => $event->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration could not be completed right now. Please try again shortly.',
            ], 500);
        }

        Cache::forget('website_events');

        $freshEvent = $event->fresh();
        $qrService = app(EventCheckInQrService::class);

        foreach ($registrations as $registration) {
            if ($request->user()) {
                $this->analyticsService->trackEventRegistration(
                    $request->user()->id,
                    $event->id,
                    null,
                    [
                        'email' => $registration->email,
                        'registration_id' => $registration->uuid,
                    ]
                );
            }

            try {
                Mail::to($registration->email)->send(new EventRsvpConfirmation(
                    $freshEvent,
                    $registration,
                    $registration->full_name,
                    $registration->email,
                    (string) $registration->phone,
                    $qrService->payloadForRegistration($registration->uuid, $freshEvent->uuid),
                ));
            } catch (Throwable $exception) {
                Log::warning('Event RSVP confirmation email failed', [
                    'error' => $exception->getMessage(),
                    'event_id' => $event->id,
                    'registration_id' => $registration->id,
                    'email' => $registration->email,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => $partySize === 1
                ? 'Registration successful! Check your email for your QR code.'
                : "Registration successful for {$partySize} people! Each person will receive a confirmation email with their QR code.",
            'spotsLeft' => $freshEvent->spots_left,
            'registrationGroupId' => $groupId,
            'registrations' => $registrations->map(fn (EventRegistration $registration) => [
                'registrationId' => $registration->uuid,
                'name' => $registration->full_name,
                'email' => $registration->email,
                'phone' => $registration->phone,
                'status' => $registration->status,
                'checkInCode' => $qrService->payloadForRegistration($registration->uuid, $freshEvent->uuid),
            ])->values(),
            'registrationId' => $registrations->first()?->uuid,
        ]);
    }

    public function myEventRegistrations(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'registrations' => [],
            ]);
        }

        $email = strtolower($user->email);

        $registrations = EventRegistration::query()
            ->with('event:id,uuid')
            ->whereIn('status', [
                EventRegistration::STATUS_REGISTERED,
                EventRegistration::STATUS_ATTENDING,
            ])
            ->where(function ($query) use ($user, $email) {
                $query->where('user_id', $user->id)
                    ->orWhere('email', $email);
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(function (EventRegistration $registration) {
                return [
                    'eventId' => $registration->event?->uuid ?? (string) $registration->event_id,
                    'registrationId' => $registration->uuid,
                    'status' => $registration->status,
                    'registeredAt' => $registration->created_at?->toIso8601String(),
                    'checkedInAt' => $registration->checked_in_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'registrations' => $registrations,
        ]);
    }

    public function cancelEventRsvp(Request $request, string $eventId): JsonResponse
    {
        $validated = $request->validate([
            'registrationId' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $event = $this->findPublishedEvent($eventId);

        if (! $event) {
            return response()->json([
                'success' => false,
                'message' => 'This event is not available.',
            ], 404);
        }

        $registrationUuid = app(EventCheckInQrService::class)
            ->parseRegistrationUuid($validated['registrationId'] ?? null);
        $email = isset($validated['email']) ? strtolower($validated['email']) : null;

        $registrationQuery = EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('status', EventRegistration::STATUS_REGISTERED);

        if ($registrationUuid) {
            $registrationQuery->where('uuid', $registrationUuid);

            if ($email) {
                $registrationQuery->where('email', $email);
            }
        } elseif ($request->user()) {
            $userEmail = strtolower($request->user()->email);
            $registrationQuery->where(function ($query) use ($request, $userEmail) {
                $query->where('user_id', $request->user()->id)
                    ->orWhere('email', $userEmail);
            });
        } elseif ($email) {
            $registrationQuery->where('email', $email);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Provide your registration ID from the confirmation email to cancel.',
            ], 422);
        }

        $registration = $registrationQuery->first();

        if (! $registration) {
            return response()->json([
                'success' => false,
                'message' => 'No registration was found for this event.',
            ], 404);
        }

        if ($registration->status === EventRegistration::STATUS_ATTENDING) {
            return response()->json([
                'success' => false,
                'message' => 'This registration has already been checked in and cannot be cancelled.',
            ], 422);
        }

        DB::transaction(function () use ($registration, $event) {
            $registration->delete();

            Event::query()
                ->whereKey($event->id)
                ->increment('spots_left');
        });

        Cache::forget('website_events');

        return response()->json([
            'success' => true,
            'message' => 'Your registration has been cancelled.',
            'spotsLeft' => $event->fresh()->spots_left,
        ]);
    }

    private function findPublishedEvent(string $eventId): ?Event
    {
        return Event::query()
            ->where('status', 'published')
            ->where(function ($query) use ($eventId) {
                $query->where('uuid', $eventId);

                if (is_numeric($eventId)) {
                    $query->orWhere('id', (int) $eventId);
                }
            })
            ->first();
    }

    private function transformPublicEvent(Event $item): array
    {
        return [
            'id' => $item->uuid,
            'title' => $item->title,
            'date' => $item->date,
            'time' => $item->time,
            'location' => $item->location,
            'category' => $item->category,
            'image' => CmsAssetUrl::resolve($item->image) ?? 'https://images.unsplash.com/photo-1519751138087-5bf79df62d5b?auto=format&fit=crop&q=80',
            'description' => $item->description,
            'spotsLeft' => $item->spots_left,
            'featured' => $item->featured,
            'registrationDeadline' => $item->registration_deadline ? $item->registration_deadline->format('Y-m-d') : '2026-12-31',
            'startDate' => $item->start_date?->toIso8601String(),
            'endDate' => $item->end_date?->toIso8601String(),
        ];
    }
}
