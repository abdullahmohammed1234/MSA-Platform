<?php

namespace App\Repositories\CMS;

use App\Models\CMS\Event;
use Illuminate\Database\Eloquent\Builder;

class EventRepository
{
    public function list(array $filters = [], int $perPage = 15)
    {
        $query = Event::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['featured'])) {
            $query->where('featured', filter_var($filters['featured'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->withCount('registrations')->orderBy('start_date', 'asc')->paginate($perPage);
    }

    public function getPublished()
    {
        return Event::where('status', 'published')
            ->orderBy('start_date', 'asc')
            ->get();
    }

    public function findByUuid(string $uuid): ?Event
    {
        return Event::where('uuid', $uuid)->first();
    }

    public function create(array $data): Event
    {
        return Event::create($data);
    }

    public function update(Event $event, array $data): bool
    {
        return $event->update($data);
    }

    public function delete(Event $event): bool
    {
        return $event->delete();
    }
}
