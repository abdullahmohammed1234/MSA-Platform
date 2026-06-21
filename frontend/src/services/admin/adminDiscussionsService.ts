import client from '@/services/api';

export interface ModerationThread {
  id: string;
  authorName: string;
  authorAvatar: string;
  title: string;
  preview: string;
  courseTitle: string;
  flaggedCount: number;
  flaggedReason?: string | null;
  repliesCount: number;
  postedAt: string;
  status: 'active' | 'flagged' | 'resolved';
  isPinned?: boolean;
  mentorReplies?: Array<{
    author: string;
    role: string;
    text: string;
    date: string;
  }>;
  reportId?: string;
}

export const adminDiscussionsService = {
  async getThreads(): Promise<ModerationThread[]> {
    const response = await client.get('/admin/academy/discussions/threads');
    const data = response.data;
    if (!data?.success) {
      throw new Error(data?.message || 'Failed to load discussion threads.');
    }
    return data.discussions ?? [];
  },

  async getReports(status = 'open'): Promise<Array<{ id: string; thread: ModerationThread | null }>> {
    const response = await client.get('/admin/academy/discussions/reports', { params: { status } });
    const data = response.data;
    if (!data?.success) {
      throw new Error(data?.message || 'Failed to load discussion reports.');
    }
    return data.reports ?? [];
  },

  async resolveReport(reportId: string, status: 'resolved' | 'dismissed' = 'dismissed'): Promise<void> {
    const response = await client.patch(`/admin/academy/discussions/reports/${reportId}/resolve`, { status });
    if (!response.data?.success) {
      throw new Error(response.data?.message || 'Failed to resolve report.');
    }
  },
};
