/**
 * Types matching EMS API v1 responses and request payloads.
 */

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number | null;
  to: number | null;
}

export interface EmsEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
  meta?: {
    pagination?: PaginationMeta;
    [key: string]: any;
  };
}

export interface PublicCategory {
  uuid: string;
  name: string;
  slug: string;
  color?: string | null;
}

export interface PublicEvent {
  uuid: string;
  name: string;
  slug: string;
  short_description?: string | null;
  banner_url?: string | null;
  category?: PublicCategory | null;
  location?: string | null;
  start_at: string | null;
  end_at: string | null;
  timezone: string;
  status: string;
  status_label: string;
  status_tone?: string;
  capacity: number | null;
  remaining_capacity: number | null;
  is_full: boolean;
  is_sold_out: boolean;
  waitlist_enabled: boolean;
  is_featured: boolean;
  is_accepting_registrations: boolean;
  registration_label: string;
}

export interface TicketType {
  uuid: string;
  name: string;
  description?: string | null;
  price: number;
  currency: string;
  is_free: boolean;
  quantity?: number | null;
  remaining_quantity?: number | null;
  is_sold_out: boolean;
  is_on_sale: boolean;
  sales_start_at?: string | null;
  sales_end_at?: string | null;
}

export interface PublicEventDetail extends PublicEvent {
  description?: string | null;
  organizer?: {
    name: string;
  };
  published_at?: string | null;
  registration_open_at?: string | null;
  registration_closed_at?: string | null;
  registration_deadline_at?: string | null;
  ticket_types?: TicketType[];
}

export interface RegisterPayload {
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  student_id?: string;
  notes?: string;
  ticket_type_id?: string;
  quantity?: number;
}

export interface Ticket {
  code: string;
  uuid: string;
  status: string;
  status_label: string;
  holder_name: string;
  issued_at: string | null;
  qr_payload?: string;
  qr_image?: string | null;
  event?: {
    uuid: string;
    name: string;
    slug: string;
    location?: string | null;
    start_at: string | null;
    end_at: string | null;
    timezone: string;
    category?: PublicCategory | null;
  };
}

export interface Registration {
  reference: string;
  uuid: string;
  status: string;
  status_label: string;
  is_active: boolean;
  type: string;
  attendee_name: string;
  attendee_email: string;
  quantity: number;
  amount_due: number;
  currency: string;
  registered_at: string | null;
  confirmed_at: string | null;
  ticket_type?: {
    uuid: string;
    name: string;
  } | null;
  event?: {
    uuid: string;
    name: string;
    slug: string;
    start_at: string | null;
    end_at: string | null;
    location: string | null;
    status: string;
    status_label: string;
  } | null;
  tickets?: Ticket[];
}

export interface CalendarLinks {
  google: string;
  outlook: string;
  yahoo: string;
  ics: string;
}

export interface EventFilterParams {
  search?: string;
  category_slug?: string;
  upcoming?: boolean;
  past?: boolean;
  featured?: boolean;
  page?: number;
  per_page?: number;
  sort_by?: 'start_at' | 'name';
  sort_direction?: 'asc' | 'desc';
}
