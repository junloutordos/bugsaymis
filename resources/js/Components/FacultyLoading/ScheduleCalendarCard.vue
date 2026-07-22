<template>
  <div ref="rootEl"
    class="schedule-calendar-card bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden"
    :class="isExpanded ? 'fixed inset-3 z-50 flex flex-col print:static print:inset-auto print:z-auto print:flex-none print:shadow-none print:ring-0' : ''">

    <!-- Card header -->
    <div class="px-4 py-3 bg-gradient-to-r from-indigo-50 to-slate-50 border-b border-slate-100 flex items-center justify-between shrink-0 gap-2">
      <div class="flex items-center gap-2 min-w-0">
        <span v-if="titleBadge" class="text-xs font-bold text-white bg-indigo-500 px-2.5 py-0.5 rounded-full shrink-0">
          {{ titleBadge }}
        </span>
        <div class="min-w-0">
          <h3 class="text-sm font-semibold text-slate-800 truncate">{{ title }}</h3>
          <p v-if="subtitle" class="text-xs text-slate-500 truncate">{{ subtitle }}</p>
        </div>
        <span v-if="meta" class="text-xs text-slate-400 shrink-0">{{ meta }}</span>
      </div>
      <div class="flex items-center gap-1 shrink-0 print:hidden">
        <slot name="header-actions" />
        <AppIconButton label="Print" size="sm" @click="printCard">
          <PrinterIcon class="h-4 w-4" />
        </AppIconButton>
        <AppIconButton :label="isExpanded ? 'Collapse' : 'Expand'" size="sm" @click="toggleExpand">
          <ArrowsPointingInIcon v-if="isExpanded" class="h-4 w-4" />
          <ArrowsPointingOutIcon v-else class="h-4 w-4" />
        </AppIconButton>
      </div>
    </div>

    <slot name="header-extra" />

    <!-- Calendar grid -->
    <div class="overflow-x-auto print:overflow-visible" :class="isExpanded ? 'flex-1' : ''">
      <div style="min-width: 760px" class="print:min-w-0">

        <!-- Day column headers -->
        <div class="flex border-b border-slate-100">
          <div class="shrink-0 border-r border-slate-100" :style="{ width: GUTTER + 'px' }" />
          <div v-for="day in WEEKDAYS" :key="day"
            class="flex-1 text-center py-2 border-l border-slate-100 first:border-l-0 group/daycol relative">
            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
              {{ day.slice(0, 3) }}
            </span>
            <span v-if="dayConfigs[day]" class="block text-xs text-slate-400 leading-tight">
              {{ fmtConfigTime(dayConfigs[day].start) }}–{{ fmtConfigTime(dayConfigs[day].end) }}
            </span>
            <button v-if="blockedEditable" type="button"
              :aria-label="`Set White Space for ${day}`"
              title="Set White Space for this day"
              class="mt-1 inline-flex min-h-7 items-center gap-1 rounded-md border border-violet-200 bg-violet-50 px-2 py-1 text-[10px] font-semibold text-violet-700 hover:border-violet-300 hover:bg-violet-100 focus:outline-none focus:ring-2 focus:ring-violet-400 focus:ring-offset-1 print:hidden"
              @click.stop="$emit('add-white-space', day, $event)">
              <PlusIcon class="h-3 w-3" />
              <span>White Space</span>
            </button>
          </div>
        </div>

        <!-- Time axis + columns -->
        <div class="flex" :style="{ height: CAL_H + 'px' }">

          <!-- Time gutter -->
          <div class="shrink-0 relative border-r border-slate-100" :style="{ width: GUTTER + 'px' }">
            <!-- First label sits below its gridline so it doesn't straddle the day-header border -->
            <div v-for="h in HOURS" :key="h"
              :style="{ top: hourTop(h) + 'px' }"
              :class="['absolute right-2 select-none', h === HOURS[0] ? 'translate-y-0.5' : '-translate-y-2.5']">
              <span class="text-xs text-slate-400 font-medium">
                {{ h === 12 ? '12PM' : h < 12 ? h + 'AM' : (h - 12) + 'PM' }}
              </span>
            </div>
          </div>

          <!-- Grid body: gridlines + day columns -->
          <div class="flex-1 relative flex">

            <!-- Horizontal hour lines (drawn over all columns) -->
            <div v-for="h in HOURS" :key="'hl-' + h"
              :style="{ top: hourTop(h) + 'px' }"
              class="absolute inset-x-0 border-t border-slate-100 pointer-events-none z-0" />

            <!-- Half-hour dashed lines (skip the one past the canvas bottom) -->
            <div v-for="h in HOURS.filter(h => h * 60 + 30 < CAL_END)" :key="'hl30-' + h"
              :style="{ top: (hourTop(h) + SCALE * 30) + 'px' }"
              class="absolute inset-x-0 border-t border-dashed border-slate-50 pointer-events-none z-0" />

            <!-- Day columns -->
            <div v-for="day in WEEKDAYS" :key="day"
              data-daycol
              :class="['flex-1 relative border-l border-slate-100 overflow-hidden',
                editable && canQuickCreate ? 'cursor-crosshair' : '']"
              @mousedown="$emit('column-mousedown', day, $event)"
              @dragover.prevent="$emit('column-dragover', day, $event)"
              @drop.prevent="$emit('column-drop', day, $event)">

              <!-- Click/drag-to-create ghost (Google Calendar-style) -->
              <div v-if="createDraft && createDraft.day === day"
                :style="createGhostStyle()"
                class="absolute inset-x-0.5 rounded-md border-2 border-indigo-400 bg-indigo-100/80 z-20 pointer-events-none flex items-start justify-center px-1 overflow-hidden">
                <span class="text-xs font-semibold text-indigo-700 mt-0.5 select-none tabular-nums">
                  {{ fmtTime(minToTime(createDraft.startMin)) }} – {{ fmtTime(minToTime(createDraft.endMin)) }}
                </span>
              </div>

              <!-- Drag-and-drop preview -->
              <div v-if="dropPreview && dropPreview.day === day"
                :style="dropPreviewStyle()"
                :class="['absolute rounded border-2 z-30 pointer-events-none flex items-center justify-center px-1 text-center',
                  dropPreview.hasConflict ? 'bg-red-100/85 border-red-400' : 'bg-emerald-100/85 border-emerald-400']">
                <span :class="['text-xs font-semibold truncate', dropPreview.hasConflict ? 'text-red-700' : 'text-emerald-700']">
                  {{ dropPreview.hasConflict ? (dropPreview.message ?? 'Conflict') : 'Drop here' }}
                </span>
              </div>

              <!-- Blocked period overlays -->
              <div v-for="bp in (dayConfigs[day]?.blocked ?? [])" :key="`${bp.label}-${bp.start}`"
                :style="blockedStyle(bp)"
                :class="['absolute inset-x-0 z-[1] flex items-center justify-center group',
                  isBandDraggable(bp) ? 'pointer-events-auto cursor-grab active:cursor-grabbing hover:ring-2 hover:ring-indigo-300 hover:ring-inset'
                    : isBandClickable(bp) ? 'pointer-events-auto cursor-pointer hover:ring-2 hover:ring-teal-300 hover:ring-inset'
                    : 'pointer-events-none']"
                @mousedown="onBandMouseDown(day, bp, $event)"
                @click.stop="isBandClickable(bp) && $emit('blocked-click', day, bp, $event)">
                <div :class="['absolute inset-0', bp.type === 'WHITE_SPACE' ? 'bg-violet-50/80 border border-violet-200' : 'bg-slate-100/70']" />
                <span :class="['relative w-full font-medium px-1 text-center leading-tight select-none', bp.type === 'WHITE_SPACE' ? 'text-violet-600' : 'text-slate-400']">
                  <span :class="['block truncate', blockedDurationMin(bp) >= 40 ? 'text-xs' : 'text-[10px]']">
                    {{ bp.label }}
                  </span>
                  <span v-if="bp.adviser_name" class="block truncate text-[10px] font-semibold text-slate-500">
                    Adviser: {{ bp.adviser_name }}
                  </span>
                  <span :class="['block tabular-nums opacity-80', blockedDurationMin(bp) >= 40 ? 'text-[10px]' : 'text-[9px]']">
                    {{ fmtTimeRange(bp.start, bp.end) }}
                  </span>
                </span>
                <template v-if="isBandDraggable(bp)">
                  <div class="absolute inset-x-0 top-0 h-2 cursor-ns-resize opacity-0 group-hover:opacity-100 bg-indigo-400/50"
                    @mousedown="onBandMouseDown(day, bp, $event, 'top')" />
                  <div class="absolute inset-x-0 bottom-0 h-2 cursor-ns-resize opacity-0 group-hover:opacity-100 bg-indigo-400/50"
                    @mousedown="onBandMouseDown(day, bp, $event, 'bottom')" />
                </template>
              </div>

              <!-- Blocked-band drag preview -->
              <div v-if="blockedDragPreview && blockedDragPreview.day === day"
                :style="blockedStyle(blockedDragPreview)"
                class="absolute inset-x-0.5 rounded-md border-2 border-indigo-400 bg-indigo-100/85 z-30 pointer-events-none flex items-center justify-center px-1 overflow-hidden">
                <span class="text-xs font-semibold text-indigo-700 truncate select-none tabular-nums">
                  {{ fmtTimeRange(blockedDragPreview.start, blockedDragPreview.end) }}
                </span>
              </div>

              <!-- Elective window band — students attend cross-section electives -->
              <div v-for="el in (dayConfigs[day]?.electives ?? [])" :key="`elec-${el.start}`"
                :style="blockedStyle(el)"
                class="absolute inset-x-0 pointer-events-none z-[2] flex items-center justify-center">
                <div class="absolute inset-0 rounded-sm border border-amber-200 bg-amber-50/80" />
                <span class="relative w-full text-amber-700 font-semibold px-1 text-center leading-tight select-none">
                  <span :class="['block truncate', blockedDurationMin(el) >= 40 ? 'text-xs' : 'text-[10px]']">
                    {{ el.label || 'Electives' }}
                  </span>
                  <span :class="['block tabular-nums opacity-80', blockedDurationMin(el) >= 40 ? 'text-[10px]' : 'text-[9px]']">
                    {{ fmtTimeRange(el.start, el.end) }}
                  </span>
                </span>
              </div>

              <!-- Science Core window band — G11/G12 leveled science (Biology/Chemistry/
                   Physics), a grade-wide synchronized block distinct from generic electives -->
              <div v-for="sc in (dayConfigs[day]?.scienceCore ?? [])" :key="`sci-${sc.start}`"
                :style="blockedStyle(sc)"
                class="absolute inset-x-0 pointer-events-none z-[2] flex items-center justify-center">
                <div class="absolute inset-0 rounded-sm border border-violet-200 bg-violet-50/80" />
                <span class="relative w-full text-violet-700 font-semibold px-1 text-center leading-tight select-none">
                  <span :class="['block truncate', blockedDurationMin(sc) >= 40 ? 'text-xs' : 'text-[10px]']">
                    {{ sc.label || 'Science Core' }}
                  </span>
                  <span :class="['block tabular-nums opacity-80', blockedDurationMin(sc) >= 40 ? 'text-[10px]' : 'text-[9px]']">
                    {{ fmtTimeRange(sc.start, sc.end) }}
                  </span>
                </span>
              </div>

              <!-- No-class afternoon overlay (Wed & Fri end at 12:00) -->
              <div v-if="dayConfigs[day] && timeToMin(dayConfigs[day].end) <= 12 * 60"
                :style="{ position: 'absolute', top: ((12 * 60 - CAL_START) * SCALE) + 'px', bottom: 0, left: 0, right: 0 }"
                class="pointer-events-none z-[1]">
                <div class="absolute inset-0 bg-slate-50/80 border-t border-slate-200/50" />
                <span class="relative block text-center text-xs text-slate-300 mt-2 select-none font-medium">
                  No Classes
                </span>
              </div>

              <!-- Schedule event blocks -->
              <div v-for="s in (eventsByDay[day] ?? [])" :key="s.id"
                data-evt
                :style="[eventStyle(s, day), eventColorStyle(s)]"
                :draggable="editable && isDraggable(s)"
                :class="['absolute rounded border z-10 overflow-hidden transition-all hover:shadow-md hover:z-20 hover:scale-[1.01]',
                  s.entry_type === 'non_teaching' ? 'border-dashed' : '',
                  s.session_type === 'ilp' ? 'border-dotted' : '',
                  editable && isDraggable(s) ? 'cursor-grab active:cursor-grabbing' : (s.can_edit ? 'cursor-pointer' : 'cursor-default'),
                  s.id === dimEventId ? 'opacity-30' : '']"
                @dragstart="$emit('event-dragstart', s, $event)"
                @dragend="$emit('event-dragend')"
                @click="$emit('event-click', s)">
                <div class="px-1.5 py-0.5 h-full flex flex-col gap-px overflow-hidden">
                  <div :class="['font-bold leading-tight truncate', eventFontSizeClass(s)]">
                    {{ s.entry_type === 'non_teaching' ? s.title : (s.subject?.name ?? s.subject?.code) }}{{ s.session_type === 'ilp' ? ' (ILP)' : '' }}
                  </div>
                  <div v-if="eventDisplayMode(s) !== 'compact' || s.session_type === 'ilp'" :class="['leading-tight truncate opacity-75', eventFontSizeClass(s)]">
                    {{ s.secondary_label }}
                  </div>
                  <div v-if="eventDisplayMode(s) === 'full' || s.session_type === 'ilp'" :class="['leading-tight opacity-55 tabular-nums', eventFontSizeClass(s)]">
                    {{ fmtTime(s.start_time) }}–{{ fmtTime(s.end_time) }}
                  </div>
                </div>
                <!-- Status indicator bar -->
                <div v-if="s.status === 'tentative'"
                  class="absolute top-0 right-0 bottom-0 w-0.5 bg-amber-400" />
                <LockClosedIcon v-if="s.is_locked"
                  class="absolute top-0.5 right-0.5 h-3 w-3 text-slate-400" title="Locked — drag disabled" />
                <button v-if="editable && s.can_edit && s.status !== 'cancelled'"
                  type="button"
                  class="absolute top-0.5 right-0.5 h-4 w-4 rounded-full bg-white/90 text-slate-400 hover:bg-rose-100 hover:text-rose-600 flex items-center justify-center print:hidden"
                  title="Remove from schedule"
                  @mousedown.stop
                  @click.stop="$emit('event-remove', s)">
                  <XMarkIcon class="h-3 w-3" />
                </button>
                <div v-if="s.status === 'cancelled'"
                  class="absolute inset-0 bg-white/60 flex items-center justify-center">
                  <span class="text-xs text-slate-400 font-medium">Cancelled</span>
                </div>
              </div>

            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Legend -->
    <div v-if="legend.length || typeLegend.length" class="px-4 py-2.5 border-t border-slate-100 flex flex-wrap gap-1.5 shrink-0">
      <div v-for="sub in legend" :key="sub.id"
        :style="scheduleColorStyle(sub)"
        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium border">
        {{ sub.code }}
      </div>
      <div v-for="t in typeLegend" :key="t.id"
        :style="t.style"
        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium border">
        {{ t.code }}
      </div>
    </div>

  </div>

  <!-- Backdrop when expanded -->
  <div v-if="isExpanded" class="fixed inset-0 bg-slate-900/40 z-40 print:hidden" @click="toggleExpand" />
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, computed } from 'vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import {
  ArrowsPointingInIcon, ArrowsPointingOutIcon, LockClosedIcon, PlusIcon, PrinterIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  title:          { type: String, required: true },
  subtitle:       { type: String, default: null },
  titleBadge:     { type: [String, Number], default: null },
  meta:           { type: String, default: '' },
  /** { Monday: [scheduleRow, ...], ... } — each row may carry a pre-resolved `secondary_label`. */
  eventsByDay:    { type: Object, default: () => ({}) },
  /** { Monday: { start, end, blocked: [{label,start,end}] }, ... } */
  dayConfigs:     { type: Object, default: () => ({}) },
  editable:       { type: Boolean, default: false },
  /** Side-by-side lanes for concurrent events instead of full-width — for
   *  overview modes (Year Level / Subject) where overlap is expected. */
  packLanes:      { type: Boolean, default: false },
  legend:         { type: Array, default: () => [] },
  dropPreview:    { type: Object, default: null },
  createDraft:    { type: Object, default: null },
  dimEventId:     { type: [Number, String], default: null },
  canQuickCreate: { type: Boolean, default: false },
  isDraggable:    { type: Function, default: () => false },
  printUrl:       { type: String, default: null },
  /** True if the calendar owner (permission + single-grade resolution) may
   *  edit this card's bell-schedule blocked bands. Individual bands still
   *  only become interactive when they carry a `write` descriptor. */
  blockedEditable:    { type: Boolean, default: false },
  /** Live position while a blocked band is being dragged: { day, start, end }. */
  blockedDragPreview: { type: Object, default: null },
})

