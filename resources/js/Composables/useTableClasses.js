/**
 * Canonical table class tokens for Atlas.
 *
 * Import these in any page that still uses inline table HTML so that
 * a single change here propagates everywhere. New pages should use
 * AppTable.vue (which embeds these automatically).
 *
 * Usage:
 *   import { TH, TD, TR } from '@/Composables/useTableClasses'
 *   <th :class="TH">Column</th>
 *   <tr :class="TR">...</tr>
 *   <td :class="TD">Value</td>
 */

// ── Header cells ──────────────────────────────────────────────────────────────
/** Standard left-aligned header */
export const TH = 'px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap'
/** Centre-aligned header (numeric / status columns) */
export const TH_C = 'px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap'
/** Right-aligned header (actions column) */
export const TH_END = 'px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider'

// ── Body cells ────────────────────────────────────────────────────────────────
/** Standard data cell */
export const TD = 'px-4 py-3 text-sm text-slate-700'
/** Muted / secondary data (dates, metadata) */
export const TD_MUTED = 'px-4 py-3 text-xs text-slate-500'
/** Monospaced — for IDs, codes, control numbers */
export const TD_MONO = 'px-4 py-3 font-mono text-xs text-indigo-700'
/** Right-aligned cell (actions) */
export const TD_END = 'px-4 py-3 text-right'

// ── Row ───────────────────────────────────────────────────────────────────────
/** Standard interactive row */
export const TR = 'hover:bg-indigo-50/40 transition-colors'
/** Clickable row — add cursor-pointer */
export const TR_CLICK = 'hover:bg-indigo-50/40 transition-colors cursor-pointer'

// ── Card wrapper (outer shell) ────────────────────────────────────────────────
/** The standard card that wraps every table */
export const TABLE_CARD = 'bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden'
