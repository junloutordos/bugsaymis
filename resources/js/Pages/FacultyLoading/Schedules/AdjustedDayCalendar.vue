<template>
  <div class="space-y-4">
    <div v-for="grade in gradesWithEntries" :key="grade.grade_level"
      class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-sm ring-1 ring-slate-200/70">
      <div class="border-b border-slate-100 bg-gradient-to-r from-indigo-50 to-slate-50 px-4 py-2.5">
        <h3 class="text-sm font-semibold text-slate-800">Grade {{ grade.grade_level }}</h3>
      </div>

      <div class="overflow-x-auto">
        <!-- Section header row -->
        <div class="flex border-b border-slate-100">
          <div class="sticky left-0 z-10 shrink-0 border-r border-slate-100 bg-white" :style="{ width: `${GUTTER}px` }" />
          <div v-for="section in grade.sections" :key="section.id"
            class="flex w-56 shrink-0 items-center justify-center gap-1 border-l border-slate-100 py-2 text-center first:border-l-0">
            <span class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500">{{ section.name }}</span>
            <button
              v-if="missingBandTypes(section).length"
              type="button"
              class="shrink-0 rounded-full bg-slate-100 px-1.5 text-xs leading-4 text-slate-500 hover:bg-indigo-100 hover:text-indigo-700"
              title="Add a band"
              @click="openAddBand(section)"
            >+</button>
          </div>
        </div>

        <!-- Time axis + section columns -->
        <div class="flex" :style="{ height: `${totalMinutes * PX_PER_MINUTE}px` }">
          <div class="sticky left-0 z-10 relative shrink-0 border-r border-slate-100 bg-white" :style="{ width: `${GUTTER}px` }">
            <span v-for="minute in hourMarks" :key="minute"
              class="absolute right-2 -translate-y-1/2 select-none text-[11px] font-medium text-slate-400"
              :style="{ top: `${hourTopPx(minute)}px` }">{{ hourLabel(minute) }}</span>
          </div>

          <div class="relative flex">
            <div v-for="minute in hourMarks" :key="`hl-${minute}`"
              class="pointer-events-none absolute inset-x-0 z-0 border-t border-slate-100" :style="{ top: `${hourTopPx(minute)}px` }" />
            <div v-for="minute in halfHourMarks" :key="`hl30-${minute}`"
              class="pointer-events-none absolute inset-x-0 z-0 border-t border-dashed border-slate-100" :style="{ top: `${hourTopPx(minute)}px` }" />

            <div v-for="section in grade.sections" :key="section.id"
              class="relative w-56 shrink-0 border-l border-slate-100 first:border-l-0"
              :class="conflictSectionId === section.id ? 'bg-rose-50/60 ring-2 ring-inset ring-rose-300' : ''"
              @dragover.prevent="onDragOver($event, section)"
              @dragleave="conflictSectionId = null"
              @drop.prevent="onDrop($event, section)"
            >
              <div
                v-for="band in timelineBands(section)"
                :key="`${band.type}-${band.start}`"
                :draggable="isDraggableBand(band)"
                class="absolute inset-x-0.5 z-[1] overflow-hidden rounded px-1.5 py-0.5 leading-tight transition-shadow"
                :class="bandClass(band)"
                :style="bandStyle(band)"
                :title="`${band.label} — ${band.start}–${band.end}`"
                @dragstart="onBandDragStart($event, band, section)"
                @click="isDraggableBand(band) && openBandOverride(section, band)"
              >
                <div v-if="isDraggableBand(band)" class="absolute inset-x-0 top-0 h-1.5 cursor-ns-resize"
                  @mousedown.stop.prevent="startResize($event, band, section, 'start')"
                  @dragstart.stop.prevent></div>
                <template v-if="isCompactBand(band)">
                  <span class="block truncate text-[9px] font-medium">{{ band.label }} · {{ band.start }}–{{ band.end }}</span>
                </template>
                <template v-else>
                  <span class="block truncate text-[10px] font-semibold">{{ band.label }}</span>
                  <span class="block truncate text-[9px] tabular-nums opacity-80">{{ band.start }}–{{ band.end }}</span>
                </template>
                <div v-if="isDraggableBand(band)" class="absolute inset-x-0 bottom-0 h-1.5 cursor-ns-resize"
                  @mousedown.stop.prevent="startResize($event, band, section, 'end')"
                  @dragstart.stop.prevent></div>
              </div>
              <div
                v-for="entry in section.entries"
                :key="entry.id"
                :draggable="true"
                class="absolute inset-x-1 z-[2] cursor-grab overflow-hidden rounded-md border px-1.5 py-1 shadow-sm transition-all hover:z-20 hover:scale-[1.01] hover:shadow-md active:cursor-grabbing"
                :class="entryClass(entry)"
                :style="[entryStyle(entry), entryColorStyle(entry)]"
                :data-entry-id="entry.id"
                :title="`${entry.subject?.name ?? entry.title ?? '—'} — ${entry.start_time}–${entry.end_time}${entry.faculty?.name ? ' — ' + entry.faculty.name : ''}`"
                @dragstart="onDragStart($event, entry, section)"
                @click="openOverride(entry)"
              >
                <span v-if="entry.manually_adjusted" class="absolute right-1 top-0.5 text-[10px] font-bold text-indigo-700" title="Manually adjusted before publishing">*</span>

                <template v-if="entryDisplayMode(entry) === 'minimal'">
                  <div class="flex items-center gap-1 leading-tight">
                    <span class="min-w-0 truncate text-[10px] font-semibold">{{ entry.subject?.name ?? entry.title ?? '—' }}</span>
                    <span class="ml-auto shrink-0 text-[9px] tabular-nums opacity-70">{{ entry.start_time }}–{{ entry.end_time }}</span>
                  </div>
                </template>
                <template v-else>
                  <div class="flex items-center justify-between gap-1">
                    <span class="min-w-0 truncate text-xs font-semibold">{{ entry.subject?.name ?? entry.title ?? '—' }}</span>
                    <span v-if="isStemEntry(entry)" class="shrink-0 rounded-full bg-white/70 px-1 text-[8px] font-bold">STEM</span>
                  </div>
                  <div v-if="entryDisplayMode(entry) === 'full' && entry.faculty?.name" class="truncate text-[10px] leading-tight opacity-80">{{ entry.faculty.name }}</div>
                  <div class="truncate text-[9px] leading-tight tabular-nums opacity-70">
                    {{ entry.start_time }}–{{ entry.end_time }}<template v-if="entryDisplayMode(entry) === 'full'"> · {{ entry.classroom?.name ?? '—' }}</template>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Official Activity / Early Dismissal isn't draggable or editable —
           it's purely informational and campus-wide, so it's shown as a
           compact banner instead of eating a share of the proportional
           timeline the way a multi-hour block otherwise would. -->
      <div v-if="officialActivityBand(grade)" class="flex items-center justify-between gap-2 border-t border-purple-100 bg-purple-50 px-4 py-2 text-sm">
        <span class="font-semibold text-purple-800">{{ officialActivityBand(grade).label }}</span>
        <span class="tabular-nums text-purple-700">{{ officialActivityBand(grade).start }}–{{ officialActivityBand(grade).end }}</span>
      </div>
    </div>
  </div>

  <AppModal :show="showOverrideModal" title="Adjust class time" size="sm" @close="showOverrideModal = false">
    <div v-if="editingEntry || editingBand" class="space-y-4">
      <p v-if="editingEntry" class="text-sm text-slate-600">
        {{ editingEntry.subject?.name ?? editingEntry.title }} — currently {{ editingEntry.start_time }}–{{ editingEntry.end_time }}
      </p>
      <p v-else class="text-sm text-slate-600">
        {{ editingBand.band.label }} — currently {{ editingBand.band.start }}–{{ editingBand.band.end }}
      </p>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">New start time</label>
          <input v-model="overrideForm.override_start_time" type="time"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">New end time</label>
          <input v-model="overrideForm.override_end_time" type="time"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
      </div>
      <p v-if="overrideError" class="text-xs text-rose-600">{{ overrideError }}</p>
    </div>

    <template #footer>
      <div class="flex w-full items-center justify-between gap-2">
        <AppButton v-if="editingEntry?.manually_adjusted || editingBand?.band.manually_adjusted" variant="ghost" class="text-rose-600" @click="removeOverride">Remove override</AppButton>
        <div class="ml-auto flex gap-2">
          <AppButton variant="ghost" @click="showOverrideModal = false">Cancel</AppButton>
          <AppButton :loading="savingOverride" @click="saveOverride">Save</AppButton>
        </div>
      </div>
    </template>
  </AppModal>

  <AppModal :show="showAddBandModal" title="Add a band" size="sm" @close="showAddBandModal = false">
    <div v-if="addBandSection" class="space-y-4">
      <p class="text-sm text-slate-600">{{ addBandSection.name }}</p>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Band type</label>
        <select v-model="addBandForm.band_type"
          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option v-for="t in missingBandTypes(addBandSection)" :key="t.value" :value="t.value">{{ t.label }}</option>
        </select>
      </div>
      <div v-if="addBandForm.band_type === 'HEALTH_BREAK'">
        <label class="mb-1 block text-sm font-medium text-slate-700">Title</label>
        <input v-model="addBandForm.title" type="text" placeholder="Health Break"
          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Start time</label>
          <input v-model="addBandForm.override_start_time" type="time"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">End time</label>
          <input v-model="addBandForm.override_end_time" type="time"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
      </div>
      <p v-if="addBandError" class="text-xs text-rose-600">{{ addBandError }}</p>
    </div>

    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <AppButton variant="ghost" @click="showAddBandModal = false">Cancel</AppButton>
        <AppButton :loading="savingAddBand" @click="saveAddBand">Add</AppButton>
      </div>
    </template>
  </AppModal>