const emit = defineEmits([
  'column-mousedown', 'column-dragover', 'column-drop', 'event-dragstart', 'event-dragend', 'event-click', 'event-remove',
  'blocked-mousedown', 'blocked-click', 'add-white-space',
])

/** Move/resize-draggable: a literal timetable row or a single-window setting.
 *  A section-scoped Lunch band (bp.sectionEditable) opts out of the
 *  grade-wide drag — dragging it here would silently move every other
 *  section's lunch too, which defeats the point of a per-section override. */
function isBandDraggable(bp) {
  return props.blockedEditable && !bp.sectionEditable && (bp.write?.kind === 'timetable' || bp.write?.kind === 'setting')
}

/** Click-to-edit: composite bands (e.g. ACTIVITY) that can't be reduced to one
 *  drag, plus a By-Section Wednesday Lunch band (its own section-only popover).
 *  White Space is always clickable when editable — it has no literal timetable
 *  row to drag, and (unlike Lunch/Recess) may be showing a grade-wide or
 *  campus-wide value rather than a section override, so it isn't always
 *  tagged sectionEditable. */
function isBandClickable(bp) {
  return props.blockedEditable && (bp.write?.kind === 'activity' || !!bp.sectionEditable || bp.type === 'WHITE_SPACE')
}

/** Stops the mousedown from also triggering the day column's own
 *  quick-create-ghost handler before deciding whether to start a band drag. */
