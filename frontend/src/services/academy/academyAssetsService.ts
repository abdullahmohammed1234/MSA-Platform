import client from '@/services/api';

/**
 * Academy/DAMS asset uploads — not CMS media.
 * Requires manage_courses (or admin bypass).
 */
export const academyAssetsService = {
  async uploadImage(file: File): Promise<{ success: boolean; message: string; url: string; owner?: string }> {
    const formData = new FormData();
    formData.append('file', file);
    const response = await client.post('/dams/assets/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },
};

export default academyAssetsService;
