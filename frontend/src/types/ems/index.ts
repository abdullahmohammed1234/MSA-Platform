/**
 * Types for the MSA Event Management System.
 *
 * These mirror the payloads produced by the backend's App\Ems\Http\Resources
 * classes. Anything the API does not send is not modelled here: the frontend
 * never invents fields, and it never reimplements the lifecycle rules — the
 * server publishes both the state graph and the per-event action list.
 */

/* -------------------------------------------------------------------------
 * Envelope
 * ---------------------------------------------------------------------- */

export interface EmsPagination {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number | null;
  to: number | null;
}

export interface EmsMeta {
  pagination?: EmsPagination;
  total?: number;
  groups?: string[];
  [key: string]: unknown;
}

export interface EmsSuccessEnvelope<T> {
  success: true;
  message: string;
  data: T;
  meta: EmsMeta;
}

export interface EmsErrorEnvelope {
  success: false;
  message: string;
  errors?: Record<string, string[]>;
}

/** A list response with its pagination metadata kept alongside the rows. */
export interface EmsPaginated<T> {
  items: T[];
  pagination: EmsPagination;
}

/* -------------------------------------------------------------------------
 * Lifecycle
 * ---------------------------------------------------------------------- */

export type EventStatus =
  | 'draft'
  | 'published'
  | 'registration_open'
  | 'registration_closed'
  | 'live'
  | 'completed'
  | 'archived'
  | 'cancelled';

export type EventStatusTone = 'neutral' | 'info' | 'success' | 'warning' | 'live' | 'muted' | 'danger';

export type EventTransitionAction =
  | 'publish'
  | 'unpublish'
  | 'open_registration'
  | 'close_registration'
  | 'mark_live'
  | 'complete'
  | 'archive'
  | 'cancel';

/** A transition offered on a specific event, already resolved for the viewer. */
export interface EventAvailableTransition {
  action: EventTransitionAction;
  label: string;
  to: EventStatus;
  to_label: string;
  confirmation: string;
  irreversible: boolean;
  /** False when the state allows it but this user may not perform it. */
  permitted: boolean;
}

export interface EventStatusOption {
  value: EventStatus;
  label: string;
  tone: EventStatusTone;
}

export interface EventTransitionEdge {
  action: EventTransitionAction;
  label: string;
  from: EventStatus;
  to: EventStatus;
  permission: string;
  confirmation: string;
  irreversible: boolean;
}

/** The whole state machine, served by GET /ems/events/lifecycle. */
export interface EventLifecycleGraph {
  states: EventStatusOption[];
  transitions: EventTransitionEdge[];
}

/* -------------------------------------------------------------------------
 * Resources
 * ---------------------------------------------------------------------- */

export interface EmsUserSummary {
  id: number;
  uuid: string;
  name: string;
  email: string;
  avatar: string | null;
}

