import type { PublicRegistration } from '@/types/ems/public';
import type { CheckoutResult } from '@/types/ems/ticketing';

const STORAGE_KEY = 'ems.pending_checkouts.v1';

export interface StoredPendingCheckout {
  slug: string;
  event_name: string;
  email: string;
  first_name: string;
  last_name: string;
  phone: string;
  quantity: number;
  ticket_type_id: string;
  ticket_name: string;
  promo_code: string | null;
  order_uuid: string;
  checkout_url: string | null;
  amount: number;
  currency: string;
  checkout_version?: number;
  saved_at: string;
}

function readAll(): StoredPendingCheckout[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function writeAll(items: StoredPendingCheckout[]): void {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
  } catch {
    // Private browsing or quota — in-memory restore still works for this visit.
  }
}

export const pendingCheckoutStorage = {
  list(): StoredPendingCheckout[] {
    return readAll();
  },

  get(slug: string): StoredPendingCheckout | null {
    return readAll().find((item) => item.slug === slug) ?? null;
  },

  save(item: StoredPendingCheckout): void {
    const next = readAll().filter((existing) => existing.slug !== item.slug);
    next.unshift(item);
    writeAll(next.slice(0, 20));
  },

  fromCheckoutResult(
    slug: string,
    eventName: string,
    form: {
      first_name: string;
      last_name: string;
      email: string;
      phone: string;
      quantity: number;
    },
    ticketTypeId: string,
    ticketName: string,
    promoCode: string | null,
    result: CheckoutResult
  ): StoredPendingCheckout {
    return {
      slug,
      event_name: eventName,
      email: form.email,
      first_name: form.first_name,
      last_name: form.last_name,
      phone: form.phone,
      quantity: form.quantity,
      ticket_type_id: ticketTypeId,
      ticket_name: ticketName,
      promo_code: promoCode,
      order_uuid: result.order.uuid,
      checkout_url: result.checkout_url ?? result.payment?.checkout_url ?? null,
      amount: result.payment?.amount ?? result.order.total_amount,
      currency: result.payment?.currency ?? result.order.currency,
      checkout_version: result.payment?.checkout_version,
      saved_at: new Date().toISOString(),
    };
  },

  remove(slug: string): void {
    writeAll(readAll().filter((item) => item.slug !== slug));
  },

  removeByOrderUuid(orderUuid: string): void {
    writeAll(readAll().filter((item) => item.order_uuid !== orderUuid));
  },

  toCheckoutResult(item: StoredPendingCheckout): CheckoutResult {
    return {
      checkout_url: item.checkout_url,
      requires_payment: true,
      order: {
        uuid: item.order_uuid,
        reference: '',
        status: 'pending',
        status_label: 'Pending',
        total_amount: item.amount,
        currency: item.currency,
      },
      registration: {
        reference: '',
        uuid: '',
        status: 'awaiting_payment',
        status_label: 'Pending Payment',
        type: 'paid',
        attendee_name: `${item.first_name} ${item.last_name}`.trim(),
        attendee_email: item.email,
        quantity: item.quantity,
        amount_due: item.amount,
        currency: item.currency,
        registered_at: item.saved_at,
        confirmed_at: null,
        ticket_type: item.ticket_type_id
          ? { uuid: item.ticket_type_id, name: item.ticket_name }
          : null,
        event: {
          uuid: '',
          name: item.event_name,
          slug: item.slug,
          start_at: null,
          location: null,
        },
        tickets: [],
      } satisfies PublicRegistration,
      payment: {
        uuid: '',
        status: 'pending',
        status_label: 'Pending',
        amount: item.amount,
        currency: item.currency,
        checkout_url: item.checkout_url,
        checkout_version: item.checkout_version,
      },
    };
  },
};

export default pendingCheckoutStorage;