function onBandMouseDown(day, bp, e, edge = 'move') {
  e.stopPropagation()
  if (isBandDraggable(bp)) emit('blocked-mousedown', day, bp, edge, e)
}

// ── Calendar constants (mirrors the values the parent used to own) ───────────

const WEEKDAYS  = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']
const CAL_START = 7 * 60   // 7:00 AM in minutes
const CAL_END   = 17 * 60  // 5:00 PM in minutes — bell schedules run to 17:00 (Consultation/ALP)
const SCALE     = 1.5      // px per minute
const GUTTER    = 44       // width of the time-axis gutter in px
const CAL_H     = (CAL_END - CAL_START) * SCALE

const HOURS = Array.from({ length: 11 }, (_, i) => i + 7)

const PALETTE = [
  { bg: '#dbeafe', border: '#93c5fd', color: '#1e40af' },
  { bg: '#ede9fe', border: '#c4b5fd', color: '#5b21b6' },
  { bg: '#d1fae5', border: '#6ee7b7', color: '#065f46' },
  { bg: '#fef3c7', border: '#fcd34d', color: '#92400e' },
  { bg: '#fee2e2', border: '#fca5a5', color: '#991b1b' },
  { bg: '#cffafe', border: '#67e8f9', color: '#0e7490' },
  { bg: '#fce7f3', border: '#f9a8d4', color: '#9d174d' },
  { bg: '#ecfdf5', border: '#34d399', color: '#064e3b' },
  { bg: '#fff7ed', border: '#fdba74', color: '#9a3412' },
  { bg: '#f0f9ff', border: '#7dd3fc', color: '#075985' },
]

