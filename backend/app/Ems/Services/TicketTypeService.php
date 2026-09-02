<?php

namespace App\Ems\Services;

use App\Ems\Exceptions\EmsException;
use App\Ems\Exceptions\ResourceInUseException;
use App\Ems\Models\Event;
use App\Ems\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TicketTypeService
{
    /**
     * @return Collection<int, TicketType>
     */
    public function listForEvent(Event $event, bool $publicOnly = false): Collection
    {
        $query = $event->ticketTypes()->with('squareCatalogMapping')->ordered();

        if ($publicOnly) {
            $query->publiclyAvailable();
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Event $event, array $data, ?User $actor = null): TicketType
    {
        $name = (string) ($data['name'] ?? '');

        // Check if an active ticket type with the same name already exists
        $activeExists = TicketType::query()
            ->where('event_id', $event->id)
            ->where('name', $name)
            ->exists();

        if ($activeExists) {
            throw new EmsException(
                'A ticket type with this name already exists for this event.',
                ['name' => ['A ticket type with this name already exists for this event.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Check if a soft-deleted ticket type with the same name exists
        /** @var TicketType|null $trashed */
        $trashed = TicketType::onlyTrashed()
            ->where('event_id', $event->id)
            ->where('name', $name)
            ->first();

        if ($trashed !== null) {
            $trashed->restore();
            $this->fill($trashed, $data);
            $trashed->save();
            $this->queueSquareSync($trashed);

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.ticket_types.restored', [
                    'event_uuid' => $event->uuid,
                    'ticket_type_uuid' => $trashed->uuid,
                    'actor_id' => $actor?->id,
                ]);

            return $trashed->fresh();
        }

        $ticketType = new TicketType();
        $ticketType->event_id = $event->id;
        $this->fill($ticketType, $data);
        $ticketType->quantity_sold = 0;
        $ticketType->save();

        $this->queueSquareSync($ticketType);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.ticket_types.created', [
                'event_uuid' => $event->uuid,
                'ticket_type_uuid' => $ticketType->uuid,
                'actor_id' => $actor?->id,
            ]);

        return $ticketType->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TicketType $ticketType, array $data, ?User $actor = null): TicketType
    {
        $hasHistory = $ticketType->quantity_sold > 0
            || $ticketType->registrations()->withTrashed()->exists()
            || $ticketType->tickets()->withTrashed()->exists();

        if ($hasHistory) {
            if (array_key_exists('price', $data) && (float) $data['price'] !== (float) $ticketType->price) {
                throw new EmsException(
                    'Price cannot be changed after registrations or sales have occurred. Create a new ticket type or deactivate this one instead.',
                    ['price' => ['Price cannot be changed after sales/registrations exist.']],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
            if (array_key_exists('currency', $data) && strtoupper((string) $data['currency']) !== strtoupper((string) $ticketType->currency)) {
                throw new EmsException(
                    'Currency cannot be changed after registrations or sales have occurred.',
                    ['currency' => ['Currency cannot be changed after sales/registrations exist.']],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        $this->fill($ticketType, $data);

        if (array_key_exists('quantity', $data)
            && $data['quantity'] !== null
            && (int) $data['quantity'] < $ticketType->quantity_sold
        ) {
            throw new EmsException(
                'Quantity cannot be less than tickets already sold.',
                ['quantity' => ['Quantity must be at least ' . $ticketType->quantity_sold . '.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $ticketType->save();

        $this->queueSquareSync($ticketType);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.ticket_types.updated', [
                'ticket_type_uuid' => $ticketType->uuid,
                'actor_id' => $actor?->id,
            ]);

        return $ticketType->fresh();
    }

    public function disable(TicketType $ticketType, ?User $actor = null): TicketType
    {
        $ticketType->is_active = false;
        $ticketType->save();

        $this->queueSquareSync($ticketType, archive: true);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.ticket_types.disabled', [
                'ticket_type_uuid' => $ticketType->uuid,
                'actor_id' => $actor?->id,
            ]);

        return $ticketType->fresh();
    }

    public function delete(TicketType $ticketType, ?User $actor = null): void
    {
        $hasHistory = $ticketType->quantity_sold > 0
            || $ticketType->registrations()->withTrashed()->exists()
            || $ticketType->tickets()->withTrashed()->exists();

        if ($hasHistory) {
            throw new ResourceInUseException(
                'This ticket type has sales or registrations and cannot be deleted. Disable it instead.'
            );
        }

        $uuid = $ticketType->uuid;
        try {
            app(\App\Ems\Services\Square\SquareCatalogService::class)->syncTicketType($ticketType, true);
        } catch (\Throwable) {
            // Historical Square catalog objects are archived best-effort.
        }
        $ticketType->delete();

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.ticket_types.deleted', [
                'ticket_type_uuid' => $uuid,
                'actor_id' => $actor?->id,
            ]);
    }

    public function duplicate(TicketType $ticketType, ?User $actor = null): TicketType
    {
        $copy = $ticketType->replicate([
            'uuid',
            'quantity_sold',
        ]);
        $copy->name = $this->uniqueCopyName($ticketType);
        $copy->quantity_sold = 0;
        $copy->is_active = false;
        $copy->save();

        $this->queueSquareSync($copy);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.ticket_types.duplicated', [
                'source_uuid' => $ticketType->uuid,
                'ticket_type_uuid' => $copy->uuid,
                'actor_id' => $actor?->id,
            ]);

        return $copy->fresh();
    }

    /**
     * @param  list<string>  $orderedUuids
     * @return Collection<int, TicketType>
     */
    public function reorder(Event $event, array $orderedUuids, ?User $actor = null): Collection
    {
        return DB::transaction(function () use ($event, $orderedUuids, $actor): Collection {
            foreach ($orderedUuids as $index => $uuid) {
                TicketType::query()
                    ->where('event_id', $event->id)
                    ->where('uuid', $uuid)
                    ->update(['sort_order' => $index]);
            }

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.ticket_types.reordered', [
                    'event_uuid' => $event->uuid,
                    'actor_id' => $actor?->id,
                ]);

            return $this->listForEvent($event);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(TicketType $ticketType, array $data): void
    {
        $fields = [
            'name',
            'description',
            'price',
            'currency',
            'quantity',
            'sales_start_at',
            'sales_end_at',
            'is_active',
            'is_visible',
            'max_per_order',
            'sort_order',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $ticketType->{$field} = $data[$field];
            }
        }

        if ($ticketType->currency === null || $ticketType->currency === '') {
            $ticketType->currency = (string) config('ems.defaults.currency', 'CAD');
        }
    }

    private function uniqueCopyName(TicketType $ticketType): string
    {
        $base = $ticketType->name . ' (Copy)';
        $name = $base;
        $i = 2;

        while (
            TicketType::query()
                ->where('event_id', $ticketType->event_id)
                ->where('name', $name)
                ->exists()
        ) {
            $name = $base . ' ' . $i;
            $i++;
        }

        return $name;
    }

    private function queueSquareSync(TicketType $ticketType, bool $archive = false): void
    {
        try {
            \App\Ems\Jobs\SyncTicketTypeToSquareJob::dispatch($ticketType->id, $archive);
        } catch (\Throwable $e) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->warning('ems.ticket_types.square_sync_dispatch_failed', [
                    'ticket_type_uuid' => $ticketType->uuid,
                    'error' => $e->getMessage(),
                ]);
        }
    }
}
