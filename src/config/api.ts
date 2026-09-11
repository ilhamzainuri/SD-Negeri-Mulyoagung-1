/**
 * Dynamic API Base URL configuration
 * Supports both local development (localhost / XAMPP) and production deployment (Hostinger).
 */

export const getApiBaseUrl = (): string => {
  // 1. Prioritas Utama: Menggunakan variabel lingkungan .env jika tersedia
  if (import.meta.env.VITE_API_BASE_URL) {
    // Memastikan tidak ada trailing slash di akhir
    const envUrl = import.meta.env.VITE_API_BASE_URL.replace(/\/$/, '');
    // Jika VITE_API_BASE_URL belum menyertakan '/backend/API', tambahkan otomatis
    return envUrl.endsWith('/backend/API') ? envUrl : `${envUrl}/backend/API`;
  }

  // 2. Resolusi Dinamis Browser
  if (typeof window !== 'undefined') {
    const { hostname, port, origin } = window.location;

    // A. Local Development - Vite Server (Port 3000 / 5173) mengarah ke XAMPP lokal
    if (port === '3000' || port === '5173') {
      return `http://${hostname}/sd-negeri-mulyoagung-1/backend/API`;
    }

    // B. Local Development - Akses langsung via XAMPP (misal http://localhost/...)
    if (hostname === 'localhost' || hostname === '127.0.0.1') {
      return `${origin}/sd-negeri-mulyoagung-1/backend/API`;
    }

    // C. Production / Staging Environment (Hostinger)
    // Berlaku untuk test.uydapz.site, sdn1mulyoagung.sch.id, atau custom domain lain
    return `${origin}/backend/API`;
  }

  // Fallback default
  return 'https://test.uydapz.site/backend/API';
};

export const API_BASE_URL = getApiBaseUrl();

/**
 * Returns full asset image URL handling local backend uploads and absolute URLs
 */
export const getImageUrl = (imagePath: string | null | undefined): string => {
  if (!imagePath) return '';
  
  // Jika path gambar sudah berupa URL absolut (http/https), langsung kembalikan
  if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
    return imagePath;
  }

  // Dapatkan origin domain saja (tanpa path /backend/API)
  let siteOrigin = 'https://test.uydapz.site';
  
  if (import.meta.env.VITE_API_BASE_URL) {
    siteOrigin = import.meta.env.VITE_API_BASE_URL.replace(/\/$/, '');
  } else if (typeof window !== 'undefined') {
    const { hostname, port, origin } = window.location;
    if (port === '3000' || port === '5173') {
      siteOrigin = `http://${hostname}/sd-negeri-mulyoagung-1`;
    } else {
      siteOrigin = origin;
    }
  }

  // Bersihkan slash di awal path gambar jika ada
  const cleanPath = imagePath.replace(/^\//, '');

  // Jika path sudah mengandung 'backend/uploads/', gabungkan langsung
  if (cleanPath.startsWith('backend/uploads/')) {
    return `${siteOrigin}/${cleanPath}`;
  }

  // Jika hanya nama file (misal: 'berita1.webp'), arahkan ke folder uploads backend
  return `${siteOrigin}/backend/uploads/${cleanPath}`;
};