export interface EventCategory {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  description: string | null;
  color: string | null;
  is_active: boolean;
  sort_order: number;
  events_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface Event {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  short_description: string | null;
  description: string | null;
  banner_url?: string | null;

  category_id: number | null;
  category?: EventCategory | null;

  organizer_id: number | null;
  organizer_name: string | null;
  organizer?: EmsUserSummary | null;

  location: string | null;
  start_at: string | null;
  end_at: string | null;
  timezone: string | null;
  capacity: number | null;
  waitlist_enabled?: boolean;
  max_tickets_per_order?: number | null;
  max_registrations_per_attendee?: number | null;
  registration_deadline_at?: string | null;

  status: EventStatus;
  status_label: string;
  status_tone: EventStatusTone;
  is_public: boolean;
  is_publicly_visible: boolean;
  is_accepting_registrations: boolean;

  published_at: string | null;
  registration_open_at: string | null;
  registration_closed_at: string | null;
  completed_at: string | null;
  archived_at: string | null;
  cancelled_at?: string | null;
  cancellation_reason?: string | null;

  available_transitions: EventAvailableTransition[];

  created_by: number | null;
  updated_by: number | null;
  creator?: EmsUserSummary | null;
  created_at: string | null;
  updated_at: string | null;

  registrations_count?: number;
}

/* -------------------------------------------------------------------------
 * Access model
 * ---------------------------------------------------------------------- */

export interface EmsCurrentUser {
  id: number;
  uuid: string;
  name: string;
  email: string;
  avatar: string | null;
  is_active: boolean;
  roles: Array<{ slug: string; name: string }>;
  /** Narrowed to the EMS namespace by the backend resource. */
  permissions: string[];
  has_ems_access: boolean;
  created_at: string | null;
}

export interface EmsRole {
  uuid: string;
  name: string;
  slug: string;
  description: string | null;
  permissions: string[];
}

export interface EmsPermission {
  uuid: string;
  name: string;
  slug: string;
  description: string | null;
  group: string;
}

/* -------------------------------------------------------------------------
 * Dashboard
 * ---------------------------------------------------------------------- */

/** Status counters keyed by EventStatus, plus the two derived totals. */
export type EmsDashboardSummary = Record<EventStatus, number> & {
  total: number;
  upcoming: number;
};

export interface EmsActivityEntry {
  id: number;
  action: string;
  description: string | null;
  result: 'success' | 'failed' | 'denied' | string;
  subject_type: string | null;
  subject_id: number | null;
  actor?: EmsUserSummary | null;
  created_at: string | null;
}

export interface EmsQuickAction {
  key: string;
  label: string;
  route: string;
}

export interface EmsDashboard {
  summary: EmsDashboardSummary;
  upcoming_events: Event[];
  recent_activity: EmsActivityEntry[];
  quick_actions: EmsQuickAction[];
}

/* -------------------------------------------------------------------------
 * Request payloads
 * ---------------------------------------------------------------------- */

export interface EventListFilters {
  search?: string;
  status?: EventStatus | '';
  category_id?: number | null;
  organizer_id?: number | null;
  upcoming?: boolean;
  starts_after?: string;
  starts_before?: string;
  sort_by?: 'start_at' | 'name' | 'status' | 'created_at' | 'updated_at';
  sort_direction?: 'asc' | 'desc';
  per_page?: number;
  page?: number;
}

export interface EventPayload {
  name: string;
  slug?: string | null;
  short_description?: string | null;
  description?: string | null;
  banner_url?: string | null;
  category_id?: number | null;
  organizer_id?: number | null;
  organizer_name?: string | null;
  location?: string | null;
  start_at: string;
  end_at?: string | null;
  timezone?: string | null;
  capacity?: number | null;
  waitlist_enabled?: boolean;
  max_tickets_per_order?: number | null;
  max_registrations_per_attendee?: number | null;
  registration_deadline_at?: string | null;
  is_public?: boolean;
  notify_audience?: 'everyone' | 'registered' | 'ticket_holders' | 'none';
}

export interface EventCategoryPayload {
  name: string;
  slug?: string | null;
  description?: string | null;
  color?: string | null;
  is_active?: boolean;
  sort_order?: number;
}

export interface EventCategoryFilters {
  is_active?: boolean;
  search?: string;
}

export interface EventTemplate {
  id: number;
  uuid: string;
  name: string;
  description: string | null;
  category_id: number | null;
  category?: EventCategory | null;
  capacity: number | null;
  is_public: boolean;
  waitlist_enabled: boolean;
  max_tickets_per_order: number | null;
  max_registrations_per_attendee: number | null;
  registration_deadline_offset_days: number | null;
  settings: Record<string, any> | null;
  is_default: boolean;
  archived_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface EventTemplatePayload {
  name: string;
  description?: string | null;
  category_id?: number | null;
  capacity?: number | null;
  is_public?: boolean;
  waitlist_enabled?: boolean;
  max_tickets_per_order?: number | null;
  max_registrations_per_attendee?: number | null;
  registration_deadline_offset_days?: number | null;
  settings?: Record<string, any> | null;
  is_default?: boolean;
}
