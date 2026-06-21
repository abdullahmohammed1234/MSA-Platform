<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ContactFormSubmission;
use App\Mail\EventRsvpConfirmation;
use App\Models\CMS\Announcement;
use App\Models\CMS\Event;
use App\Models\CMS\EventRegistration;
use App\Models\CMS\Media;
use App\Models\CMS\TeamMember;
use App\Models\CMS\Resource;
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
                ->where('mime_type', 'like', 'image/%')
                ->orderByDesc('created_at')
                ->get()
                ->map(function (Media $item) {
                    $title = pathinfo($item->filename, PATHINFO_FILENAME);
                    $title = str_replace(['-', '_'], ' ', $title);

                    return [
                        'id' => $item->uuid,
                        'url' => $item->url,
                        'title' => ucwords($title),
                        'description' => 'Uploaded via CMS media library.',
                        'category' => 'Community',
                        'date' => $item->created_at?->format('Y') ?? date('Y'),
                        'isLandscape' => true,
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
            'email' => 'required|email|max:255',
            'department' => 'required|string|max:255',
            'interests' => 'required|string|max:5000',
            'experience' => 'nullable|string|max:5000',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jazakullah Khair! Your volunteer application has been received. Our department coordinator will reach out to you.',
        ]);
    }

    public function submitEventRsvp(Request $request, string $eventId): JsonResponse
    {
        $validated = $request->validate([
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'studentId' => 'required|string|max:50',
        ]);

        $event = Event::query()
            ->where('status', 'published')
            ->where(function ($query) use ($eventId) {
                $query->where('uuid', $eventId);

                if (is_numeric($eventId)) {
                    $query->orWhere('id', (int) $eventId);
                }
            })
            ->first();

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

        if ($event->spots_left <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'This event is full. No spots remain.',
            ], 422);
        }

        $email = strtolower($validated['email']);

        if (EventRegistration::where('event_id', $event->id)->where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This email is already registered for this event.',
            ], 422);
        }

        try {
            $registration = DB::transaction(function () use ($event, $validated, $email, $request) {
                $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

                if ($lockedEvent->spots_left <= 0) {
                    throw new \RuntimeException('full');
                }

                $created = EventRegistration::create([
                    'event_id' => $lockedEvent->id,
                    'user_id' => $request->user()?->id,
                    'first_name' => $validated['firstName'],
                    'last_name' => $validated['lastName'],
                    'email' => $email,
                    'student_id' => $validated['studentId'],
                    'status' => 'confirmed',
                ]);

                $lockedEvent->decrement('spots_left');

                return $created;
            });
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'full') {
                return response()->json([
                    'success' => false,
                    'message' => 'This event is full. No spots remain.',
                ], 422);
            }

            throw $exception;
        }

        Cache::forget('website_events');

        if ($request->user()) {
            $this->analyticsService->trackEventRegistration(
                $request->user()->id,
                $event->id,
                null,
                [
                    'email' => $email,
                    'student_id' => $validated['studentId'],
                ]
            );
        }

        try {
            Mail::to($email)->send(new EventRsvpConfirmation(
                $event->fresh(),
                trim($validated['firstName'].' '.$validated['lastName']),
                $email,
                $validated['studentId'],
            ));
        } catch (Throwable $exception) {
            Log::warning('Event RSVP confirmation email failed', [
                'error' => $exception->getMessage(),
                'event_id' => $event->id,
                'registration_id' => $registration->id,
                'email' => $email,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registration successful! Check your email for confirmation.',
            'spotsLeft' => $event->fresh()->spots_left,
            'registrationId' => $registration->uuid,
        ]);
    }
}
