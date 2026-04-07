/**
 * API Client — Camada de comunicação com o backend Laravel.
 *
 * Centraliza headers, auth token, interceptors de erro e base URL.
 * Preparado para plugar no backend Sanctum quando disponível.
 */

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api/v1';

// ─── Token Management ──────────────────────────────────────

const TOKEN_KEY = 'eventovivo_token';

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token);
}

export function removeToken(): void {
  localStorage.removeItem(TOKEN_KEY);
}

export function hasToken(): boolean {
  return !!getToken();
}

// ─── API Error ─────────────────────────────────────────────

export interface ApiValidationErrors {
  [field: string]: string[];
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  meta?: Record<string, unknown>;
  message?: string;
}

export class ApiError extends Error {
  constructor(
    public status: number,
    public body: any,
    public validationErrors?: ApiValidationErrors,
  ) {
    super(body?.message || `Request failed with status ${status}`);
    this.name = 'ApiError';
  }

  get isValidation(): boolean {
    return this.status === 422;
  }

  get isUnauthorized(): boolean {
    return this.status === 401;
  }

  get isForbidden(): boolean {
    return this.status === 403;
  }

  get isNotFound(): boolean {
    return this.status === 404;
  }

  /** Get the first error message for a field */
  fieldError(field: string): string | undefined {
    return this.validationErrors?.[field]?.[0];
  }
}

// ─── Request Builder ───────────────────────────────────────

interface RequestOptions extends Omit<RequestInit, 'body'> {
  body?: unknown;
  params?: Record<string, string | number | boolean | undefined | null>;
}

function buildUrl(path: string, params?: RequestOptions['params']): string {
  const url = new URL(path.startsWith('http') ? path : `${API_BASE_URL}${path}`, window.location.origin);

  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        url.searchParams.set(key, String(value));
      }
    });
  }

  return url.toString();
}

function buildHeaders(custom?: HeadersInit): Headers {
  const headers = new Headers(custom);

  if (!headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  headers.set('Accept', 'application/json');

  const token = getToken();
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  return headers;
}

async function handleResponse<T>(response: Response, unwrapData = true): Promise<T> {
  // Handle 204 No Content
  if (response.status === 204) {
    return undefined as T;
  }

  const contentType = response.headers.get('content-type');
  const isJson = contentType?.includes('application/json');

  if (!response.ok) {
    const body = isJson ? await response.json() : await response.text();

    // Auto logout on 401
    if (response.status === 401) {
      removeToken();
      // Dispatch event for AuthProvider to catch
      window.dispatchEvent(new CustomEvent('auth:unauthorized'));
    }

    throw new ApiError(
      response.status,
      body,
      response.status === 422 ? body?.errors : undefined,
    );
  }

  if (!isJson) {
    const text = await response.text();
    const preview = text.replace(/\s+/g, ' ').trim().slice(0, 180);

    throw new ApiError(502, {
      message: 'API returned a non-JSON response. Check VITE_API_BASE_URL and origin routing.',
      response_content_type: contentType,
      response_preview: preview,
    });
  }

  const json = await response.json();
  if (!unwrapData) {
    return json as T;
  }

  if (
    json?.data !== undefined &&
    json?.meta &&
    typeof json.meta === 'object' &&
    'page' in json.meta &&
    'last_page' in json.meta
  ) {
    return {
      data: json.data,
      meta: json.meta,
    } as T;
  }

  // Laravel wraps regular responses in { data: ... }
  return json.data !== undefined ? json.data : json;
}

// ─── Public API ────────────────────────────────────────────

export const api = {
  async get<T = any>(path: string, options?: RequestOptions): Promise<T> {
    const response = await fetch(buildUrl(path, options?.params), {
      method: 'GET',
      headers: buildHeaders(options?.headers),
      ...options,
      body: undefined,
    });
    return handleResponse<T>(response);
  },

  async getRaw<T = any>(path: string, options?: RequestOptions): Promise<T> {
    const response = await fetch(buildUrl(path, options?.params), {
      method: 'GET',
      headers: buildHeaders(options?.headers),
      ...options,
      body: undefined,
    });
    return handleResponse<T>(response, false);
  },

  async post<T = any>(path: string, options?: RequestOptions): Promise<T> {
    const response = await fetch(buildUrl(path, options?.params), {
      method: 'POST',
      headers: buildHeaders(options?.headers),
      ...options,
      body: options?.body ? JSON.stringify(options.body) : undefined,
    });
    return handleResponse<T>(response);
  },

  async patch<T = any>(path: string, options?: RequestOptions): Promise<T> {
    const response = await fetch(buildUrl(path, options?.params), {
      method: 'PATCH',
      headers: buildHeaders(options?.headers),
      ...options,
      body: options?.body ? JSON.stringify(options.body) : undefined,
    });
    return handleResponse<T>(response);
  },

  async put<T = any>(path: string, options?: RequestOptions): Promise<T> {
    const response = await fetch(buildUrl(path, options?.params), {
      method: 'PUT',
      headers: buildHeaders(options?.headers),
      ...options,
      body: options?.body ? JSON.stringify(options.body) : undefined,
    });
    return handleResponse<T>(response);
  },

  async delete<T = any>(path: string, options?: RequestOptions): Promise<T> {
    const response = await fetch(buildUrl(path, options?.params), {
      method: 'DELETE',
      headers: buildHeaders(options?.headers),
      ...options,
      body: options?.body ? JSON.stringify(options.body) : undefined,
    });
    return handleResponse<T>(response);
  },

  /** Upload files (multipart/form-data) */
  async upload<T = any>(path: string, formData: FormData): Promise<T> {
    const headers = new Headers();
    headers.set('Accept', 'application/json');
    const token = getToken();
    if (token) headers.set('Authorization', `Bearer ${token}`);

    const response = await fetch(buildUrl(path), {
      method: 'POST',
      headers,
      body: formData,
    });
    return handleResponse<T>(response);
  },
};

export default api;
