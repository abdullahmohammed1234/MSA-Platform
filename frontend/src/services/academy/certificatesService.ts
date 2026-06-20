import client from '@/services/api/client';
import type { Achievement, Badge, Milestone } from '@/types/certification';

/** Gamification API client (achievements, badges, milestones). */
export const gamificationService = {
  async getAchievements(): Promise<Achievement[]> {
    const response = await client.get('/academy/achievements');
    return response.data.achievements;
  },

  async getBadges(): Promise<Badge[]> {
    const response = await client.get('/academy/badges');
    return response.data.badges;
  },

  async getMilestones(): Promise<Milestone[]> {
    const response = await client.get('/academy/milestones');
    return response.data.milestones;
  },

  async getAchievementsAdmin(): Promise<Achievement[]> {
    const response = await client.get('/admin/academy/achievements');
    return response.data.achievements;
  },

  async createAchievement(data: Partial<Achievement>): Promise<Achievement> {
    const response = await client.post('/admin/academy/achievements', data);
    return response.data.achievement;
  },

  async updateAchievement(id: number, data: Partial<Achievement>): Promise<Achievement> {
    const response = await client.put(`/admin/academy/achievements/${id}`, data);
    return response.data.achievement;
  },

  async deleteAchievement(id: number): Promise<void> {
    await client.delete(`/admin/academy/achievements/${id}`);
  },

  async getBadgesAdmin(): Promise<Badge[]> {
    const response = await client.get('/admin/academy/badges');
    return response.data.badges;
  },

  async createBadge(data: Partial<Badge>): Promise<Badge> {
    const response = await client.post('/admin/academy/badges', data);
    return response.data.badge;
  },

  async updateBadge(id: number, data: Partial<Badge>): Promise<Badge> {
    const response = await client.put(`/admin/academy/badges/${id}`, data);
    return response.data.badge;
  },

  async deleteBadge(id: number): Promise<void> {
    await client.delete(`/admin/academy/badges/${id}`);
  },
};

/** @deprecated Use gamificationService */
export const certificatesService = gamificationService;
