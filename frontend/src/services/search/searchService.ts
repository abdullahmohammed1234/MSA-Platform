import api from '@/services/api';

export interface SearchResultItem {
  id: string;
  content_type: 'announcement' | 'event' | 'program' | 'resource' | 'volunteer' | 'store';
  type_label: string;
  title: string;
  excerpt: string;
  destination: string;
  date?: string | null;
  category?: string | null;
  thumbnail?: string | null;
}

export interface SearchTypeCounts {
  all: number;
  announcement: number;
  event: number;
  program: number;
  resource: number;
  volunteer: number;
  store: number;
}

export interface SearchPagination {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
}

export interface SearchResponse {
  query: string;
  filters: {
    content_type: string;
    sort_by: string;
  };
  pagination: SearchPagination;
  type_counts: SearchTypeCounts;
  items: SearchResultItem[];
  message?: string;
}

export interface SearchQueryParams {
  q: string;
  type?: string;
  sort_by?: 'relevance' | 'date';
  page?: number;
  per_page?: number;
}

class SearchService {
  /**
   * Execute unified platform search against /api/v1/search
   */
  public async search(params: SearchQueryParams): Promise<SearchResponse> {
    const queryParams: Record<string, string | number> = {
      q: params.q,
    };

    if (params.type && params.type !== 'all') {
      queryParams.type = params.type;
    }
    if (params.sort_by) {
      queryParams.sort_by = params.sort_by;
    }
    if (params.page) {
      queryParams.page = params.page;
    }
    if (params.per_page) {
      queryParams.per_page = params.per_page;
    }

    const response = await api.get<SearchResponse>('/search', {
      params: queryParams,
    });

    return response.data;
  }
}

export const searchService = new SearchService();
export default searchService;