</template>

<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import AppModal from '@/Components/AppModal.vue'
import AppButton from '@/Components/AppButton.vue'

const props = defineProps({
  preview: { type: Object, required: true },
  adjustment: { type: Object, required: true },
})

const emit = defineEmits(['update:preview'])

const PX_PER_MINUTE = 1.5
const SNAP_MINUTES = 5
const GUTTER = 44
const MIN_ENTRY_HEIGHT = 24
const MIN_BAND_HEIGHT = 22
const COMPACT_BAND_THRESHOLD_MIN = 15

// Same palette the regular Schedules calendar (ScheduleCalendarCard) uses,
// so a subject reads the same color whether you're looking at the weekly
// schedule or an adjusted day.
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
const ELECTIVE_STYLE = { backgroundColor: '#fef3c7', borderColor: '#f59e0b', color: '#92400e' }
const SCIENCE_CORE_STYLE = { backgroundColor: '#ede9fe', borderColor: '#8b5cf6', color: '#5b21b6' }
const NON_TEACHING_STYLE = { backgroundColor: '#f1f5f9', borderColor: '#94a3b8', color: '#334155' }

const BAND_COLOR_CLASSES = {
  WHITE_SPACE: 'border-violet-200 bg-violet-50 text-violet-700',
  WELLNESS: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  CONSULT: 'border-sky-200 bg-sky-50 text-sky-700',
  ELECTIVE: 'border-amber-300 bg-amber-50 text-amber-700',
  SCIENCE_CORE: 'border-violet-300 bg-violet-100 text-violet-800',
  HEALTH_BREAK: 'border-rose-200 bg-rose-50 text-rose-700',
  OFFICIAL_ACTIVITY: 'border-purple-300 bg-purple-100 text-purple-800',
}

