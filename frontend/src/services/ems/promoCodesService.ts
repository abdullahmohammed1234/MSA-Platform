import { emsHttp } from './emsClient';

export interface PromoCode {
  id: number;
  uuid: string;
  code: string;
  description: string | null;
  discount_type: 'percentage' | 'fixed' | 'free';
  discount_value: number;
  usage_limit: number | null;
  usage_per_attendee: number;
  start_date: string | null;
  end_date: string | null;
  minimum_purchase: number | null;
  is_active: boolean;
  archived_at: string | null;
  created_at: string | null;
  updated_at: string | null;

  // Stats appended by controller index/show
  times_used?: number;
  remaining_uses?: number | null;
  revenue_impact?: number;

  // Relational loads
  events?: Array<{ uuid: string; name: string }>;
  ticket_types?: Array<{ uuid: string; name: string }>;
}

export interface PromoCodePayload {
  code: string;
  description?: string | null;
  discount_type: 'percentage' | 'fixed' | 'free';
  discount_value: number;
  usage_limit?: number | null;
  usage_per_attendee: number;
  start_date?: string | null;
  end_date?: string | null;
  minimum_purchase?: number | null;
  is_active?: boolean;
  eligible_events?: string[];
  eligible_ticket_types?: string[];
}

export const promoCodesService = {
  /** GET /ems/promo-codes */
  list(): Promise<PromoCode[]> {
    return emsHttp.get<PromoCode[]>('/promo-codes');
  },

  /** GET /ems/promo-codes/{uuid} */
  show(uuid: string): Promise<PromoCode> {
    return emsHttp.get<PromoCode>(`/promo-codes/${uuid}`);
  },

  /** POST /ems/promo-codes */
  create(payload: PromoCodePayload): Promise<PromoCode> {
    return emsHttp.post<PromoCode>('/promo-codes', payload);
  },

  /** PUT /ems/promo-codes/{uuid} */
  update(uuid: string, payload: Partial<PromoCodePayload>): Promise<PromoCode> {
    return emsHttp.put<PromoCode>(`/promo-codes/${uuid}`, payload);
  },

  /** DELETE /ems/promo-codes/{uuid} */
  async remove(uuid: string): Promise<void> {
    await emsHttp.delete(`/promo-codes/${uuid}`);
  },
};