function timeToMin(t) {
  if (!t) return 0
  const parts = t.split(':')
  return parseInt(parts[0]) * 60 + parseInt(parts[1])
}

function minToTime(min) {
  const h = Math.floor(min / 60)
  const m = min % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

function hourTop(h) {
  return (h * 60 - CAL_START) * SCALE
}

function fmtTime(t) {
  if (!t) return '—'
  const [h, m] = t.split(':')
  const hour = parseInt(h)
  return `${hour % 12 || 12}:${m} ${hour >= 12 ? 'PM' : 'AM'}`
}

function fmtConfigTime(t) {
  if (!t) return ''
  const [h, m] = t.split(':')
  const hour = parseInt(h)
  return `${hour % 12 || 12}:${m}${hour >= 12 ? 'PM' : 'AM'}`
}

/** "9:40–10:00 AM" — the meridiem is repeated only when it differs. */
function fmtTimeRange(start, end) {
  if (!start || !end) return ''
  const short = (t) => {
    const [h, m] = t.split(':')
    return `${parseInt(h) % 12 || 12}:${m}`
  }
  const mer = (t) => (parseInt(t) >= 12 ? 'PM' : 'AM')
  return mer(start) === mer(end)
    ? `${short(start)}–${short(end)} ${mer(end)}`
    : `${short(start)} ${mer(start)}–${short(end)} ${mer(end)}`
}

function blockedDurationMin(bp) {
  return timeToMin(bp.end) - timeToMin(bp.start)
}

/** Greedy interval-packing so concurrent events in a day get side-by-side
 *  lanes instead of stacking. Returns Map(scheduleId -> {lane, totalLanes}). */
function packOverlaps(events) {
  const items = events
    .map(s => ({ id: s.id, start: timeToMin(s.start_time), end: timeToMin(s.end_time) }))
    .sort((a, b) => a.start - b.start || a.end - b.end)

  const result = new Map()
  let lanesEnd = []
  let cluster = []
  let clusterMaxLane = 0
  let clusterEnd = -Infinity

  const flush = () => {
    for (const id of cluster) result.get(id).totalLanes = clusterMaxLane + 1
    cluster = []
    clusterMaxLane = 0
    lanesEnd = []
  }

  for (const item of items) {
    if (item.start >= clusterEnd) {
      flush()
      clusterEnd = -Infinity
    }
    let lane = 0
    while (lane < lanesEnd.length && lanesEnd[lane] > item.start) lane++
    lanesEnd[lane] = item.end
    result.set(item.id, { lane, totalLanes: 1 })
    cluster.push(item.id)
    clusterMaxLane = Math.max(clusterMaxLane, lane)
    clusterEnd = Math.max(clusterEnd, item.end)
  }
  flush()
  return result
}

/** { day: Map(scheduleId -> {lane, totalLanes}) } — only built when packLanes. */
const lanePacking = computed(() => {
  const map = {}
  if (!props.packLanes) return map
  for (const [day, events] of Object.entries(props.eventsByDay)) {
    map[day] = packOverlaps(events)
  }
  return map
})

function eventDurationMin(s) {
  return timeToMin(s.end_time) - timeToMin(s.start_time)
}

function eventDisplayMode(s) {
  const d = eventDurationMin(s)
  if (d >= 40) return 'full'
  if (d >= 25) return 'compact'
  return 'minimal'
}

function eventFontSizeClass(s) {
  const mode = eventDisplayMode(s)
  if (mode === 'full') return 'text-xs'
  if (mode === 'compact') return 'text-[10px]'
  return 'text-[9px]'
}

function eventStyle(s, day) {
  const sm = Math.max(timeToMin(s.start_time), CAL_START)
  const em = Math.min(timeToMin(s.end_time), CAL_END)
  const style = {
    position: 'absolute',
    top:    ((sm - CAL_START) * SCALE) + 'px',
    height: Math.max((em - sm) * SCALE, 24) + 'px',
  }

  if (props.packLanes) {
    const pack  = lanePacking.value[day]?.get(s.id)
    const lane  = pack?.lane ?? 0
    const total = pack?.totalLanes ?? 1
    const pct   = 100 / total
    style.left  = `calc(${lane * pct}% + 1px)`
    style.width = `calc(${pct}% - 2px)`
  } else {
    style.left  = '2px'
    style.right = '2px'
  }

  return style
}

function blockedStyle(bp) {
  const sm = Math.max(timeToMin(bp.start), CAL_START)
  const em = Math.min(timeToMin(bp.end), CAL_END)
  return {
    position: 'absolute',
    top:    ((sm - CAL_START) * SCALE) + 'px',
    height: Math.max((em - sm) * SCALE, 4) + 'px',
    left: 0,
    right: 0,
  }
}

function subjectColorStyle(subjectId) {
  const p = PALETTE[(subjectId ?? 0) % PALETTE.length]
  return { backgroundColor: p.bg, borderColor: p.border, color: p.color }
}

const ELECTIVE_STYLE = { backgroundColor: '#fef3c7', borderColor: '#f59e0b', color: '#92400e' }
const SCIENCE_CORE_STYLE = { backgroundColor: '#ede9fe', borderColor: '#8b5cf6', color: '#5b21b6' }

/** True for elective/science-core sessions, whether flagged on the subject
 *  itself or placed on a synthetic ELEC- / SCI- prefixed section. Accepts
 *  either a schedule row ({ subject: {...}, is_elective_section, ... }) or a
 *  bare subject object (legend entries — { id, code, is_elective, ... }). */
function isElectiveEvent(s) {
  return !!(s.subject?.is_elective || s.is_elective || s.is_elective_section)
}
function isScienceCoreEvent(s) {
  return !!(s.subject?.is_science_core || s.is_science_core || s.is_science_core_section)
}

/** Shared by event blocks and legend chips so both agree on color — electives
 *  and Science Core get a fixed amber/violet fill (matching the window-band
 *  colors used on homeroom section cards) instead of the per-subject palette. */
function scheduleColorStyle(s) {
  if (isElectiveEvent(s)) return ELECTIVE_STYLE
  if (isScienceCoreEvent(s)) return SCIENCE_CORE_STYLE
  return subjectColorStyle(s.subject?.id ?? s.id)
}

function eventColorStyle(s) {
  if (s.entry_type === 'non_teaching') {
    return { backgroundColor: '#f1f5f9', borderColor: '#94a3b8', color: '#334155' }
  }
  return scheduleColorStyle(s)
}

/** Fixed "Elective"/"Science Core" legend keys, shown whenever the card has
 *  at least one such session — needed on Faculty/My Schedule cards, which
 *  have no section divider pills to explain the color. */
const typeLegend = computed(() => {
  const rows = Object.values(props.eventsByDay).flat()
  const out = []
  if (rows.some(isElectiveEvent)) out.push({ id: 'legend-elective', code: 'Elective', style: ELECTIVE_STYLE })
  if (rows.some(isScienceCoreEvent)) out.push({ id: 'legend-science-core', code: 'Science Core', style: SCIENCE_CORE_STYLE })
  return out
})

function dropPreviewStyle() {
  if (!props.dropPreview) return {}
  const sm = Math.max(props.dropPreview.startMin, CAL_START)
  const em = Math.min(props.dropPreview.endMin, CAL_END)
  return {
    position: 'absolute',
    top:    ((sm - CAL_START) * SCALE) + 'px',
    height: Math.max((em - sm) * SCALE, 16) + 'px',
    left:   '2px',
    right:  '2px',
  }
}

function createGhostStyle() {
  if (!props.createDraft) return {}
  return {
    top:    ((props.createDraft.startMin - CAL_START) * SCALE) + 'px',
    height: (Math.max(props.createDraft.endMin - props.createDraft.startMin, 15) * SCALE) + 'px',
  }
}

// ── Full-screen expand ────────────────────────────────────────────────────────

const isExpanded = ref(false)

function toggleExpand() {
  isExpanded.value = !isExpanded.value
}

function onKeydown(e) {
  if (e.key === 'Escape' && isExpanded.value) toggleExpand()
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

// ── Print (isolates just this card, regardless of expand state) ──────────────

const rootEl = ref(null)

function printCard() {
  if (props.printUrl) {
    window.open(props.printUrl, '_blank', 'noopener')
    return
  }

  document.body.classList.add('print-single-card')
  rootEl.value?.classList.add('schedule-print-target')
  requestAnimationFrame(() => window.print())
}

function handleAfterPrint() {
  document.body.classList.remove('print-single-card')
  rootEl.value?.classList.remove('schedule-print-target')
}

onMounted(() => window.addEventListener('afterprint', handleAfterPrint))
onBeforeUnmount(() => window.removeEventListener('afterprint', handleAfterPrint))
</script>

<style>
/* Global (unscoped) on purpose: printing one card needs to hide the whole
 * app shell (sidebar/topbar/other cards), which lives outside this component. */
@media print {
  body.print-single-card * {
    visibility: hidden;
  }
  body.print-single-card .schedule-print-target,
  body.print-single-card .schedule-print-target * {
    visibility: visible;
  }
  body.print-single-card .schedule-print-target {
    position: fixed;
    inset: 0;
    z-index: 9999;
    box-shadow: none;
    border-radius: 0;
  }
  @page {
    size: landscape;
    margin: 0.4in;
  }
}
</style>
