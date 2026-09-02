<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ContactFormSubmission;
use App\Mail\VolunteerApplication;
use App\Models\CMS\Announcement;
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

    /**
     * @deprecated Phase 9 — legacy CMS events retired. EMS owns events.
     * Routes still named api.website.events* return 410 Gone.
     */
    public function legacyCmsEventsRetired(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Legacy CMS events API has been retired. Use EMS public events at /api/v1/ems/public/events.',
            'retired' => true,
            'replacement' => '/api/v1/ems/public/events',
        ], 410);
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

    public function submitVolunteer(\App\Http\Requests\StoreVolunteerRegistrationRequest $request, \App\Services\VolunteerRegistrationService $volunteerService): JsonResponse
    {
        $validated = $request->validated();

        $registration = $volunteerService->submit($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jazakullah Khair! Your volunteer application has been received. Our department coordinator will reach out to you.',
            'data' => new \App\Http\Resources\VolunteerRegistrationResource($registration),
        ], 200);
    }

}
