// The app lives under a sub-folder of the website (vite `base`).
const base = import.meta.env.BASE_URL.replace(/\/+$/, '');

/** Website root that hosts this app (apps/<name>/ sits one level below it). */
export const siteRoot = `${base.replace(/\/apps\/[^/]+$/, '')}/`;
