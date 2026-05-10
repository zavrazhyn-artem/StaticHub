const cdnBase = (import.meta.env.VITE_CDN_URL || '').replace(/\/+$/, '');

export const cdn = (path) => `${cdnBase}/${String(path).replace(/^\/+/, '')}`;
