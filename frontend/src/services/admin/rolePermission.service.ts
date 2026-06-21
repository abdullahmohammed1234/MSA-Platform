import client from '@/services/api';
import type { Role, Permission, AuditLog } from '@/types/auth';

export interface PaginatedAuditLogs {
  data: AuditLog[];
  current_page: number;
  last_page: number;
  total: number;
}

export const rolePermissionService = {
  /**
   * Get all roles.
   */
  async getRoles(): Promise<{ roles: Role[] }> {
    const response = await client.get('/admin/roles');
    return response.data;
  },

  /**
   * Create a new role.
   */
  async createRole(payload: { name: string; slug: string; description?: string; permissions?: string[] }): Promise<{ role: Role }> {
    const response = await client.post('/admin/roles', payload);
    return response.data;
  },

  /**
   * Update an existing role.
   */
  async updateRole(uuid: string, payload: { name: string; slug: string; description?: string; permissions?: string[] }): Promise<{ role: Role }> {
    const response = await client.put(`/admin/roles/${uuid}`, payload);
    return response.data;
  },

  /**
   * Delete a role.
   */
  async deleteRole(uuid: string): Promise<{ message: string }> {
    const response = await client.delete(`/admin/roles/${uuid}`);
    return response.data;
  },

  /**
   * Get all permissions.
   */
  async getPermissions(): Promise<{ permissions: Permission[] }> {
    const response = await client.get('/admin/permissions');
    return response.data;
  },

  /**
   * Assign roles to a user.
   */
  async assignUserRoles(userUuid: string, roles: string[]): Promise<{ user: any }> {
    const response = await client.post(`/admin/users/${userUuid}/roles`, { roles });
    return response.data;
  },

  /**
   * Assign direct permissions to a user.
   */
  async assignUserPermissions(userUuid: string, permissions: string[]): Promise<{ user: any }> {
    const response = await client.post(`/admin/users/${userUuid}/permissions`, { permissions });
    return response.data;
  },

  /**
   * Fetch paginated audit logs.
   */
  async getAuditLogs(page = 1): Promise<{ audit_logs: PaginatedAuditLogs }> {
    const response = await client.get('/admin/audit-logs', { params: { page } });
    return response.data;
  }
};
