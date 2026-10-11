<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ContactFormSubmission;
use App\Mail\VolunteerApplication;
use App\Models\CMS\Announcement;
use App\Models\CMS\Media;
use App\Models\CMS\TeamMember;
use App\Models\CMS\Resource;
use App\Models\CMS\FeaturedOpportunity;
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

    private function deriveCategory(string $title, ?string $summary): string
    {
        $combined = strtolower($title . ' ' . ($summary ?? ''));
        if (str_contains($combined, 'jumuah') || str_contains($combined, 'prayer') || str_contains($combined, 'khutbah')) {
            return 'Prayer';
        }
        if (str_contains($combined, 'volunteer') || str_contains($combined, 'board') || str_contains($combined, 'committee')) {
            return 'Board';
        }
        if (str_contains($combined, 'event') || str_contains($combined, 'halaqah') || str_contains($combined, 'social')) {
            return 'Events';
        }
        if (str_contains($combined, 'course') || str_contains($combined, 'study') || str_contains($combined, 'workshop')) {
            return 'Education';
        }
        if ($summary && strlen($summary) <= 20 && !str_contains($summary, ' ')) {
            return ucfirst($summary);
        }
        return 'General';
    }

    public function announcements(Request $request): JsonResponse
    {
        $hasFilters = $request->filled('search') || $request->filled('category');

        if ($hasFilters) {
            $query = Announcement::where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now());

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('summary', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%");
                });
            }

            if ($request->filled('category')) {
                $category = strtolower($request->input('category'));
                $query->where(function ($q) use ($category) {
                    $q->where('title', 'like', "%{$category}%")
                      ->orWhere('summary', 'like', "%{$category}%");
                });
            }

            $announcements = $query->orderBy('published_at', 'desc')->get()->map(function ($item) {
                return [
                    'id' => $item->uuid,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'content' => $item->content ?: $item->summary,
                    'summary' => $item->summary ?? 'General',
                    'date' => $item->published_at ? $item->published_at->format('Y-m-d') : null,
                    'category' => $this->deriveCategory($item->title, $item->summary),
                    'featured_image' => CmsAssetUrl::resolve($item->featured_image),
                ];
            })->values()->toArray();

            return response()->json([
                'announcements' => $announcements,
            ]);
        }

        $announcements = Cache::remember('website_announcements', 3600, function () {
            $dbAnnouncements = Announcement::where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->orderBy('published_at', 'desc')
                ->get();

            if ($dbAnnouncements->isEmpty()) {
                return [
                    [
                        'id' => 'ann-1',
                        'title' => "Jumu'ah Location Update",
                        'slug' => 'jumuah-location-update',
                        'content' => "Jumu'ah prayers this week will be held in the West Gym to accommodate more students.",
                        'summary' => 'Jumu\'ah prayers this week will be held in the West Gym.',
                        'date' => '2026-06-08',
                        'category' => 'Prayer',
                        'featured_image' => null,
                    ],
                    [
                        'id' => 'ann-2',
                        'title' => 'Volunteering Open',
                        'slug' => 'volunteering-open',
                        'content' => 'Applications are now open for the 2026 MSA Board committees. Apply today!',
                        'summary' => 'Applications are now open for the 2026 MSA Board committees.',
                        'date' => '2026-06-05',
                        'category' => 'Board',
                        'featured_image' => null,
                    ]
                ];
            }

            return $dbAnnouncements->map(function ($item) {
                return [
                    'id' => $item->uuid,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'content' => $item->content ?: $item->summary,
                    'summary' => $item->summary ?? 'General',
                    'date' => $item->published_at ? $item->published_at->format('Y-m-d') : null,
                    'category' => $this->deriveCategory($item->title, $item->summary),
                    'featured_image' => CmsAssetUrl::resolve($item->featured_image),
                ];
            })->values()->toArray();
        });

        return response()->json([
            'announcements' => $announcements,
        ]);
    }

    public function showAnnouncement(string $slug): JsonResponse
    {
        $announcement = Announcement::with('author:id,name')
            ->where(function ($query) use ($slug) {
                $query->where('slug', $slug)
                      ->orWhere('uuid', $slug);
            })
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->first();

        if (!$announcement) {
            $fallbacks = [
                'jumuah-location-update' => [
                    'id' => 'ann-1',
                    'uuid' => 'ann-1',
                    'title' => "Jumu'ah Location Update",
                    'slug' => 'jumuah-location-update',
                    'content' => "Jumu'ah prayers this week will be held in the West Gym to accommodate more students.\n\nPlease arrive early to ensure seating and follow the instructions of MSA volunteers. Sisters' prayer space will be designated on the upper level with dedicated entrance signage.\n\nFirst Khutbah begins promptly at 1:15 PM, followed by the second congregation at 2:00 PM. Wudu facilities are available in the adjacent athletic center change rooms.\n\nJazakum Allahu Khairan for your cooperation as we work to provide safe and spacious Friday prayer spaces for the SFU community!",
                    'summary' => 'Jumu\'ah prayers this week will be held in the West Gym.',
                    'category' => 'Prayer',
                    'date' => '2026-06-08',
                    'published_at' => '2026-06-08T12:00:00Z',
                    'featured_image' => null,
                    'author' => ['name' => 'SFU MSA Executive Board'],
                ],
                'volunteering-open' => [
                    'id' => 'ann-2',
                    'uuid' => 'ann-2',
                    'title' => 'Volunteering Open',
                    'slug' => 'volunteering-open',
                    'content' => "Applications are now open for the 2026 MSA Board committees. Apply today to serve our campus Muslim community!\n\nPositions are open in Logistics, Media, Dawah, and Event Management. Gain leadership experience, earn volunteer certificates, and give back to your community.",
                    'summary' => 'Applications are now open for the 2026 MSA Board committees.',
                    'category' => 'Board',
                    'date' => '2026-06-05',
                    'published_at' => '2026-06-05T12:00:00Z',
                    'featured_image' => null,
                    'author' => ['name' => 'VMS Committee'],
                ],
            ];

            $fallbackItem = $fallbacks[$slug] ?? null;
            if (!$fallbackItem && ($slug === 'ann-1' || $slug === 'ann-2')) {
                $fallbackItem = $slug === 'ann-1' ? $fallbacks['jumuah-location-update'] : $fallbacks['volunteering-open'];
            }

            if ($fallbackItem) {
                $relatedFallback = array_values(array_filter($fallbacks, fn($f) => $f['slug'] !== $fallbackItem['slug']));
                return response()->json([
                    'announcement' => $fallbackItem,
                    'related' => $relatedFallback,
                ]);
            }

            return response()->json([
                'message' => 'Announcement not found.',
            ], 404);
        }

        $related = Announcement::where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('id', '!=', $announcement->id)
            ->orderBy('published_at', 'desc')
            ->take(3)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->uuid,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'summary' => $item->summary ?? 'General',
                    'date' => $item->published_at ? $item->published_at->format('Y-m-d') : null,
                    'category' => $this->deriveCategory($item->title, $item->summary),
                    'featured_image' => CmsAssetUrl::resolve($item->featured_image),
                ];
            })
            ->values()
            ->toArray();

        return response()->json([
            'announcement' => [
                'id' => $announcement->uuid,
                'uuid' => $announcement->uuid,
                'title' => $announcement->title,
                'slug' => $announcement->slug,
                'content' => $announcement->content ?: ($announcement->summary ?: 'Official announcement update from SFU Muslim Students\' Association.'),
                'summary' => $announcement->summary ?? 'General',
                'category' => $this->deriveCategory($announcement->title, $announcement->summary),
                'date' => $announcement->published_at ? $announcement->published_at->format('Y-m-d') : null,
                'published_at' => $announcement->published_at ? $announcement->published_at->toIso8601String() : null,
                'featured_image' => CmsAssetUrl::resolve($announcement->featured_image),
                'author' => $announcement->author ? [
                    'name' => $announcement->author->name,
                ] : [
                    'name' => 'SFU MSA Team',
                ],
            ],
            'related' => $related,
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

    public function featuredOpportunities(): JsonResponse
    {
        $opportunities = Cache::remember('website_featured_opportunities', 86400, function () {
            return FeaturedOpportunity::where('is_published', true)
                ->orderBy('sort_order', 'asc')
                ->orderBy('published_at', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->uuid,
                        'title' => $item->title,
                        'slug' => $item->slug,
                        'eyebrow' => $item->eyebrow ?? 'Featured Opportunity',
                        'short_description' => $item->short_description,
                        'description' => $item->description,
                        'featured_image' => CmsAssetUrl::resolve($item->featured_image),
                        'external_url' => $item->external_url,
                        'features' => $item->features ?? [],
                        'is_published' => $item->is_published,
                        'published_at' => $item->published_at?->toIso8601String(),
                        'sort_order' => $item->sort_order,
                    ];
                })
                ->toArray();
        });

        return response()->json([
            'opportunities' => $opportunities,
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
