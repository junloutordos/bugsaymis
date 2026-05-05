// In production (ECS), VITE_STORAGE_BASE_URL is baked in at Docker build time.
// In local dev it is unset, so images are served from the local /storage symlink.
const STORAGE_BASE = import.meta.env.VITE_STORAGE_BASE_URL ?? '/storage'

/**
 * Convert a stored file path to a full URL.
 * Already-absolute URLs (http/https) are returned as-is.
 */
export function storageUrl(path) {
    if (!path) return null
    if (path.startsWith('http')) return path
    return `${STORAGE_BASE}/${path}`
}
