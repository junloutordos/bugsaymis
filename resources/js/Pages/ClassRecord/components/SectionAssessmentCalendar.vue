<script setup>
import { ref, computed } from 'vue'
import AppModal from '@/Components/AppModal.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import { ChevronLeftIcon, ChevronRightIcon, CalendarDaysIcon, PlusIcon } from '@heroicons/vue/24/outline'
import { checkWatCap, WAT_LIMITS } from '@/Utils/ClassRecord/watUtils.js'

const props = defineProps({
  show:         { type: Boolean, default: false },
  sectionLabel: { type: String, default: '' },
  days:         { type: Array, default: () => [] }, // [{ date, count, items: [...] }]

  // ── Editable / click-to-schedule mode ────────────────────────────────────
  // When editable is true, clicking an empty (or non-full) day opens a
  // picker of `pendingRows` (undated draft rows from the Setup tab) so the
  // teacher can assign that date without leaving the calendar. Read-only
  // callers (e.g. a plain "view section calendar" button) simply omit these
  // props and get the original browse-only behaviour.
  editable:      { type: Boolean, default: false },
  pendingRows:   { type: Array, default: () => [] }, // [{ key, label, is_graded, is_major }]
  disabledDates: { type: Function, default: null },  // (dateStr) => { ok: boolean, reason: ?string } — schedule-day / deadline check
})

const emit = defineEmits(['close', 'schedule', 'apply-to-sections'])

const MONTH_NAMES = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

function todayParts() {
  const d = new Date()
  return { month: d.getMonth() + 1, year: d.getFullYear() }
}

const calMonth = ref(todayParts().month)
const calYear  = ref(todayParts().year)

const dayMap = computed(() => {
  const map = new Map()
  for (const d of props.days) map.set(d.date, d)
  return map
})

// Per-day {graded, major} counts derived straight from `days` — same shape
// Show.vue's own dateCounts computed uses, so checkWatCap() behaves
// identically here and there.
const countsMap = computed(() => {
  const map = new Map()
  for (const d of props.days) {
    const graded = d.graded_count ?? d.items?.filter(i => i.is_graded !== false).length ?? d.count ?? 0
    const major  = d.major_count ?? d.items?.filter(i => i.is_major).length ?? 0
    map.set(d.date, { graded, major })
  }
  return map
})

