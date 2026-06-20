import client from '@/services/api/client';

export type ReactionType = 'THUMBS_UP' | 'HEART' | 'CLAP' | 'INSIGHTFUL' | 'LAUGH' | 'SAD';
export type ReportReason = 'SPAM' | 'HARASSMENT' | 'INAPPROPRIATE_CONTENT' | 'MISINFORMATION' | 'OTHER';

export const discussionsService = {
  async toggleReaction(payload: {
    type: ReactionType;
    threadId?: number;
    postId?: number;
  }): Promise<{ active: boolean; type: ReactionType }> {
    const response = await client.post('/discussions/reactions', {
      type: payload.type,
      thread_id: payload.threadId,
      post_id: payload.postId,
    });
    return response.data;
  },

  async toggleBookmark(threadId: number): Promise<{ bookmarked: boolean }> {
    const response = await client.post('/discussions/bookmarks', { thread_id: threadId });
    return response.data;
  },

  async submitReport(payload: {
    reason: ReportReason;
    threadId?: number;
    postId?: number;
  }): Promise<void> {
    await client.post('/discussions/reports', {
      reason: payload.reason,
      thread_id: payload.threadId,
      post_id: payload.postId,
    });
  },
};