const dragging = ref(null) // { entry, durationMinutes, section }
const conflictSectionId = ref(null)
const resizing = ref(null) // { band, section, edge, startY, originalStart, originalEnd }
const MIN_BAND_MINUTES = 5

const showOverrideModal = ref(false)
const editingEntry = ref(null)
const editingBand = ref(null) // { section, band }
const overrideForm = ref({ override_start_time: '', override_end_time: '' })
const overrideError = ref('')

// Recess almost always has a real default already showing, so it only
// makes sense to offer adding it when this section genuinely has none.
// White Space/Wellness/Health Break are always offered regardless of
// whether a default is already showing — picking one just repositions
// or declares that band for the day, same as dragging it, so there's no
// duplicate-creation risk.
const ADD_BAND_TYPES = [
  { value: 'RECESS', label: 'Recess', alwaysOffered: false },
  { value: 'WHITE_SPACE', label: 'White Space', alwaysOffered: true },
  { value: 'WELLNESS', label: 'Wellness Break', alwaysOffered: true },
  { value: 'HEALTH_BREAK', label: 'Health Break', alwaysOffered: true },
]

const showAddBandModal = ref(false)
const addBandSection = ref(null)
const addBandForm = ref({ band_type: '', override_start_time: '', override_end_time: '', title: '' })
const addBandError = ref('')
const savingAddBand = ref(false)
const savingOverride = ref(false)

const gradesWithEntries = computed(() => (props.preview.grades ?? []).filter(grade => grade.sections?.length))

const calendarStartMinutes = computed(() => toMinutes(props.preview.calendar_start ?? '07:30'))
const calendarEndMinutes = computed(() => toMinutes(props.preview.calendar_end ?? '17:00'))
const totalMinutes = computed(() => calendarEndMinutes.value - calendarStartMinutes.value)

