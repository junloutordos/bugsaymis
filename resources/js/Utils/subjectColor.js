/**
 * Deterministic subject color coding for the Computer Laboratory schedule.
 *
 * The same subject/title always maps to the same color, on screen and on the
 * printed sheet, without needing a stored color column — the key string is
 * hashed to pick a fixed index into an accessible palette.
 */

const PALETTE = [
  { name: 'indigo',  bg: '#e0e7ff', border: '#818cf8', text: '#312e81', hex: '#6366f1' },
  { name: 'emerald', bg: '#d1fae5', border: '#34d399', text: '#065f46', hex: '#10b981' },
  { name: 'amber',   bg: '#fef3c7', border: '#fbbf24', text: '#92400e', hex: '#f59e0b' },
  { name: 'rose',    bg: '#ffe4e6', border: '#fb7185', text: '#9f1239', hex: '#f43f5e' },
  { name: 'sky',     bg: '#e0f2fe', border: '#38bdf8', text: '#0c4a6e', hex: '#0ea5e9' },
  { name: 'violet',  bg: '#ede9fe', border: '#a78bfa', text: '#4c1d95', hex: '#8b5cf6' },
  { name: 'teal',    bg: '#ccfbf1', border: '#2dd4bf', text: '#134e4a', hex: '#14b8a6' },
  { name: 'orange',  bg: '#ffedd5', border: '#fb923c', text: '#7c2d12', hex: '#f97316' },
  { name: 'pink',    bg: '#fce7f3', border: '#f472b6', text: '#831843', hex: '#ec4899' },
  { name: 'lime',    bg: '#ecfccb', border: '#a3e635', text: '#3f6212', hex: '#84cc16' },
  { name: 'cyan',    bg: '#cffafe', border: '#22d3ee', text: '#164e63', hex: '#06b6d4' },
  { name: 'fuchsia', bg: '#fae8ff', border: '#e879f9', text: '#701a75', hex: '#d946ef' },
]

/** Simple deterministic string hash (djb2 variant) — stable across sessions/browsers. */
function hashKey(key) {
  let hash = 5381
  for (let i = 0; i < key.length; i++) {
    hash = ((hash << 5) + hash) + key.charCodeAt(i)
    hash |= 0
  }
  return Math.abs(hash)
}

/**
 * Resolve a stable palette entry for a subject/title key.
 * Falls back to a neutral slate entry when no key is available.
 */
export function subjectColor(key) {
  if (!key) {
    return { name: 'slate', bg: '#f1f5f9', border: '#94a3b8', text: '#334155', hex: '#64748b' }
  }
  return PALETTE[hashKey(String(key).trim().toLowerCase()) % PALETTE.length]
}

/** Tailwind-safe inline style object for the on-screen calendar block. */
export function subjectColorStyle(key) {
  const color = subjectColor(key)
  return {
    backgroundColor: color.bg,
    borderColor: color.border,
    color: color.text,
  }
}
