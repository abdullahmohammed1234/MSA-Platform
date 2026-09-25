<?php

namespace App\Volunteering\Services;

use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Team;
use App\Volunteering\Models\Shift;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VolunteerOpportunityService
{
    public function listEligibleEvents(): Collection
    {
        return \App\Ems\Models\Event::select('id', 'uuid', 'name', 'slug', 'description', 'short_description', 'banner_url', 'location', 'start_at', 'end_at', 'status')
            ->orderBy('start_at', 'desc')
            ->get()
            ->map(function ($event) {
                $configuredOpportunity = Opportunity::where('event_id', $event->id)->first();
                return [
                    'id' => $event->id,
                    'uuid' => $event->uuid,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'description' => $event->description ?? $event->short_description,
                    'location' => $event->location,
                    'start_at' => $event->start_at ? $event->start_at->toIso8601String() : null,
                    'end_at' => $event->end_at ? $event->end_at->toIso8601String() : null,
                    'banner_url' => $event->banner_url,
                    'status' => $event->status,
                    'is_configured' => $configuredOpportunity !== null,
                    'opportunity_id' => $configuredOpportunity?->id,
                ];
            });
    }

    public function listPublicOpportunities(array $filters = []): LengthAwarePaginator
    {
        $query = Opportunity::with(['event:id,name,slug,description,short_description,banner_url,start_at,end_at,location', 'teams', 'shifts'])
            ->where('status', 'open');

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                  ->orWhere('description', 'like', $search)
                  ->orWhere('location', 'like', $search)
                  ->orWhereHas('event', function ($eq) use ($search) {
                      $eq->where('name', 'like', $search)
                         ->orWhere('description', 'like', $search);
                  });
            });
        }

        if (!empty($filters['event_id'])) {
            $query->where('event_id', $filters['event_id']);
        }

        return $query->orderBy('start_at', 'asc')->paginate($filters['per_page'] ?? 15);
    }

    public function listAdminOpportunities(array $filters = []): LengthAwarePaginator
    {
        $query = Opportunity::with(['event:id,name,slug,banner_url', 'teams', 'shifts', 'creator:id,name'])
            ->withCount(['signups' => function ($q) {
                $q->whereIn('status', ['signed_up', 'confirmed', 'completed']);
            }]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($filters['per_page'] ?? 15);
    }

    public function getOpportunityBySlug(string $slug): Opportunity
    {
        return Opportunity::with(['event', 'teams.shifts', 'shifts'])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function createOpportunity(array $data, int $userId): Opportunity
    {
        return DB::transaction(function () use ($data, $userId) {
            $event = null;
            if (!empty($data['event_id'])) {
                $event = \App\Ems\Models\Event::find($data['event_id']);
            }

            $title = $data['title'] ?? ($event ? $event->name : 'Volunteer Opportunity');
            $slug = $data['slug'] ?? ($event ? $event->slug : Str::slug($title) . '-' . Str::random(5));
            $description = $data['description'] ?? ($event ? ($event->description ?? $event->short_description) : null);
            $location = $data['location'] ?? ($event ? $event->location : null);
            $startAt = $data['start_at'] ?? ($event ? $event->start_at : null);
            $endAt = $data['end_at'] ?? ($event ? $event->end_at : null);

            $opportunity = Opportunity::create([
                'title' => $title,
                'slug' => $slug,
                'description' => $description,
                'event_id' => $data['event_id'] ?? null,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'location' => $location,
                'capacity' => $data['capacity'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'published_at' => ($data['status'] ?? 'draft') === 'open' ? now() : null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            if (!empty($data['teams']) && is_array($data['teams'])) {
                foreach ($data['teams'] as $index => $teamData) {
                    $team = $opportunity->teams()->create([
                        'name' => $teamData['name'],
                        'description' => $teamData['description'] ?? null,
                        'capacity' => $teamData['capacity'] ?? null,
                        'status' => $teamData['status'] ?? 'open',
                        'ordering' => $index,
                    ]);

                    if (!empty($teamData['shifts']) && is_array($teamData['shifts'])) {
                        foreach ($teamData['shifts'] as $shiftData) {
                            $team->shifts()->create([
                                'opportunity_id' => $opportunity->id,
                                'name' => $shiftData['name'] ?? null,
                                'start_at' => $shiftData['start_at'],
                                'end_at' => $shiftData['end_at'],
                                'capacity' => $shiftData['capacity'] ?? 10,
                                'status' => $shiftData['status'] ?? 'open',
                            ]);
                        }
                    }
                }
            }

            if (!empty($data['shifts']) && is_array($data['shifts'])) {
                foreach ($data['shifts'] as $shiftData) {
                    if (empty($shiftData['team_id'])) {
                        $opportunity->shifts()->create([
                            'name' => $shiftData['name'] ?? null,
                            'start_at' => $shiftData['start_at'],
                            'end_at' => $shiftData['end_at'],
                            'capacity' => $shiftData['capacity'] ?? 10,
                            'status' => $shiftData['status'] ?? 'open',
                        ]);
                    }
                }
            }

            return $opportunity->load(['teams.shifts', 'shifts']);
        });
    }

    public function updateOpportunity(Opportunity $opportunity, array $data, int $userId): Opportunity
    {
        return DB::transaction(function () use ($opportunity, $data, $userId) {
            $opportunity->update(array_filter([
                'title' => $data['title'] ?? $opportunity->title,
                'slug' => $data['slug'] ?? $opportunity->slug,
                'description' => $data['description'] ?? $opportunity->description,
                'event_id' => array_key_exists('event_id', $data) ? $data['event_id'] : $opportunity->event_id,
                'start_at' => $data['start_at'] ?? $opportunity->start_at,
                'end_at' => $data['end_at'] ?? $opportunity->end_at,
                'location' => $data['location'] ?? $opportunity->location,
                'capacity' => $data['capacity'] ?? $opportunity->capacity,
                'status' => $data['status'] ?? $opportunity->status,
                'updated_by' => $userId,
            ], fn ($val) => $val !== null));

            if (isset($data['status']) && $data['status'] === 'open' && !$opportunity->published_at) {
                $opportunity->update(['published_at' => now()]);
            }

            return $opportunity->fresh(['teams.shifts', 'shifts']);
        });
    }

    public function getAnalytics(): array
    {
        $totalOpportunities = Opportunity::count();
        $openOpportunities = Opportunity::where('status', 'open')->count();
        $totalSignups = \App\Volunteering\Models\Signup::count();
        $activeSignups = \App\Volunteering\Models\Signup::whereIn('status', ['signed_up', 'confirmed', 'completed'])->count();
        $completedSignups = \App\Volunteering\Models\Signup::where('status', 'completed')->count();

        return [
            'total_opportunities' => $totalOpportunities,
            'open_opportunities' => $openOpportunities,
            'total_signups' => $totalSignups,
            'active_signups' => $activeSignups,
            'completed_signups' => $completedSignups,
        ];
    }
}
