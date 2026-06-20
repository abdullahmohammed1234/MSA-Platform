import { defineStore } from 'pinia';
import { ref } from 'vue';
import { certificatesService } from '@/services/academy/certificatesService';
import type { Badge } from '@/types/certification';

export const useBadgesStore = defineStore('academy-badges', () => {
  const badges = ref<Badge[]>([]);
  const adminBadges = ref<Badge[]>([]);
  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);

  const fetchBadges = async () => {
    loading.value = true;
    error.value = null;
    try {
      badges.value = await certificatesService.getBadges();
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch badges';
    } finally {
      loading.value = false;
    }
  };

  // Admin actions
  const fetchBadgesAdmin = async () => {
    loading.value = true;
    error.value = null;
    try {
      adminBadges.value = await certificatesService.getBadgesAdmin();
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch admin badges';
    } finally {
      loading.value = false;
    }
  };

  const createBadge = async (data: Partial<Badge>) => {
    loading.value = true;
    try {
      const created = await certificatesService.createBadge(data);
      adminBadges.value.push(created);
      return created;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to create badge';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const updateBadge = async (id: number, data: Partial<Badge>) => {
    loading.value = true;
    try {
      const updated = await certificatesService.updateBadge(id, data);
      const index = adminBadges.value.findIndex(b => b.id === id);
      if (index !== -1) {
        adminBadges.value[index] = updated;
      }
      return updated;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to update badge';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const deleteBadge = async (id: number) => {
    loading.value = true;
    try {
      await certificatesService.deleteBadge(id);
      adminBadges.value = adminBadges.value.filter(b => b.id !== id);
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to delete badge';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  return {
    badges,
    adminBadges,
    loading,
    error,
    fetchBadges,
    fetchBadgesAdmin,
    createBadge,
    updateBadge,
    deleteBadge
  };
});
