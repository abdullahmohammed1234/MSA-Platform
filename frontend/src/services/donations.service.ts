import client from '@/services/api';

export interface DonationCheckoutPayload {
  donor_name: string;
  donor_email: string;
  amount_cents: number;
  is_anonymous?: boolean;
  dedication?: string;
}

export interface DonationItem {
  id?: number;
  uuid: string;
  donation_number: string;
  donor_name: string;
  donor_email: string;
  amount_cents: number;
  formatted_amount?: string;
  currency: string;
  status: 'pending' | 'paid' | 'failed' | 'cancelled' | 'refunded';
  is_anonymous: boolean;
  dedication?: string;
  square_checkout_id?: string;
  square_order_id?: string;
  square_payment_id?: string;
  paid_at?: string;
  refunded_at?: string;
  created_at: string;
}

export const donationsService = {
  // Public APIs
  async createCheckout(payload: DonationCheckoutPayload) {
    const res = await client.post('/donations/checkout', payload);
    return res.data;
  },

  async getDonationStatus(uuid: string) {
    const res = await client.get(`/donations/${uuid}/status`);
    return res.data;
  },

  // DMS Admin APIs
  async getDmsDashboard() {
    const res = await client.get('/donations/admin/dashboard');
    return res.data;
  },

  async getDmsDonations(params: { page?: number; per_page?: number; search?: string; status?: string }) {
    const res = await client.get('/donations/admin/donations', { params });
    return res.data;
  },

  async getDmsDonationDetail(uuid: string) {
    const res = await client.get(`/donations/admin/donations/${uuid}`);
    return res.data;
  },

  async refundDonation(uuid: string, reason: string) {
    const res = await client.post(`/donations/admin/donations/${uuid}/refund`, { reason });
    return res.data;
  },

  async getDmsDonors(params: { page?: number; per_page?: number; search?: string }) {
    const res = await client.get('/donations/admin/donors', { params });
    return res.data;
  },

  async getDmsReports(year?: number) {
    const res = await client.get('/donations/admin/reports', { params: { year } });
    return res.data;
  },

  async reconcileDms() {
    const res = await client.post('/donations/admin/reconcile');
    return res.data;
  },

  getExportCsvUrl() {
    const baseUrl = client.defaults.baseURL || '/api/v1';
    return `${baseUrl}/donations/admin/export`;
  },
};
