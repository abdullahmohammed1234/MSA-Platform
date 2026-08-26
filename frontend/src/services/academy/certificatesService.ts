import client from '@/services/api';
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
    const response = await client.get('/dams/achievements');
    return response.data.achievements;
  },

  async createAchievement(data: Partial<Achievement>): Promise<Achievement> {
    const response = await client.post('/dams/achievements', data);
    return response.data.achievement;
  },

  async updateAchievement(id: number, data: Partial<Achievement>): Promise<Achievement> {
    const response = await client.put(`/dams/achievements/${id}`, data);
    return response.data.achievement;
  },

  async deleteAchievement(id: number): Promise<void> {
    await client.delete(`/dams/achievements/${id}`);
  },

  async getBadgesAdmin(): Promise<Badge[]> {
    const response = await client.get('/dams/badges');
    return response.data.badges;
  },

  async createBadge(data: Partial<Badge>): Promise<Badge> {
    const response = await client.post('/dams/badges', data);
    return response.data.badge;
  },

  async updateBadge(id: number, data: Partial<Badge>): Promise<Badge> {
    const response = await client.put(`/dams/badges/${id}`, data);
    return response.data.badge;
  },

  async deleteBadge(id: number): Promise<void> {
    await client.delete(`/dams/badges/${id}`);
  },
};

/** @deprecated Use gamificationService */
export const certificatesService = gamificationService;
