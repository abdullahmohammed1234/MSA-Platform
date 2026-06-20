import { defineStore } from 'pinia';
import { ref } from 'vue';
import { certificatesService } from '@/services/academy/certificatesService';
import type { Achievement } from '@/types/certification';

export const useAchievementsStore = defineStore('academy-achievements', () => {
  const achievements = ref<Achievement[]>([]);
  const adminAchievements = ref<Achievement[]>([]);
  const points = ref<number>(0);
  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);

  const fetchAchievements = async () => {
    loading.value = true;
    error.value = null;
    try {
      const response = await clientGetAchievements();
      achievements.value = response.achievements;
      points.value = response.points || 0;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch achievements';
    } finally {
      loading.value = false;
    }
  };

  const clientGetAchievements = async () => {
    // Return direct achievements and points
    const res = await certificatesService.getAchievements();
    const achieved = res.filter(a => a.unlocked);
    const pts = achieved.reduce((sum, item) => sum + item.points, 0);
    return { achievements: res, points: pts };
  };

  // Admin actions
  const fetchAchievementsAdmin = async () => {
    loading.value = true;
    error.value = null;
    try {
      adminAchievements.value = await certificatesService.getAchievementsAdmin();
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch admin achievements';
    } finally {
      loading.value = false;
    }
  };

  const createAchievement = async (data: Partial<Achievement>) => {
    loading.value = true;
    try {
      const created = await certificatesService.createAchievement(data);
      adminAchievements.value.push(created);
      return created;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to create achievement';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const updateAchievement = async (id: number, data: Partial<Achievement>) => {
    loading.value = true;
    try {
      const updated = await certificatesService.updateAchievement(id, data);
      const index = adminAchievements.value.findIndex(a => a.id === id);
      if (index !== -1) {
        adminAchievements.value[index] = updated;
      }
      return updated;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to update achievement';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const deleteAchievement = async (id: number) => {
    loading.value = true;
    try {
      await certificatesService.deleteAchievement(id);
      adminAchievements.value = adminAchievements.value.filter(a => a.id !== id);
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to delete achievement';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  return {
    achievements,
    adminAchievements,
    points,
    loading,
    error,
    fetchAchievements,
    fetchAchievementsAdmin,
    createAchievement,
    updateAchievement,
    deleteAchievement
  };
});
