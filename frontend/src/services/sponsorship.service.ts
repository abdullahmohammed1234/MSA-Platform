import api from './api';

export interface PublicOpportunity {
  id: number;
  uuid: string;
  title: string;
  slug: string;
  description: string;
  opportunity_type: string;
  target_amount_cents: number;
  event?: {
    id: number;
    name: string;
    start_at: string;
    location: string;
    banner_url?: string;
  };
  packages: Array<{
    id: number;
    uuid: string;
    name: string;
    description: string;
    price_cents: number;
    max_available?: number;
    claimed_count: number;
    benefits: Array<{
      id: number;
      title: string;
      description?: string;
      quantity: number;
    }>;
  }>;
}

export interface Organization {
  id: number;
  uuid: string;
  legal_name: string;
  display_name: string;
  relationship_type: string;
  status: string;
  industry?: string;
  website_url?: string;
  phone?: string;
  email?: string;
  contacts?: any[];
  sponsorships?: any[];
  account_owner?: { name: string; email: string };
  created_at: string;
}

export interface SponsorshipDeal {
  id: number;
  uuid: string;
  sponsorship_number: string;
  title: string;
  sponsorship_type: string;
  status: string;
  financial_status: string;
  fulfillment_status: string;
  total_committed_cents: number;
  total_paid_cents: number;
  in_kind_estimated_cents: number;
  outstanding_cents?: number;
  start_date: string;
  organization?: Organization;
  opportunity?: { title: string };
  package?: { name: string };
  agreement?: any;
  commitments?: any[];
  payments?: any[];
  deliverables?: any[];
  created_at: string;
}

export const sponsorshipService = {
  // Public
  async getPublicOpportunities() {
    const res = await api.get('/sponsorship/opportunities');
    return res.data;
  },

  async submitInquiry(data: any) {
    const res = await api.post('/sponsorship/inquire', data);
    return res.data;
  },

  // Admin Dashboard & Reports
  async getDashboardMetrics() {
    const res = await api.get('/sponsorship/admin/dashboard');
    return res.data;
  },

  // Organizations CRM
  async getOrganizations(params?: any) {
    const res = await api.get('/sponsorship/admin/organizations', { params });
    return res.data;
  },

  async createOrganization(data: any) {
    const res = await api.post('/sponsorship/admin/organizations', data);
    return res.data;
  },

  async getOrganizationDetails(uuid: string) {
    const res = await api.get(`/sponsorship/admin/organizations/${uuid}`);
    return res.data;
  },

  async updateOrganization(uuid: string, data: any) {
    const res = await api.put(`/sponsorship/admin/organizations/${uuid}`, data);
    return res.data;
  },

  async checkDuplicateOrganizations(name: string, email?: string) {
    const res = await api.get('/sponsorship/admin/organizations/check-duplicates', {
      params: { name, email },
    });
    return res.data;
  },

  async addContact(orgUuid: string, data: any) {
    const res = await api.post(`/sponsorship/admin/organizations/${orgUuid}/contacts`, data);
    return res.data;
  },

  async logCommunication(orgUuid: string, data: any) {
    const res = await api.post(`/sponsorship/admin/organizations/${orgUuid}/communications`, data);
    return res.data;
  },

  async createFollowUp(orgUuid: string, data: any) {
    const res = await api.post(`/sponsorship/admin/organizations/${orgUuid}/follow-ups`, data);
    return res.data;
  },

  // Opportunities
  async getOpportunities(params?: any) {
    const res = await api.get('/sponsorship/admin/opportunities', { params });
    return res.data;
  },

  async createOpportunity(data: any) {
    const res = await api.post('/sponsorship/admin/opportunities', data);
    return res.data;
  },

  async addPackage(opportunityUuid: string, data: any) {
    const res = await api.post(`/sponsorship/admin/opportunities/${opportunityUuid}/packages`, data);
    return res.data;
  },

  // Sponsorships
  async getSponsorships(params?: any) {
    const res = await api.get('/sponsorship/admin/sponsorships', { params });
    return res.data;
  },

  async createSponsorship(data: any) {
    const res = await api.post('/sponsorship/admin/sponsorships', data);
    return res.data;
  },

  async getSponsorshipDetail(uuid: string) {
    const res = await api.get(`/sponsorship/admin/sponsorships/${uuid}`);
    return res.data;
  },

  async updateSponsorshipStatus(uuid: string, status: string) {
    const res = await api.patch(`/sponsorship/admin/sponsorships/${uuid}/status`, { status });
    return res.data;
  },

  async executeAgreement(uuid: string, data: any) {
    const res = await api.post(`/sponsorship/admin/sponsorships/${uuid}/agreement`, data);
    return res.data;
  },

  // Payments
  async getPayments(params?: any) {
    const res = await api.get('/sponsorship/admin/payments', { params });
    return res.data;
  },

  async recordManualPayment(sponsorshipUuid: string, data: any) {
    const res = await api.post(`/sponsorship/admin/sponsorships/${sponsorshipUuid}/payments/manual`, data);
    return res.data;
  },

  async createSquareCheckout(sponsorshipUuid: string, amountCents: number) {
    const res = await api.post(`/sponsorship/admin/sponsorships/${sponsorshipUuid}/payments/square`, {
      amount_cents: amountCents,
    });
    return res.data;
  },

  // Fulfillment
  async getFulfillmentDeliverables(params?: any) {
    const res = await api.get('/sponsorship/admin/fulfillment', { params });
    return res.data;
  },

  async addDeliverable(sponsorshipUuid: string, data: any) {
    const res = await api.post(`/sponsorship/admin/sponsorships/${sponsorshipUuid}/deliverables`, data);
    return res.data;
  },

  async completeFulfillment(deliverableUuid: string, data: any) {
    const res = await api.post(`/sponsorship/admin/deliverables/${deliverableUuid}/fulfillments`, data);
    return res.data;
  },
};
