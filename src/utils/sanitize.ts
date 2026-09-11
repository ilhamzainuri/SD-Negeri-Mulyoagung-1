/**
 * Lightweight native HTML sanitizer for XSS protection
 */
export const sanitizeHtml = (dirty: string | null | undefined): string => {
  if (!dirty || typeof dirty !== 'string') return '';

  return dirty
    // Remove dangerous tags and their content
    .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
    .replace(/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi, '')
    .replace(/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/gi, '')
    .replace(/<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/gi, '')
    .replace(/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi, '')
    // Remove javascript: URLs
    .replace(/href\s*=\s*["']?\s*javascript:[^"'>\s]*/gi, 'href="#"')
    .replace(/src\s*=\s*["']?\s*javascript:[^"'>\s]*/gi, 'src=""')
    // Remove inline event handlers (onerror, onclick, onload, etc.)
    .replace(/\son\w+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, '');
};
