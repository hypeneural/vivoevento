import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';

export interface SearchResult {
  id: number;
  title: string;
  type: 'event' | 'media' | 'client';
  subtitle: string;
  url: string;
  image: string | null;
  uuid?: string;
  slug?: string;
}

interface SearchResponse {
  results: SearchResult[];
  total: number;
  query: string;
}

export function useGlobalSearch(query: string) {
  return useQuery<SearchResponse>({
    queryKey: ['global-search', query],
    queryFn: () => api.get<SearchResponse>('/dashboard/search', { params: { q: query } }),
    enabled: query.length >= 2,
    staleTime: 30_000,
    placeholderData: (prev) => prev,
  });
}
