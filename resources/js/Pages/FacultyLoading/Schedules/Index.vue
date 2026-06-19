<template>
  <Head title="Class Schedules" />
  <AdminLayout title="Class Schedules">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Class Schedules</h1>
          <p class="text-sm text-slate-500 mt-0.5">Weekly timetable by section</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <Link :href="route('faculty-loading.auto-schedule.index')"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 rounded-lg font-medium transition-colors">
            <SparklesIcon class="h-4 w-4" /> AI Generate
          </Link>
          <button @click="openForm()"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm">
            <PlusIcon class="h-4 w-4" /> Assign Schedule
          </button>
        </div>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="Object.keys($page.props.errors ?? {}).length"
        class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm space-y-1">
        <p v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</p>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-2 items-center">
        <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm shrink-0">
          <button type="button" @click="setViewBy('section')"
            :class="['px-3 py-1.5 font-medium transition-colors', viewBy === 'section' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50']">
            By Section
          </button>
          <button type="button" @click="setViewBy('faculty')"
            :class="['px-3 py-1.5 font-medium border-l border-slate-200 transition-colors', viewBy === 'faculty' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50']">
            By Faculty
          </button>
        </div>
        <select v-model="filters.term_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option v-for="t in terms" :key="t.id" :value="t.id">
            {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
          </option>
        </select>
        <select v-if="viewBy === 'section'" v-model="filters.section_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option :value="null">All Sections</option>
          <option v-for="sec in sections" :key="sec.id" :value="sec.id">
            Grade {{ sec.levelid }} — {{ sec.sectionname }}
          </option>
        </select>
        <select v-else v-model="filters.faculty_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option :value="null">All Faculty</option>
          <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
        </select>
      </div>

      <!-- Empty state -->
      <div v-if="schedules.length === 0"
        class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <CalendarIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">No schedules found</p>
        <p class="text-xs text-slate-400 mt-1">Assign a schedule or use AI Generate to get started.</p>
      </div>

      <!-- Calendar cards per section / per faculty -->
      <div v-else class="space-y-6">
        <div v-for="groupId in groupsWithSchedules" :key="groupId"
          class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">

          <!-- Group header -->
          <div class="px-4 py-3 bg-gradient-to-r from-indigo-50 to-slate-50 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span v-if="viewBy === 'section'" class="text-xs font-bold text-white bg-indigo-500 px-2.5 py-0.5 rounded-full">
                Grade {{ byGroup[groupId][0].grade_level }}
              </span>
              <h3 class="text-sm font-semibold text-slate-800">
                {{ viewBy === 'faculty' ? (byGroup[groupId][0].faculty?.name ?? 'Unassigned / TBA') : byGroup[groupId][0].section_name }}
              </h3>
              <span class="text-xs text-slate-400">· {{ byGroup[groupId].length }} slot(s)</span>
            </div>
            <button v-if="viewBy === 'section'" @click="openForm({ section_id: groupId })"
              class="inline-flex items-center gap-1 px-2.5 py-1 text-xs bg-white hover:bg-indigo-50 text-indigo-600 border border-indigo-200 rounded-md font-medium transition-colors">
              <PlusIcon class="h-3 w-3" /> Add
            </button>
          </div>

          <!-- Calendar grid -->
          <div class="overflow-x-auto">
            <div style="min-width: 580px">

              <!-- Day column headers -->
              <div class="flex border-b border-slate-100">
                <div class="shrink-0 border-r border-slate-100" :style="{ width: GUTTER + 'px' }" />
                <div v-for="day in WEEKDAYS" :key="day"
                  class="flex-1 text-center py-2 border-l border-slate-100 first:border-l-0">
                  <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    {{ day.slice(0, 3) }}
                  </span>
                  <span v-if="dayConfigs[day]" class="block text-xs text-slate-400 leading-tight">
                    {{ fmtConfigTime(dayConfigs[day].start) }}–{{ fmtConfigTime(dayConfigs[day].end) }}
                  </span>
                </div>
              </div>

              <!-- Time axis + columns -->
              <div class="flex" :style="{ height: CAL_H + 'px' }">

                <!-- Time gutter -->
                <div class="shrink-0 relative border-r border-slate-100" :style="{ width: GUTTER + 'px' }">
                  <div v-for="h in HOURS" :key="h"
                    :style="{ top: hourTop(h) + 'px' }"
                    class="absolute right-2 -translate-y-2.5 select-none">
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

                  <!-- Half-hour dashed lines -->
                  <div v-for="h in HOURS" :key="'hl30-' + h"
                    :style="{ top: (hourTop(h) + SCALE * 30) + 'px' }"
                    class="absolute inset-x-0 border-t border-dashed border-slate-50 pointer-events-none z-0" />

                  <!-- Day columns -->
                  <div v-for="day in WEEKDAYS" :key="day"
                    class="flex-1 relative border-l border-slate-100 overflow-hidden">

                    <!-- Blocked period overlays -->
                    <div v-for="bp in (dayConfigs[day]?.blocked ?? [])" :key="bp.label"
                      :style="blockedStyle(bp)"
                      class="absolute inset-x-0 pointer-events-none z-[1] flex items-center justify-center">
                      <div class="absolute inset-0 bg-slate-100/70" />
                      <span class="relative text-xs text-slate-400 font-medium px-1 text-center leading-tight select-none">
                        {{ bp.label }}
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
                    <div v-for="s in (byGroupDay[groupId]?.[day] ?? [])" :key="s.id"
                      :style="[eventStyle(s), subjectColorStyle(s.subject?.id)]"
                      class="absolute rounded border z-10 overflow-hidden cursor-pointer transition-all hover:shadow-md hover:z-20 hover:scale-[1.01]"
                      @click="openForm(s)">
                      <div class="px-1.5 py-0.5 h-full flex flex-col gap-px overflow-hidden">
                        <div class="text-xs font-bold leading-tight truncate">
                          {{ s.subject?.code }}
                        </div>
                        <div class="text-xs leading-tight truncate opacity-75">
                          {{ secondaryLabel(s) }}
                        </div>
                        <div class="text-xs leading-tight opacity-55 tabular-nums">
                          {{ fmtTime(s.start_time) }}–{{ fmtTime(s.end_time) }}
                        </div>
                      </div>
                      <!-- Status indicator bar -->
                      <div v-if="s.status === 'tentative'"
                        class="absolute top-0 right-0 bottom-0 w-0.5 bg-amber-400" />
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

          <!-- Legend: subjects for this group -->
          <div class="px-4 py-2.5 border-t border-slate-100 flex flex-wrap gap-1.5">
            <div v-for="sub in subjectsInGroup(groupId)" :key="sub.id"
              :style="subjectColorStyle(sub.id)"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium border">
              {{ sub.code }}
            </div>
          </div>

        </div>
      </div>

    </div>

    <!-- Schedule Form Modal -->
    <div v-if="modal"
      class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 backdrop-blur-sm p-4 overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 space-y-4 my-8">
        <h2 class="text-lg font-semibold text-slate-800">{{ form.id ? 'Edit' : 'Assign' }} Schedule</h2>

        <!-- Validation result banner -->
        <div v-if="validationResult" class="space-y-1.5">
          <div v-for="err in validationResult.errors ?? []" :key="err"
            class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-3 py-2 text-xs flex items-start gap-1.5">
            <ExclamationCircleIcon class="h-4 w-4 shrink-0 mt-0.5" /> {{ err }}
          </div>
          <div v-for="w in validationResult.warnings ?? []" :key="w"
            class="bg-amber-50 border border-amber-200 text-amber-700 rounded-lg px-3 py-2 text-xs flex items-start gap-1.5">
            <ExclamationTriangleIcon class="h-4 w-4 shrink-0 mt-0.5" /> {{ w }}
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Faculty *</label>
            <select v-model="form.faculty_id"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select faculty...</option>
              <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Subject *</label>
            <select v-model="form.subject_id"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select subject...</option>
              <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.code }} — {{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Section *</label>
            <select v-model="form.section_id"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select section...</option>
              <option v-for="sec in sections" :key="sec.id" :value="sec.id">
                Grade {{ sec.levelid }} — {{ sec.sectionname }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Classroom *</label>
            <select v-model="form.classroom_id"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select classroom...</option>
              <option v-for="c in classrooms" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Academic Term *</label>
            <select v-model="form.academic_term_id"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select term...</option>
              <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">School Year *</label>
            <select v-model="form.school_year_id"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select school year...</option>
              <option v-for="t in terms" :key="'sy-' + t.id" :value="t.id">{{ t.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Day *</label>
            <select v-model="form.day_of_week"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option value="">Select day...</option>
              <option v-for="d in WEEKDAYS" :key="d" :value="d">{{ d }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Start Time *</label>
            <input v-model="form.start_time" type="time"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">End Time *</label>
            <input v-model="form.end_time" type="time"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
            <select v-model="form.status"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option value="active">Active</option>
              <option value="tentative">Tentative</option>
              <option v-if="form.id" value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
            <textarea v-model="form.remarks" rows="2"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none" />
          </div>

          <!-- Override warnings -->
          <div v-if="validationResult && validationResult.warnings?.length && !validationResult.errors?.length"
            class="col-span-2 flex items-center gap-2">
            <input v-model="form.force" type="checkbox" id="force-save" class="rounded text-amber-500" />
            <label for="force-save" class="text-sm text-amber-700">
              I acknowledge the warnings — save anyway
            </label>
          </div>
        </div>

        <div class="flex justify-between gap-3 pt-1">
          <button type="button" @click="checkConflicts"
            class="px-4 py-2 text-sm border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-lg font-medium flex items-center gap-1.5">
            <MagnifyingGlassIcon class="h-4 w-4" /> Check Conflicts
          </button>
          <div class="flex gap-2">
            <button @click="modal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">
              Cancel
            </button>
            <button @click="save" :disabled="form.processing"
              class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium disabled:opacity-50">
              {{ form.id ? 'Update' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'
import {
  CalendarIcon, CheckCircleIcon, ExclamationCircleIcon, ExclamationTriangleIcon,
  MagnifyingGlassIcon, PencilIcon, PlusIcon, SparklesIcon, TrashIcon,
} from '@heroicons/vue/24/outline'

// ── Calendar constants ───────────────────────────────────────────────────────

const WEEKDAYS  = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']
const CAL_START = 7 * 60        // 7:00 AM in minutes
const CAL_END   = 16 * 60 + 30  // 4:30 PM in minutes
const SCALE     = 1.2            // px per minute
const GUTTER    = 44             // width of the time-axis gutter in px
const CAL_H     = (CAL_END - CAL_START) * SCALE  // total calendar height in px

// Hour marks to draw (7 AM through 4 PM inclusive)
const HOURS = Array.from({ length: 10 }, (_, i) => i + 7)

// Subject color palette — 10 distinct colors, cycling by subject_id % 10
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

// ── Props ────────────────────────────────────────────────────────────────────

const props = defineProps({
  schedules:   { type: Array,  default: () => [] },
  terms:       { type: Array,  default: () => [] },
  faculty:     { type: Array,  default: () => [] },
  subjects:    { type: Array,  default: () => [] },
  classrooms:  { type: Array,  default: () => [] },
  sections:    { type: Array,  default: () => [] },
  currentTerm: { type: Object, default: null },
  filters:     { type: Object, default: () => ({}) },
  dayConfigs:  { type: Object, default: () => ({}) },
})

// ── Filters ──────────────────────────────────────────────────────────────────

const filters = reactive({
  term_id:    props.filters.term_id    ?? props.currentTerm?.id ?? null,
  section_id: props.filters.section_id ?? null,
  faculty_id: props.filters.faculty_id ?? null,
})

function applyFilters() {
  router.get(route('faculty-loading.schedules.index'), filters, { preserveState: true })
}

// ── View mode (group calendar cards by section or by faculty) ────────────────

const viewBy = ref(props.filters.faculty_id ? 'faculty' : 'section')

function setViewBy(mode) {
  if (viewBy.value === mode) return
  viewBy.value = mode
  if (mode === 'section') {
    filters.faculty_id = null
  } else {
    filters.section_id = null
  }
  applyFilters()
}

// ── Grouping ─────────────────────────────────────────────────────────────────

/** Group key for a schedule row, depending on the active view mode. */
function groupKeyOf(s) {
  return viewBy.value === 'faculty' ? (s.faculty?.id ?? 'unassigned') : s.section_id
}

/** { groupId: [schedules] } */
const byGroup = computed(() => {
  const map = {}
  for (const s of props.schedules) {
    const k = groupKeyOf(s)
    if (!map[k]) map[k] = []
    map[k].push(s)
  }
  return map
})

/** Group IDs in display order (backend already sorted by grade + name + day + time) */
const groupsWithSchedules = computed(() => {
  const seen = []
  for (const s of props.schedules) {
    const k = groupKeyOf(s)
    if (!seen.includes(k)) seen.push(k)
  }
  return seen
})

/** { groupId: { day: [schedules] } } */
const byGroupDay = computed(() => {
  const map = {}
  for (const s of props.schedules) {
    const k = groupKeyOf(s)
    if (!map[k]) map[k] = {}
    if (!map[k][s.day_of_week]) map[k][s.day_of_week] = []
    map[k][s.day_of_week].push(s)
  }
  return map
})

/** Unique subjects for the legend of a given group */
function subjectsInGroup(groupId) {
  const seen = new Map()
  for (const s of (byGroup.value[groupId] ?? [])) {
    if (s.subject && !seen.has(s.subject.id)) seen.set(s.subject.id, s.subject)
  }
  return [...seen.values()]
}

/** Text shown inside an event block for the dimension that ISN'T the grouping axis. */
function secondaryLabel(s) {
  return viewBy.value === 'faculty'
    ? `G${s.grade_level} ${s.section_name}`
    : (s.faculty?.name ? lastNameOf(s.faculty.name) : 'TBA')
}

// ── Calendar helpers ─────────────────────────────────────────────────────────

function timeToMin(t) {
  if (!t) return 0
  const parts = t.split(':')
  return parseInt(parts[0]) * 60 + parseInt(parts[1])
}

/** Top offset in px for a given hour mark */
function hourTop(h) {
  return (h * 60 - CAL_START) * SCALE
}

/** Absolute positioning style for a schedule event block */
function eventStyle(s) {
  const sm = Math.max(timeToMin(s.start_time), CAL_START)
  const em = Math.min(timeToMin(s.end_time), CAL_END)
  return {
    position: 'absolute',
    top:    ((sm - CAL_START) * SCALE) + 'px',
    height: Math.max((em - sm) * SCALE, 24) + 'px',
    left:   '2px',
    right:  '2px',
  }
}

/** Absolute positioning style for a blocked-period overlay */
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

/** Inline style for subject-colored event block (cycles palette by subject_id) */
function subjectColorStyle(subjectId) {
  const p = PALETTE[(subjectId ?? 0) % PALETTE.length]
  return {
    backgroundColor: p.bg,
    borderColor:     p.border,
    color:           p.color,
  }
}

// ── Formatters ───────────────────────────────────────────────────────────────

function fmtTime(t) {
  if (!t) return '—'
  const [h, m] = t.split(':')
  const hour = parseInt(h)
  return `${hour % 12 || 12}:${m} ${hour >= 12 ? 'PM' : 'AM'}`
}

/** Format HH:MM:SS → h:MM AM/PM for day config display */
function fmtConfigTime(t) {
  if (!t) return ''
  const [h, m] = t.split(':')
  const hour = parseInt(h)
  return `${hour % 12 || 12}:${m}${hour >= 12 ? 'PM' : 'AM'}`
}

/** Extract surname for compact display in event blocks */
function lastNameOf(name) {
  if (!name) return ''
  const parts = name.trim().split(' ')
  return parts[parts.length - 1]
}

// ── Form & modal ─────────────────────────────────────────────────────────────

const modal            = ref(false)
const validationResult = ref(null)

const form = useForm({
  id: null, faculty_id: null, subject_id: null, section_id: null, classroom_id: null,
  school_year_id: null, academic_term_id: null, day_of_week: '',
  start_time: '', end_time: '', status: 'active', remarks: '', force: false,
})

/**
 * Open the form modal.
 * - Pass a full schedule object (with s.id) to edit.
 * - Pass { section_id } to pre-fill a new-schedule form for that section.
 * - Pass nothing to open a blank new-schedule form.
 */
function openForm(s = null) {
  validationResult.value = null
  if (s && s.id) {
    Object.assign(form, {
      id:               s.id,
      faculty_id:       s.faculty?.id      ?? null,
      subject_id:       s.subject?.id      ?? null,
      section_id:       s.section_id,
      classroom_id:     s.classroom?.id    ?? null,
      school_year_id:   null,
      academic_term_id: filters.term_id    ?? null,
      day_of_week:      s.day_of_week,
      start_time:       s.start_time?.slice(0, 5) ?? '',
      end_time:         s.end_time?.slice(0, 5)   ?? '',
      status:           s.status,
      remarks:          s.remarks ?? '',
      force:            false,
    })
  } else {
    form.reset()
    form.id               = null
    form.status           = 'active'
    form.force            = false
    form.academic_term_id = filters.term_id    ?? null
    form.section_id       = s?.section_id ?? filters.section_id ?? null
  }
  modal.value = true
}

async function checkConflicts() {
  if (!form.faculty_id || !form.academic_term_id || !form.day_of_week || !form.start_time || !form.end_time) return
  const payload = {
    faculty_id:       form.faculty_id,
    subject_id:       form.subject_id   ?? 0,
    section_id:       form.section_id   ?? 0,
    classroom_id:     form.classroom_id ?? 0,
    academic_term_id: form.academic_term_id,
    day_of_week:      form.day_of_week,
    start_time:       form.start_time,
    end_time:         form.end_time,
    exclude_id:       form.id,
  }
  try {
    const { data } = await axios.post(route('faculty-loading.schedules.validate'), payload)
    validationResult.value = data
  } catch (e) {
    console.error(e)
  }
}

function save() {
  if (form.id) {
    form.put(route('faculty-loading.schedules.update', form.id), {
      onSuccess: () => { modal.value = false; validationResult.value = null },
    })
  } else {
    form.post(route('faculty-loading.schedules.store'), {
      onSuccess: () => { modal.value = false; validationResult.value = null },
    })
  }
}
</script>
