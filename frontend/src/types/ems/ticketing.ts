/**
 * Phase 3 ticketing & payment types.
 */

export interface TicketType {
  uuid: string;
  name: string;
  description: string | null;
  price: number;
  currency: string;
  quantity: number | null;
  quantity_sold: number;
  remaining_quantity: number | null;
  sales_start_at: string | null;
  sales_end_at: string | null;
  is_active: boolean;
  is_visible: boolean;
  is_free: boolean;
  is_sold_out: boolean;
  is_on_sale: boolean;
  max_per_order: number | null;
  sort_order: number;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface TicketTypePayload {
  name: string;
  description?: string | null;
  price: number;
  currency?: string;
  quantity?: number | null;
  sales_start_at?: string | null;
  sales_end_at?: string | null;
  is_active?: boolean;
  is_visible?: boolean;
  max_per_order?: number | null;
  sort_order?: number;
}

export interface EventPaymentSummary {
  capacity: number | null;
  remaining_capacity: number | null;
  tickets_sold: number;
  paid_orders: number;
  pending_payments: number;
  failed_payments: number;
  revenue: number;
  currency: string;
  waitlist_enabled: boolean;
  waitlist_count: number;
  ticket_types: Array<{
    uuid: string;
    name: string;
    price: number;
    currency: string;
    quantity: number | null;
    quantity_sold: number;
    remaining: number | null;
    is_active: boolean;
    is_sold_out: boolean;
  }>;
}

export interface PublicTicketType {
  uuid: string;
  name: string;
  description: string | null;
  price: number;
  currency: string;
  is_free: boolean;
  quantity: number | null;
  remaining_quantity: number | null;
  is_sold_out: boolean;
  is_on_sale: boolean;
  sales_start_at: string | null;
  sales_end_at: string | null;
  max_per_order: number | null;
  sort_order: number;
}

export interface CheckoutResult {
  checkout_url: string | null;
  requires_payment: boolean;
  order: {
    uuid: string;
    reference: string;
    status: string;
    status_label: string;
    total_amount: number;
    currency: string;
  };
  registration: import('@/types/ems/public').PublicRegistration;
  payment: {
    uuid: string;
    status: string;
    status_label: string;
    amount: number;
    currency: string;
  } | null;
}

export interface WaitlistEntry {
  uuid: string;
  position: number;
  status: string;
  status_label: string;
  quantity: number;
  attendee_name: string;
  attendee_email: string;
}
