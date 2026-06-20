import client from '@/services/api/client';

export interface AcademyResource {
  id: string;
  title: string;
  category: string;
  size: string;
  description: string;
  lessons: string[];
  url?: string;
  linkText?: string;
}

export const academyResourcesService = {
  async getResources(): Promise<AcademyResource[]> {
    const response = await client.get('/academy/resources');
    const data = response.data;
    if (!data?.success) {
      throw new Error(data?.message || 'Failed to load resources.');
    }
    return data.resources ?? [];
  },
};
