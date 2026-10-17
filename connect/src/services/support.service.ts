import apiClient from './api.ts';
import type {
  SupportCategory,
  SupportInquiry,
  CreateInquiryPayload,
  SendReplyPayload,
  UpdateInquiryStatusPayload,
  AssignInquiryPayload,
  PaginatedInquiries,
  StaffSupportStats,
  ResourcesStatusResponse,
} from '../types/support.ts';

export const supportService = {
  /**
   * Fetch available support categories and guidance.
   */
  async getCategories(): Promise<SupportCategory[]> {
    const response = await apiClient.get<{ categories: SupportCategory[] }>('/support/categories');
    return response.data.categories;
  },

  /**
   * Fetch paginated list of the current member's inquiries.
   */
  async getInquiries(page = 1): Promise<PaginatedInquiries> {
    const response = await apiClient.get<PaginatedInquiries>('/support/inquiries', {
      params: { page },
    });
    return response.data;
  },

  /**
   * Retrieve a single inquiry thread by reference code (e.g. INQ-202610-A1B2).
   */
  async getInquiry(referenceCode: string): Promise<SupportInquiry> {
    const response = await apiClient.get<{ inquiry: SupportInquiry }>(
      `/support/inquiries/${encodeURIComponent(referenceCode)}`
    );
    return response.data.inquiry;
  },

  /**
   * Submit a new member support inquiry.
   */
  async createInquiry(payload: CreateInquiryPayload): Promise<SupportInquiry> {
    const response = await apiClient.post<{ message: string; inquiry: SupportInquiry }>(
      '/support/inquiries',
      payload
    );
    return response.data.inquiry;
  },

  /**
   * Send a member reply to an active inquiry thread.
   */
  async sendReply(referenceCode: string, payload: SendReplyPayload): Promise<SupportInquiry> {
    const response = await apiClient.post<{ message: string; inquiry?: SupportInquiry; data?: any }>(
      `/support/inquiries/${encodeURIComponent(referenceCode)}/messages`,
      payload
    );
    if (response.data.inquiry) {
      return response.data.inquiry;
    }
    return this.getInquiry(referenceCode);
  },

  /**
   * Member voluntarily closes an inquiry.
   */
  async closeInquiry(referenceCode: string): Promise<SupportInquiry> {
    const response = await apiClient.post<{ message: string; inquiry?: SupportInquiry }>(
      `/support/inquiries/${encodeURIComponent(referenceCode)}/close`
    );
    if (response.data.inquiry) {
      return response.data.inquiry;
    }
    return this.getInquiry(referenceCode);
  },

  /**
   * Check whether community resources are officially published and enabled.
   */
  async getResourcesStatus(): Promise<ResourcesStatusResponse> {
    const response = await apiClient.get<ResourcesStatusResponse>('/support/resources/status');
    return response.data;
  },

  /* -------------------------------------------------------------
   * Authorized Staff Triage APIs
   * ------------------------------------------------------------- */

  /**
   * Staff triage inbox list with filters.
   */
  async getStaffInquiries(params: {
    status?: string;
    category?: string;
    search?: string;
    page?: number;
  } = {}): Promise<PaginatedInquiries> {
    const response = await apiClient.get<PaginatedInquiries>('/staff/support/inquiries', {
      params,
    });
    return response.data;
  },

  /**
   * Retrieve staff detail view of an inquiry.
   */
  async getStaffInquiry(referenceCode: string): Promise<SupportInquiry> {
    const response = await apiClient.get<{ inquiry: SupportInquiry }>(
      `/staff/support/inquiries/${encodeURIComponent(referenceCode)}`
    );
    return response.data.inquiry;
  },

  /**
   * Staff sends a response in an inquiry thread.
   */
  async sendStaffReply(referenceCode: string, payload: SendReplyPayload): Promise<SupportInquiry> {
    const response = await apiClient.post<{ message: string; inquiry?: SupportInquiry; data?: any }>(
      `/staff/support/inquiries/${encodeURIComponent(referenceCode)}/messages`,
      payload
    );
    if (response.data.inquiry) {
      return response.data.inquiry;
    }
    return this.getStaffInquiry(referenceCode);
  },

  /**
   * Staff updates status (open, in_progress, resolved, closed).
   */
  async updateStaffInquiryStatus(referenceCode: string, status: UpdateInquiryStatusPayload['status']): Promise<SupportInquiry> {
    const response = await apiClient.patch<{ message: string; inquiry?: SupportInquiry }>(
      `/staff/support/inquiries/${encodeURIComponent(referenceCode)}/status`,
      { status }
    );
    if (response.data.inquiry) {
      return response.data.inquiry;
    }
    return this.getStaffInquiry(referenceCode);
  },

  /**
   * Staff assigns an inquiry to a team member.
   */
  async assignStaffInquiry(referenceCode: string, assignedTo: number | null): Promise<SupportInquiry> {
    const response = await apiClient.patch<{ message: string; inquiry?: SupportInquiry }>(
      `/staff/support/inquiries/${encodeURIComponent(referenceCode)}/assign`,
      { assigned_to: assignedTo } as AssignInquiryPayload
    );
    if (response.data.inquiry) {
      return response.data.inquiry;
    }
    return this.getStaffInquiry(referenceCode);
  },

  /**
   * Fetch staff inquiry triage statistics.
   */
  async getStaffStats(): Promise<StaffSupportStats> {
    const response = await apiClient.get<StaffSupportStats>('/staff/support/stats');
    return response.data;
  },
};

export default supportService;
