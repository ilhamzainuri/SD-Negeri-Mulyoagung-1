/**
 * Dynamic API Base URL configuration
 * Supports both local development (localhost / XAMPP) and production deployment (Hostinger).
 */

// 1. Mendapatkan Origin Domain Utama (misal: https://test.uydapz.site atau http://localhost/sd-negeri-mulyoagung-1)
export const getSiteOrigin = (): string => {
  if (import.meta.env.VITE_API_BASE_URL) {
    return import.meta.env.VITE_API_BASE_URL.replace(/\/$/, '').replace(/\/backend\/API$/, '');
  }

  if (typeof window !== 'undefined') {
    const { hostname, port, origin } = window.location;

    // Local Development (Vite Server - Port 3000 / 5173)
    if (port === '3000' || port === '5173') {
      return `http://${hostname}/sd-negeri-mulyoagung-1`;
    }

    // Local Development via XAMPP direct
    if (hostname === 'localhost' || hostname === '127.0.0.1') {
      return `${origin}/sd-negeri-mulyoagung-1`;
    }

    // Production / Staging (Hostinger)
    return origin;
  }

  return 'https://test.uydapz.site';
};

export const SITE_ORIGIN = getSiteOrigin();

// 2. Base URL API Konsisten (Satu-satunya sumber ke folder /backend/API)
export const getApiBaseUrl = (): string => `${SITE_ORIGIN}/backend/API`;

export const API_BASE_URL = getApiBaseUrl();

/**
 * Mendapatkan token autentikasi CMS dari localStorage jika user sedang login
 */
export const getCmsAuthToken = (): string => {
  if (typeof window !== 'undefined') {
    try {
      const stored = localStorage.getItem('cms_user');
      if (stored) {
        const user = JSON.parse(stored);
        return user.token || '';
      }
    } catch {
      // ignore
    }
  }
  return '';
};

/**
 * Global fetch wrapper that bypasses browser and intermediate HTTP caching
 * and attaches CMS auth token if available
 */
export const apiFetch = async (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
  const headers = new Headers(init?.headers);
  headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
  headers.set('Pragma', 'no-cache');

  const token = getCmsAuthToken();
  if (token) {
    headers.set('X-CMS-Token', token);
    headers.set('Authorization', `Bearer ${token}`);
  }

  let url = input;
  if (typeof input === 'string') {
    const separator = input.includes('?') ? '&' : '?';
    url = `${input}${separator}_t=${Date.now()}`;
  }

  return fetch(url, {
    ...init,
    cache: 'no-store',
    headers,
  });
};

/**
 * Returns full asset image URL handling local backend uploads and absolute URLs
 */
export const getImageUrl = (imagePath: string | null | undefined): string => {
  if (!imagePath) return '';

  // Jika path gambar sudah berupa URL absolut (http/https), langsung kembalikan
  if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
    return imagePath;
  }

  const cleanPath = imagePath.replace(/^\//, '');

  // Jika path sudah mengandung 'backend/uploads/', gabungkan dengan SITE_ORIGIN
  if (cleanPath.startsWith('backend/uploads/')) {
    return `${SITE_ORIGIN}/${cleanPath}`;
  }

  // Fallback: Arahkan ke folder uploads backend
  return `${SITE_ORIGIN}/backend/uploads/${cleanPath}`;
};