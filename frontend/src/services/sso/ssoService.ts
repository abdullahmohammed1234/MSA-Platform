import api from '@/services/api';
import { getPmsBaseUrl, getPmsLaunchUrl } from '@/config/api';

export interface SsoTicketResponse {
  success: boolean;
  ticket: string;
  service: string;
  expires_in: number;
}

export const ssoService = {
  /**
   * Request a short-lived single-use SSO exchange ticket (SEC-11-01).
   */
  async requestTicket(service: string = 'pms'): Promise<string> {
    const res = await api.post<SsoTicketResponse>('/sso/tickets', { service });
    return res.data.ticket;
  },

  /**
   * Resolve secure PMS launch URL using short-lived fragment ticket.
   * Format: https://projects.sfumsa.ca/#sso_ticket=<ticket>
   * Falls back to plain base URL if ticket generation fails.
   */
  async getPmsSsoLaunchUrl(): Promise<string> {
    try {
      const ticket = await this.requestTicket('pms');
      return getPmsLaunchUrl(ticket);
    } catch (err) {
      console.error('Failed to issue SSO ticket, falling back to standard login:', err);
      return getPmsBaseUrl();
    }
  },
};
