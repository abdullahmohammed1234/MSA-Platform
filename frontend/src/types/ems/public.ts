/**
 * Public EMS resource types (Phase 2).
 * Mirror App\Ems\Http\Resources\Public\* — no administrative fields.
 */

import type { EventStatus, EventStatusTone } from '@/types/ems';

export interface PublicCategory {
  uuid: string;
  name: string;
  slug: string;
  description: string | null;
  color: string | null;
  sort_order: number;
  events_count?: number;
}

export interface PublicEventCategoryRef {
  uuid: string;
  name: string;
  slug: string;
  color: string | null;
}

export interface PublicEvent {
  uuid: string;
  name: string;
  slug: string;
  short_description: string | null;
  banner_url: string | null;
  category: PublicEventCategoryRef | null;
  location: string | null;
  start_at: string | null;
  end_at: string | null;
  timezone: string | null;
  status: EventStatus;
  status_label: string;
  status_tone: EventStatusTone;
  capacity: number | null;
  remaining_capacity: number | null;
  is_full: boolean;
  is_sold_out?: boolean;
  waitlist_enabled?: boolean;
  is_accepting_registrations: boolean;
  registration_label: string;
}

export interface PublicEventDetail extends PublicEvent {
  description: string | null;
  organizer: { name: string } | null;
  published_at: string | null;
  registration_open_at: string | null;
  registration_closed_at: string | null;
  registration_deadline_at?: string | null;
  max_tickets_per_order?: number | null;
  max_registrations_per_attendee?: number | null;
  payments_enabled?: boolean;
  ticket_types?: import('@/types/ems/ticketing').PublicTicketType[];
}

export interface PublicCalendarEvent {
  uuid: string;
  name: string;
  slug: string;
  start_at: string | null;
  end_at: string | null;
  timezone: string | null;
  status: EventStatus;
  status_label: string;
  location: string | null;
  category: PublicEventCategoryRef | null;
}

export interface PublicTicket {
  code: string;
  uuid: string;
  status: string;
  status_label: string;
  holder_name: string | null;
  issued_at: string | null;
  qr_payload: string | null;
  qr_image?: string | null;
  event: {
    uuid: string;
    name: string;
    slug: string;
    location: string | null;
    start_at: string | null;
    end_at: string | null;
    timezone: string | null;
    category: { name: string; slug: string; color: string | null } | null;
  } | null;
  registration: {
    reference: string;
    status: string;
    status_label: string;
    registered_at: string | null;
  } | null;
}

export interface PublicRegistration {
  reference: string;
  uuid: string;
  status: string;
  status_label: string;
  type: string;
  attendee_name: string;
  attendee_email: string;
  quantity: number;
  amount_due?: number;
  currency?: string;
  registered_at: string | null;
  confirmed_at: string | null;
  ticket_type?: { uuid: string; name: string } | null;
  pending_checkout?: {
    order_uuid: string | null;
    checkout_url: string | null;
    amount: number;
    currency: string;
    checkout_version?: number | null;
    checkout_expires_at?: string | null;
  } | null;
  event: {
    uuid: string;
    name: string;
    slug: string;
    start_at: string | null;
    end_at?: string | null;
    location: string | null;
    status?: EventStatus;
    status_label?: string;
  } | null;
  tickets: PublicTicket[];
}

export interface PublicEventFilters {
  search?: string;
  category_id?: number | null;
  category_slug?: string;
  upcoming?: boolean;
  past?: boolean;
  registration_open?: boolean;
  registration_closed?: boolean;
  status?: EventStatus | '';
  sort_by?: 'start_at' | 'name';
  sort_direction?: 'asc' | 'desc';
  per_page?: number;
  page?: number;
}

export interface RegisterForEventPayload {
  first_name: string;
  last_name: string;
  email: string;
  phone?: string | null;
  student_id?: string | null;
  notes?: string | null;
  quantity?: number;
  ticket_type_id?: string | null;
  promo_code?: string | null;
  order_uuid?: string | null;
}

export interface CalendarFilters {
  starts_after?: string;
  starts_before?: string;
  category_slug?: string;
  upcoming?: boolean;
  past?: boolean;
  search?: string;
}