function formatDate(d) {
  const y   = d.getFullYear()
  const m   = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

const todayStr = formatDate(new Date())

const calendarDays = computed(() => {
  const first    = new Date(calYear.value, calMonth.value - 1, 1)
  const last     = new Date(calYear.value, calMonth.value, 0)
  const startDow = first.getDay()
  const cells    = []

  for (let i = 0; i < startDow; i++) {
    const d = new Date(calYear.value, calMonth.value - 1, -startDow + i + 1)
    cells.push({ date: formatDate(d), day: d.getDate(), current: false, entry: null })
  }
  for (let d = 1; d <= last.getDate(); d++) {
    const date = formatDate(new Date(calYear.value, calMonth.value - 1, d))
    cells.push({ date, day: d, current: true, entry: dayMap.value.get(date) ?? null })
  }
  const remainder = 42 - cells.length
  for (let i = 1; i <= remainder; i++) {
    const d = new Date(calYear.value, calMonth.value, i)
    cells.push({ date: formatDate(d), day: i, current: false, entry: null })
  }
  return cells
})

function navigateMonth(delta) {
  let m = calMonth.value + delta
  let y = calYear.value
  if (m < 1)  { m = 12; y-- }
  if (m > 12) { m = 1;  y++ }
  calMonth.value = m
  calYear.value  = y
}

const selectedDate  = ref(null)
const selectedEntry = computed(() => selectedDate.value ? dayMap.value.get(selectedDate.value) ?? null : null)

// ── Click-to-schedule picker ───────────────────────────────────────────────
const showPicker    = ref(false)
const pickerDate    = ref(null)
const pickerRowKey   = ref(null)
const pickerWarning = ref(null)

const availableRows = computed(() => props.pendingRows.filter(r => !r._scheduled))

function scheduleFeasibility(date, row) {
  if (!row) return { ok: true, reason: null }
  const scheduleCheck = props.disabledDates ? props.disabledDates(date) : { ok: true, reason: null }
  if (!scheduleCheck.ok) return scheduleCheck
  return checkWatCap(countsMap.value, date, row.is_graded, row.is_major)
}

function cellFeasible(date) {
  // Quick "is this day even worth clicking" check — used to grey out cells
  // that are already at the daily cap for a generic graded/non-major row.
  if (!props.editable || !availableRows.value.length) return { ok: false, reason: null }
  const scheduleCheck = props.disabledDates ? props.disabledDates(date) : { ok: true, reason: null }
  if (!scheduleCheck.ok) return scheduleCheck
  return checkWatCap(countsMap.value, date, true, false)
}

function selectDay(cell) {
  if (!cell.current) return
  selectedDate.value = cell.date

  if (props.editable && availableRows.value.length) {
    pickerDate.value    = cell.date
    pickerRowKey.value  = availableRows.value[0]?.key ?? null
    pickerWarning.value = null
    showPicker.value     = true
  }
}

const pickerRow = computed(() => availableRows.value.find(r => r.key === pickerRowKey.value) ?? null)

const pickerFeasibility = computed(() =>
  pickerDate.value ? scheduleFeasibility(pickerDate.value, pickerRow.value) : { ok: true, reason: null }
)

function confirmSchedule() {
  if (!pickerRow.value || !pickerDate.value) return
  const feasibility = pickerFeasibility.value
  if (!feasibility.ok) {
    pickerWarning.value = feasibility.reason
    return
  }
  emit('schedule', { key: pickerRow.value.key, date: pickerDate.value })
  showPicker.value = false

  // Offer the "apply to other sections" contextual action right after a
  // successful placement, only when this class record actually has other
  // sections of the same subject to apply to (caller decides via the
  // emitted event whether that list is non-empty).
  emit('apply-to-sections', { date: pickerDate.value, key: pickerRow.value.key })
}

function closePicker() {
  showPicker.value = false
  pickerWarning.value = null
}

function close() {
  selectedDate.value = null
  showPicker.value = false
  emit('close')
}
</script>

<template>
  <AppModal :show="show" size="xl" :title="editable ? 'Assessment Calendar' : 'Section Assessment Calendar'"
    :subtitle="sectionLabel ? `${sectionLabel} — all subjects, current school year` : ''"
    @close="close">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

      <!-- Calendar (2/3) -->
      <div class="lg:col-span-2">
        <div class="flex items-center justify-between mb-3">
          <button @click="navigateMonth(-1)" class="p-1.5 rounded hover:bg-slate-100">
            <ChevronLeftIcon class="w-5 h-5 text-slate-500" />
          </button>
          <span class="text-sm font-semibold text-slate-700">
            {{ MONTH_NAMES[calMonth - 1] }} {{ calYear }}
          </span>
          <button @click="navigateMonth(1)" class="p-1.5 rounded hover:bg-slate-100">
            <ChevronRightIcon class="w-5 h-5 text-slate-500" />
          </button>
        </div>

        <div v-if="editable" class="mb-2 text-[11px] text-slate-500 flex items-center gap-1.5">
          <PlusIcon class="w-3.5 h-3.5 text-indigo-400" />
          Click a day to schedule an assessment on it. Days at the WAT cap are greyed out.
        </div>

        <div class="grid grid-cols-7 mb-1">
          <div v-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d"
            class="text-center text-xs font-semibold text-slate-400 py-1">
            {{ d }}
          </div>
        </div>

        <div class="grid grid-cols-7 gap-px bg-slate-100 rounded-lg overflow-hidden">
          <div
            v-for="cell in calendarDays" :key="cell.date"
            :class="[
              'bg-white min-h-[74px] p-1.5 text-xs transition-colors',
              !cell.current ? 'opacity-30' : '',
              cell.date === todayStr ? 'ring-2 ring-inset ring-indigo-400' : '',
              selectedDate === cell.date ? 'bg-indigo-50' : '',
              cell.current && editable && !cellFeasible(cell.date).ok && availableRows.length
                ? 'cursor-not-allowed bg-slate-50'
                : 'cursor-pointer hover:bg-indigo-50',
            ]"
            :title="cell.current && editable ? (cellFeasible(cell.date).reason ?? '') : ''"
            @click="selectDay(cell)"
          >
            <div class="flex items-center justify-between mb-1">
              <span :class="['font-medium', cell.date === todayStr ? 'text-indigo-600' : 'text-slate-700']">
                {{ cell.day }}
              </span>
              <span v-if="cell.entry"
                :class="['font-bold text-[10px]', (cell.entry.graded_count ?? cell.entry.count) >= WAT_LIMITS.dailyGraded ? 'text-red-500' : 'text-slate-400']"
                :title="`${cell.entry.graded_count ?? cell.entry.count}/${WAT_LIMITS.dailyGraded} graded · ${cell.entry.major_count ?? 0}/${WAT_LIMITS.dailyMajor} major`">
                {{ cell.entry.graded_count ?? cell.entry.count }}/{{ WAT_LIMITS.dailyGraded }}
              </span>
              <PlusIcon v-else-if="cell.current && editable && availableRows.length && cellFeasible(cell.date).ok"
                class="w-3.5 h-3.5 text-indigo-300" />
            </div>
            <div v-if="cell.entry" class="space-y-0.5 overflow-hidden">
              <div v-for="item in cell.entry.items.slice(0, 2)" :key="item.id"
                class="rounded px-1 py-0.5 truncate leading-tight text-[11px] bg-red-50 border border-red-100 text-red-700">
                [{{ item.category_code }}] {{ item.title }}
              </div>
              <div v-if="cell.entry.items.length > 2" class="text-slate-400 pl-1">
                +{{ cell.entry.items.length - 2 }} more
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Day detail panel (1/3) -->
      <div class="lg:col-span-1 flex flex-col border border-slate-100 rounded-xl p-3">
        <h3 class="text-sm font-semibold text-slate-700 mb-2">
          {{ selectedDate ?? 'Select a date' }}
        </h3>

        <div v-if="!selectedEntry" class="flex-1 flex flex-col items-center justify-center text-slate-300 py-8">
          <CalendarDaysIcon class="w-10 h-10 mb-2" />
          <span class="text-xs">{{ selectedDate ? 'No assessments scheduled' : 'Click a day to view details' }}</span>
        </div>

        <div v-else class="space-y-2 overflow-y-auto max-h-[320px] pr-1">
          <div class="flex flex-wrap gap-1.5">
            <AppBadge :color="(selectedEntry.graded_count ?? selectedEntry.count) >= WAT_LIMITS.dailyGraded ? 'red' : 'slate'">
              {{ selectedEntry.graded_count ?? selectedEntry.count }} / {{ WAT_LIMITS.dailyGraded }} graded
            </AppBadge>
            <AppBadge :color="(selectedEntry.major_count ?? 0) >= WAT_LIMITS.dailyMajor ? 'red' : 'slate'">
              {{ selectedEntry.major_count ?? 0 }} / {{ WAT_LIMITS.dailyMajor }} major
            </AppBadge>
          </div>
          <div v-for="item in selectedEntry.items" :key="item.id"
            :class="['rounded-lg p-2.5 border', item.is_own_record ? 'border-indigo-100 bg-indigo-50/60' : 'border-slate-100 bg-slate-50/60']">
            <p class="text-sm font-medium text-slate-800 leading-tight">[{{ item.category_code }}] {{ item.title }}</p>
            <div class="flex flex-wrap gap-1 mt-1">
              <span v-if="item.is_major" class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px] font-semibold uppercase tracking-wide">Major</span>
              <span v-if="item.is_graded === false" class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 text-[10px] font-semibold uppercase tracking-wide">Non-graded</span>
            </div>
            <p class="text-xs text-slate-500 mt-0.5">{{ item.subject_name }}</p>
            <p v-if="item.teacher_name" class="text-xs text-slate-400 italic">{{ item.teacher_name }}</p>
            <p v-if="item.is_own_record" class="text-[11px] text-indigo-500 mt-0.5">This class record</p>
          </div>
        </div>

        <div v-if="editable && availableRows.length" class="mt-3 pt-3 border-t border-slate-100">
          <AppButton size="sm" variant="secondary" class="w-full justify-center"
            :disabled="!selectedDate"
            @click="selectedDate && selectDay({ date: selectedDate, current: true })">
            <PlusIcon class="w-3.5 h-3.5" /> Schedule an assessment here
          </AppButton>
        </div>
      </div>
    </div>

    <!-- Click-to-schedule picker -->
    <AppModal :show="showPicker" size="sm" title="Schedule Assessment"
      :subtitle="pickerDate ? `Date: ${pickerDate}` : ''"
      @close="closePicker">
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Assessment</label>
          <select v-model="pickerRowKey"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option v-for="row in availableRows" :key="row.key" :value="row.key">
              {{ row.label }}{{ row.is_major ? ' (Major)' : '' }}{{ row.is_graded === false ? ' (Non-graded)' : '' }}
            </option>
          </select>
        </div>
        <p v-if="!pickerFeasibility.ok" class="text-xs text-danger-600">{{ pickerFeasibility.reason }}</p>
        <p v-if="pickerWarning" class="text-xs text-danger-600">{{ pickerWarning }}</p>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="closePicker">Cancel</AppButton>
        <AppButton :disabled="!pickerRow || !pickerFeasibility.ok" @click="confirmSchedule">
          Schedule
        </AppButton>
      </template>
    </AppModal>
  </AppModal>
</template>
