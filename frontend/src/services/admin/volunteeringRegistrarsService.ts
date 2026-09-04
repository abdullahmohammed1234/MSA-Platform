import client from '@/services/api';
import type { EmsPaginated, EmsMeta } from '@/types/ems';
import type {
  VolunteerRegistration,
  VolunteerRegistrationFilters,
  UpdateVolunteerRegistrationPayload,
} from '@/types/ems/volunteers';

export const volunteeringRegistrarsService = {
  /**
   * Fetch paginated list of volunteer registrations with search and status filters.
   */
  async listRegistrations(
    filters: VolunteerRegistrationFilters = {}
  ): Promise<EmsPaginated<VolunteerRegistration>> {
    const params: Record<string, any> = {};

    if (filters.search) params.search = filters.search;
    if (filters.status && filters.status !== 'all') params.status = filters.status;
    if (filters.sort_by) params.sort_by = filters.sort_by;
    if (filters.sort_order) params.sort_order = filters.sort_order;
    if (filters.page) params.page = filters.page;
    if (filters.per_page) params.per_page = filters.per_page;

    const response = await client.get('/admin/volunteering-registrars', { params });
    const resData = response.data;
    
    const items = resData.data ?? [];
    const meta: EmsMeta = resData.meta ?? {};
    const pagination = meta.pagination ?? {
      current_page: 1,
      per_page: items.length,
      total: items.length,
      last_page: 1,
      from: items.length ? 1 : null,
      to: items.length || null,
    };

    return {
      items,
      pagination,
    };
  },

  /**
   * Fetch a single volunteer registration by UUID.
   */
  async getRegistration(uuid: string): Promise<VolunteerRegistration> {
    const response = await client.get(`/admin/volunteering-registrars/${uuid}`);
    return response.data.data;
  },

  /**
   * Update status, admin notes, or admin assignment on a volunteer registration.
   */
  async updateRegistration(
    uuid: string,
    payload: UpdateVolunteerRegistrationPayload
  ): Promise<VolunteerRegistration> {
    const response = await client.put(`/admin/volunteering-registrars/${uuid}`, payload);
    return response.data.data;
  },

  /**
   * Soft delete / archive a volunteer registration.
   */
  async deleteRegistration(uuid: string): Promise<{ success: boolean; message: string }> {
    const response = await client.delete(`/admin/volunteering-registrars/${uuid}`);
    return response.data;
  },
};

export default volunteeringRegistrarsService;
