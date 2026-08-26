import client from '@/services/api'
import type { SystemsOverview, SystemApplication, PlatformService } from '@/types/systems'

export const systemsControlPlaneService = {
  async getOverview(refresh = false): Promise<SystemsOverview> {
    const response = await client.get('/admin/systems', {
      params: refresh ? { refresh: 1 } : undefined,
    })
    return response.data
  },

  async getApplication(id: string, refresh = false): Promise<SystemApplication> {
    const response = await client.get(`/admin/systems/registry/${encodeURIComponent(id)}`, {
      params: refresh ? { refresh: 1 } : undefined,
    })
    return response.data.system
  },

  async getPlatformService(id: string, refresh = false): Promise<PlatformService> {
    const response = await client.get(`/admin/systems/services/${encodeURIComponent(id)}`, {
      params: refresh ? { refresh: 1 } : undefined,
    })
    return response.data.service
  },
}

export default systemsControlPlaneService
