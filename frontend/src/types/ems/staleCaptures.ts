export type StaleCaptureResolutionStatus =
  | 'unresolved'
  | 'refunded'
  | 'partially_refunded'
  | 'resolved_no_refund';

export interface StaleCapture {
  payment_uuid: string;
  payment_status: string;
  payment_status_label: string;
  order_uuid: string | null;
  order_reference: string | null;
  registration_uuid: string | null;
  registration_status: string | null;
  registration_status_label: string | null;
  attendee_name: string | null;
  attendee_email: string | null;
  event_uuid: string | null;
  event_name: string | null;
  event_missing: boolean;
  checkout_amount: number;
  currency: string;
  square_payment_id: string;
  square_order_id: string | null;
  reported_at: string | null;
  source: string | null;
  webhook_event_id: string | null;
  buyer_cancelled_at: string | null;
  ticket_count: number;
  resolution_status: StaleCaptureResolutionStatus;
  resolved_at: string | null;
  resolved_by_user_id: number | null;
  resolved_by_name: string | null;
  resolution_reason: string | null;
  square_refund_uuid: string | null;
  amount_refunded: number | null;
  remaining_refundable_amount: number | null;
}

export interface StaleCaptureListParams {
  resolution?: StaleCaptureResolutionStatus | 'all';
  event_uuid?: string;
  source?: string;
  search?: string;
  reported_from?: string;
  reported_to?: string;
}

export interface StaleCaptureRefundResult {
  refund: {
    uuid: string;
    status: string;
    status_label: string;
    amount: number;
    currency: string;
    provider_refund_id: string | null;
    reason: string;
  };
  stale_capture: StaleCapture;
}
