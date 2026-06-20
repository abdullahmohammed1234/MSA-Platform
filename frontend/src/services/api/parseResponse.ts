import type { AxiosResponse } from 'axios';

export class ApiError extends Error {
  status?: number;

  constructor(message: string, status?: number) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
  }
}

export function parseApiResponse<T>(response: AxiosResponse, key: string): T {
  const data = response.data;

  if (!data?.success) {
    throw new ApiError(data?.message || `API request failed for ${key}`, response.status);
  }

  if (!(key in data)) {
    throw new ApiError(`Missing "${key}" in API response`, response.status);
  }

  return data[key] as T;
}