function toMinutes(hhmm) {
  const [h, m] = hhmm.split(':').map(Number)
  return h * 60 + m
}

function fromMinutes(total) {
  const h = Math.floor(total / 60)
  const m = total % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

function offsetStyle(startHHMM, endHHMM, minHeight) {
  const top = (toMinutes(startHHMM) - calendarStartMinutes.value) * PX_PER_MINUTE
  const height = Math.max(minHeight, (toMinutes(endHHMM) - toMinutes(startHHMM)) * PX_PER_MINUTE)
  return { top: `${top}px`, height: `${height}px` }
}

function entryStyle(entry) {
  return offsetStyle(entry.start_time, entry.end_time, MIN_ENTRY_HEIGHT)
}

function bandStyle(band) {
  return offsetStyle(band.start, band.end, MIN_BAND_HEIGHT)
}

// Hour marks for the shared time gutter/gridlines — derived from the day's
// own calendar_start/calendar_end (07:00 on an early-start STEM-split day,
// 07:30 otherwise), same technique as the regular Schedules calendar.
const hourMarks = computed(() => {
  const marks = []
  const firstHour = Math.ceil(calendarStartMinutes.value / 60)
  const lastHour = Math.floor(calendarEndMinutes.value / 60)
  for (let h = firstHour; h <= lastHour; h++) marks.push(h * 60)
  if (marks[0] !== calendarStartMinutes.value) marks.unshift(calendarStartMinutes.value)
  if (marks[marks.length - 1] !== calendarEndMinutes.value) marks.push(calendarEndMinutes.value)
  return marks
})
const halfHourMarks = computed(() => hourMarks.value
  .filter(minute => minute % 60 === 0 && minute + 30 < calendarEndMinutes.value)
  .map(minute => minute + 30))

function hourTopPx(minute) {
  return (minute - calendarStartMinutes.value) * PX_PER_MINUTE
}
function hourLabel(minute) {
  const h = Math.floor(minute / 60)
  const m = minute % 60
  const suffix = h < 12 ? 'AM' : 'PM'
  const hour12 = h % 12 || 12
  return m === 0 ? `${hour12}${suffix}` : `${hour12}:${String(m).padStart(2, '0')}${suffix}`
}

function entryDurationMinutes(entry) {
  return toMinutes(entry.end_time) - toMinutes(entry.start_time)
}
function entryDisplayMode(entry) {
  const d = entryDurationMinutes(entry)
  if (d >= 40) return 'full'
  if (d >= 25) return 'compact'
  return 'minimal'
}

function bandDurationMinutes(band) {
  return toMinutes(band.end) - toMinutes(band.start)
}
function isCompactBand(band) {
  return bandDurationMinutes(band) < COMPACT_BAND_THRESHOLD_MIN
}

function subjectColorStyle(subjectId) {
  const p = PALETTE[(subjectId ?? 0) % PALETTE.length]
  return { backgroundColor: p.bg, borderColor: p.border, color: p.color }
}

function entryColorStyle(entry) {
  if (entry.entry_type === 'non_teaching') return NON_TEACHING_STYLE
  if (entry.subject?.is_elective) return ELECTIVE_STYLE
  if (entry.subject?.is_science_core) return SCIENCE_CORE_STYLE
  return subjectColorStyle(entry.subject?.id)
}

function entryClass(entry) {
  return entry.manually_adjusted ? 'ring-2 ring-indigo-400 ring-inset' : ''
}

function bandClass(band) {
  const base = BAND_COLOR_CLASSES[band.type] ?? 'border-slate-200 bg-slate-100 text-slate-600'
  const interactive = isDraggableBand(band)
    ? 'cursor-grab hover:ring-2 hover:ring-indigo-300 hover:ring-inset active:cursor-grabbing'
    : ''
  const manual = band.manually_adjusted ? 'ring-1 ring-indigo-400' : ''
  return ['border', base, interactive, manual].filter(Boolean).join(' ')
}

function allEntries() {
  return gradesWithEntries.value.flatMap(grade => grade.sections).flatMap(section => section.entries)
}

// Live client-side pre-check only — purely advisory (drives the drop
// target's highlight color). The server call on drop is authoritative;
// this never blocks a drop, it only colors it.
function wouldConflict(entry, proposedStartMinutes, proposedEndMinutes) {
  return allEntries().some(other => {
    if (other.id === entry.id) return false
    const sameRoom = other.classroom?.id && entry.classroom?.id && other.classroom.id === entry.classroom.id
    const sameFaculty = other.faculty?.id && entry.faculty?.id && other.faculty.id === entry.faculty.id
    if (!sameRoom && !sameFaculty) return false
    const otherStart = toMinutes(other.start_time)
    const otherEnd = toMinutes(other.end_time)
    return proposedStartMinutes < otherEnd && otherStart < proposedEndMinutes
  })
}

const DRAGGABLE_BAND_TYPES = ['RECESS', 'WHITE_SPACE', 'WELLNESS', 'HEALTH_BREAK']

function isDraggableBand(band) {
  return DRAGGABLE_BAND_TYPES.includes(band.type)
}

function onDragStart(event, entry, section) {
  const durationMinutes = toMinutes(entry.end_time) - toMinutes(entry.start_time)
  dragging.value = { kind: 'entry', target: entry, durationMinutes, section }
  event.dataTransfer.setData('text/plain', String(entry.id))
  event.dataTransfer.effectAllowed = 'move'
}

function onBandDragStart(event, band, section) {
  if (!isDraggableBand(band)) return
  const durationMinutes = toMinutes(band.end) - toMinutes(band.start)
  dragging.value = { kind: 'band', target: band, durationMinutes, section }
  event.dataTransfer.setData('text/plain', band.type)
  event.dataTransfer.effectAllowed = 'move'
}

function proposedStartMinutes(event, columnEl) {
  const rect = columnEl.getBoundingClientRect()
  const offsetY = event.clientY - rect.top
  const rawMinutes = calendarStartMinutes.value + offsetY / PX_PER_MINUTE
  return Math.round(rawMinutes / SNAP_MINUTES) * SNAP_MINUTES
}

function onDragOver(event, section) {
  if (!dragging.value) return
  const start = proposedStartMinutes(event, event.currentTarget)
  const end = start + dragging.value.durationMinutes
  // Bands are informational overlays — no conflict pre-check for them,
  // matching the spec's "no new overlap validation for bands" decision.
  conflictSectionId.value = dragging.value.kind === 'entry' && wouldConflict(dragging.value.target, start, end)
    ? section.id
    : null
}

async function onDrop(event, section) {
  if (!dragging.value) return
  const { kind, target, durationMinutes } = dragging.value
  const start = proposedStartMinutes(event, event.currentTarget)
  const end = start + durationMinutes
  dragging.value = null
  conflictSectionId.value = null

  const { data } = kind === 'entry'
    ? await axios.post(route('faculty-loading.schedules.day-adjustments.overrides.store', props.adjustment.id), {
        class_schedule_id: target.id,
        override_start_time: fromMinutes(start),
        override_end_time: fromMinutes(end),
      })
    : await axios.post(route('faculty-loading.schedules.day-adjustments.band-overrides.store', props.adjustment.id), {
        section_id: section.id,
        band_type: target.type,
        override_start_time: fromMinutes(start),
        override_end_time: fromMinutes(end),
      })
  emit('update:preview', data)
}

function startResize(event, band, section, edge) {
  resizing.value = {
    band,
    section,
    edge,
    startY: event.clientY,
    originalStart: toMinutes(band.start),
    originalEnd: toMinutes(band.end),
  }
  window.addEventListener('mousemove', onResizeMove)
  window.addEventListener('mouseup', onResizeEnd)
}

function onResizeMove(event) {
  if (!resizing.value) return
  const rawDelta = (event.clientY - resizing.value.startY) / PX_PER_MINUTE
  resizing.value.deltaMinutes = Math.round(rawDelta / SNAP_MINUTES) * SNAP_MINUTES
}

async function onResizeEnd() {
  window.removeEventListener('mousemove', onResizeMove)
  window.removeEventListener('mouseup', onResizeEnd)
  if (!resizing.value) return

  const { band, section, edge, originalStart, originalEnd, deltaMinutes = 0 } = resizing.value
  resizing.value = null

  let start = originalStart
  let end = originalEnd
  if (edge === 'start') {
    start = Math.min(originalStart + deltaMinutes, originalEnd - MIN_BAND_MINUTES)
  } else {
    end = Math.max(originalEnd + deltaMinutes, originalStart + MIN_BAND_MINUTES)
  }

  if (start === originalStart && end === originalEnd) return

  const { data } = await axios.post(
    route('faculty-loading.schedules.day-adjustments.band-overrides.store', props.adjustment.id),
    {
      section_id: section.id,
      band_type: band.type,
      override_start_time: fromMinutes(start),
      override_end_time: fromMinutes(end),
    },
  )
  emit('update:preview', data)
}

function openOverride(entry) {
  editingEntry.value = entry
  editingBand.value = null
  overrideForm.value = { override_start_time: entry.start_time, override_end_time: entry.end_time }
  overrideError.value = ''
  showOverrideModal.value = true
}

function openBandOverride(section, band) {
  editingBand.value = { section, band }
  editingEntry.value = null
  overrideForm.value = { override_start_time: band.start, override_end_time: band.end }
  overrideError.value = ''
  showOverrideModal.value = true
}

async function saveOverride() {
  savingOverride.value = true
  overrideError.value = ''
  try {
    const { data } = editingEntry.value
      ? await axios.post(route('faculty-loading.schedules.day-adjustments.overrides.store', props.adjustment.id), {
          class_schedule_id: editingEntry.value.id,
          override_start_time: overrideForm.value.override_start_time,
          override_end_time: overrideForm.value.override_end_time,
        })
      : await axios.post(route('faculty-loading.schedules.day-adjustments.band-overrides.store', props.adjustment.id), {
          section_id: editingBand.value.section.id,
          band_type: editingBand.value.band.type,
          override_start_time: overrideForm.value.override_start_time,
          override_end_time: overrideForm.value.override_end_time,
        })
    emit('update:preview', data)
    showOverrideModal.value = false
  } catch (error) {
    const errors = error.response?.data?.errors ?? {}
    overrideError.value = errors.override_end_time?.[0] ?? errors.override_start_time?.[0] ?? error.response?.data?.message ?? 'Unable to save this adjustment.'
  } finally {
    savingOverride.value = false
  }
}

async function removeOverride() {
  const { data } = editingEntry.value
    ? await axios.delete(route('faculty-loading.schedules.day-adjustments.overrides.destroy', [props.adjustment.id, editingEntry.value.id]))
    : await axios.delete(route('faculty-loading.schedules.day-adjustments.band-overrides.destroy', [props.adjustment.id, editingBand.value.section.id, editingBand.value.band.type]))
  emit('update:preview', data)
  showOverrideModal.value = false
}

function isStemEntry(entry) {
  return Boolean(entry.subject?.is_stem || entry.subject?.is_elective || entry.subject?.is_science_core)
}

// Official Activity is rendered as a footer banner (see officialActivityBand
// below), not inside the proportional timeline.
function timelineBands(section) {
  return (section.bands ?? []).filter(band => band.type !== 'OFFICIAL_ACTIVITY')
}

// Campus-wide and identical across every section in the grade — read it off
// the first section that has one rather than duplicating it per section.
function officialActivityBand(grade) {
  for (const section of grade.sections ?? []) {
    const band = (section.bands ?? []).find(b => b.type === 'OFFICIAL_ACTIVITY')
    if (band) return band
  }
  return null
}

function missingBandTypes(section) {
  const present = new Set((section.bands ?? []).map(b => b.type))
  return ADD_BAND_TYPES.filter(t => t.alwaysOffered || !present.has(t.value))
}

function openAddBand(section) {
  const missing = missingBandTypes(section)
  if (!missing.length) return
  addBandSection.value = section
  addBandForm.value = { band_type: missing[0].value, override_start_time: '', override_end_time: '', title: '' }
  addBandError.value = ''
  showAddBandModal.value = true
}

async function saveAddBand() {
  savingAddBand.value = true
  addBandError.value = ''
  try {
    const { data } = await axios.post(route('faculty-loading.schedules.day-adjustments.band-overrides.store', props.adjustment.id), {
      section_id: addBandSection.value.id,
      band_type: addBandForm.value.band_type,
      override_start_time: addBandForm.value.override_start_time,
      override_end_time: addBandForm.value.override_end_time,
      ...(addBandForm.value.band_type === 'HEALTH_BREAK' ? { title: addBandForm.value.title || 'Health Break' } : {}),
    })
    emit('update:preview', data)
    showAddBandModal.value = false
  } catch (error) {
    const errors = error.response?.data?.errors ?? {}
    addBandError.value = errors.override_end_time?.[0] ?? errors.override_start_time?.[0] ?? error.response?.data?.message ?? 'Unable to add this band.'
  } finally {
    savingAddBand.value = false
  }
}
</script>
