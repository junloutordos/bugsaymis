<template>
  <Head title="My Work Schedule" />
  <AdminLayout title="My Work Schedule">
    <div class="space-y-6 max-w-3xl mx-auto">

      <!-- Header -->
      <div>
        <h1 class="text-xl font-semibold text-slate-800">My Work Schedule</h1>
        <p class="text-sm text-slate-500 mt-0.5">Set your preferred work schedule and submit it to HR for approval.</p>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" /> {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.error }}
      </div>

      <!-- ── Current Active Schedule ──────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <ClockIcon class="h-4 w-4 text-indigo-500" /> Current Active Schedule
          </h2>
        </div>
        <div class="px-5 py-5">
          <div v-if="!currentSchedule" class="text-slate-400 text-sm text-center py-4">
            No active schedule assigned yet. Submit a request below.
          </div>
          <div v-else class="space-y-3">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="font-semibold text-slate-800">{{ currentSchedule.name }}</p>
                <p class="text-sm text-slate-600 mt-0.5">{{ formatDaysWithTimes(currentSchedule.daily_schedules) }}</p>
                <p class="text-xs text-slate-400 mt-1">
                  Effective: {{ currentSchedule.effective_date }}
                  <template v-if="currentSchedule.remarks"> · {{ currentSchedule.remarks }}</template>
                </p>
              </div>
              <span class="shrink-0 text-xs font-semibold bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded-full">Active</span>
            </div>
            <!-- Day breakdown -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-2">
              <div v-for="entry in sortedDailySchedules(currentSchedule.daily_schedules)" :key="entry.day"
                :class="['rounded-lg px-3 py-2', entry.work_from_home ? 'bg-blue-50' : 'bg-slate-50']">
                <div class="flex items-center gap-1.5">
                  <p class="text-xs font-semibold text-slate-600">{{ entry.day }}</p>
                  <span v-if="entry.work_from_home"
                    class="inline-flex items-center gap-0.5 text-[10px] font-semibold bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded-full">
                    <HomeIcon class="h-2.5 w-2.5" /> WFH
                  </span>
                </div>
                <p class="text-xs text-slate-500 font-mono mt-0.5">{{ entry.time_in }} – {{ entry.time_out }}</p>
                <p v-if="entry.lunch_start && entry.lunch_end" class="text-[10px] text-slate-400 font-mono mt-0.5">
                  Lunch: {{ entry.lunch_start }} – {{ entry.lunch_end }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Pending Submission ───────────────────────────────────────────── -->
      <div v-if="pendingSubmission" class="bg-amber-50 border border-amber-200 rounded-xl shadow-sm">
        <div class="px-5 py-4 border-b border-amber-200 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-amber-800 flex items-center gap-2">
            <ClockIcon class="h-4 w-4 text-amber-500" /> Pending Submission
          </h2>
          <span class="text-xs font-semibold bg-amber-200 text-amber-800 px-2.5 py-1 rounded-full">Awaiting HR Review</span>
        </div>
        <div class="px-5 py-4">
          <p class="font-medium text-amber-900">{{ pendingSubmission.name }}</p>
          <p class="text-sm text-amber-700 mt-0.5">{{ formatDaysWithTimes(pendingSubmission.daily_schedules) }}</p>
          <p class="text-xs text-amber-600 mt-1">Effective: {{ pendingSubmission.effective_date }}</p>
          <div class="mt-3">
            <button @click="cancelSubmission"
              class="text-xs font-medium text-red-600 hover:text-red-800 border border-red-200 hover:bg-red-50 px-3 py-1.5 rounded-lg transition-colors">
              Cancel Submission
            </button>
          </div>
        </div>
      </div>

      <!-- ── Submit New Schedule ─────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm" :class="pendingSubmission ? 'opacity-60 pointer-events-none' : ''">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <PlusCircleIcon class="h-4 w-4 text-indigo-500" />
            {{ pendingSubmission ? 'Submit New Schedule (cancel pending first)' : 'Submit New Schedule Request' }}
          </h2>
          <p class="text-xs text-slate-400 mt-0.5">Select a preset or customize your daily times. HR will review and approve.</p>
        </div>

        <div class="px-5 py-5 space-y-5">

          <!-- Preset picker -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Load from Preset (optional)</label>
            <select v-model="selectedPresetId" @change="loadPreset"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
              <option value="">— Select a preset to pre-fill times —</option>
              <option v-for="p in presets" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>

          <!-- Schedule name -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Schedule Name <span class="text-red-500">*</span></label>
            <input v-model="submitForm.name" type="text" placeholder="e.g. My Flex Schedule"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            <p v-if="submitForm.errors.name" class="text-red-500 text-xs mt-1">{{ submitForm.errors.name }}</p>
          </div>

          <!-- Per-day schedule -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="text-xs font-medium text-slate-600">Work Days &amp; Times <span class="text-red-500">*</span></label>
              <button type="button" @click="copyFirstToAll"
                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Copy Mon to all active days</button>
            </div>
            <div class="border border-slate-200 rounded-lg overflow-hidden">
              <div class="grid grid-cols-[80px_1fr_1fr_1fr_1fr_72px] bg-slate-50 px-3 py-2 text-[11px] font-semibold text-slate-500 uppercase tracking-wide">
                <span>Day</span><span>Time In</span><span>Time Out</span><span>Lunch Start</span><span>Lunch End</span><span class="text-center">WFH</span>
              </div>
              <div v-for="d in allDays" :key="d"
                class="grid grid-cols-[80px_1fr_1fr_1fr_1fr_72px] items-center px-3 py-2 border-t border-slate-100"
                :class="submitForm.daily_schedules[d] ? (submitForm.daily_schedules[d].work_from_home ? 'bg-blue-50' : 'bg-white') : 'bg-slate-50/60 opacity-60'">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                  <input type="checkbox" :checked="!!submitForm.daily_schedules[d]"
                    @change="toggleDay(d)"
                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-400" />
                  <span class="text-sm font-medium text-slate-700">{{ d }}</span>
                </label>
                <input v-if="submitForm.daily_schedules[d]"
                  v-model="submitForm.daily_schedules[d].time_in"
                  type="time"
                  class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm font-mono mr-1 focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                <span v-else class="text-xs text-slate-400 italic mr-1">Rest day</span>
                <input v-if="submitForm.daily_schedules[d]"
                  v-model="submitForm.daily_schedules[d].time_out"
                  type="time"
                  class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm font-mono mr-1 focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                <span v-else></span>
                <input v-if="submitForm.daily_schedules[d]"
                  v-model="submitForm.daily_schedules[d].lunch_start"
                  type="time"
                  placeholder="12:00"
                  class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm font-mono mr-1 focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                <span v-else></span>
                <input v-if="submitForm.daily_schedules[d]"
                  v-model="submitForm.daily_schedules[d].lunch_end"
                  type="time"
                  placeholder="13:00"
                  class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm font-mono mr-1 focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                <span v-else></span>
                <div class="flex justify-center">
                  <button v-if="submitForm.daily_schedules[d]"
                    type="button"
                    @click="submitForm.daily_schedules[d].work_from_home = !submitForm.daily_schedules[d].work_from_home"
                    :class="['inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-1 rounded-full transition-colors',
                      submitForm.daily_schedules[d].work_from_home
                        ? 'bg-blue-100 text-blue-600 hover:bg-blue-200'
                        : 'bg-slate-100 text-slate-400 hover:bg-slate-200']">
                    <HomeIcon class="h-3 w-3" />
                    {{ submitForm.daily_schedules[d].work_from_home ? 'WFH' : '' }}
                  </button>
                </div>
              </div>
            </div>
            <p v-if="submitForm.errors.daily_schedules" class="text-red-500 text-xs mt-1">{{ submitForm.errors.daily_schedules }}</p>
          </div>

          <!-- Effective date + remarks -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Effective Date <span class="text-red-500">*</span></label>
              <input v-model="submitForm.effective_date" type="date" :min="today"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              <p v-if="submitForm.errors.effective_date" class="text-red-500 text-xs mt-1">{{ submitForm.errors.effective_date }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Remarks (optional)</label>
              <input v-model="submitForm.remarks" type="text" placeholder="e.g. Shifting to hybrid arrangement"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            </div>
          </div>

          <button @click="submitSchedule"
            :disabled="submitForm.processing || !submitForm.name || !Object.keys(submitForm.daily_schedules).length || !submitForm.effective_date"
            class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2">
            <svg v-if="submitForm.processing" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
            </svg>
            {{ submitForm.processing ? 'Submitting…' : 'Submit for HR Approval' }}
          </button>
        </div>
      </div>

      <!-- ── Schedule History ────────────────────────────────────────────── -->
      <div v-if="history.length" class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <DocumentTextIcon class="h-4 w-4 text-indigo-500" /> Past Submissions
          </h2>
        </div>
        <ul class="divide-y divide-slate-100">
          <li v-for="h in history" :key="h.id" class="px-5 py-3 flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-sm font-medium text-slate-700">{{ h.name }}</p>
              <p class="text-xs text-slate-500 mt-0.5">{{ formatDaysWithTimes(h.daily_schedules) }}</p>
              <p class="text-xs text-slate-400 mt-0.5">Effective: {{ h.effective_date }}</p>
              <p v-if="h.rejection_reason" class="text-xs text-red-500 mt-0.5">Reason: {{ h.rejection_reason }}</p>
            </div>
            <span class="shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full"
              :class="{
                'bg-emerald-100 text-emerald-700': h.status === 'approved',
                'bg-red-100 text-red-600': h.status === 'rejected',
              }">
              {{ h.status === 'approved' ? 'Approved' : 'Rejected' }}
            </span>
          </li>
        </ul>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  ClockIcon, CheckCircleIcon, PlusCircleIcon, DocumentTextIcon, HomeIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  currentSchedule:   Object,
  pendingSubmission: Object,
  presets:           Array,
  history:           Array,
})

const allDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
const today   = new Date().toISOString().slice(0, 10)

// ── Submit form ───────────────────────────────────────────────────────────────

const selectedPresetId = ref('')

const submitForm = useForm({
  preset_id:       null,
  name:            '',
  daily_schedules: {
    Mon: { time_in: '08:00', time_out: '17:00', lunch_start: '12:00', lunch_end: '13:00', work_from_home: false },
    Tue: { time_in: '08:00', time_out: '17:00', lunch_start: '12:00', lunch_end: '13:00', work_from_home: false },
    Wed: { time_in: '08:00', time_out: '17:00', lunch_start: '12:00', lunch_end: '13:00', work_from_home: false },
    Thu: { time_in: '08:00', time_out: '17:00', lunch_start: '12:00', lunch_end: '13:00', work_from_home: false },
    Fri: { time_in: '08:00', time_out: '17:00', lunch_start: '12:00', lunch_end: '13:00', work_from_home: false },
  },
  effective_date:  today,
  remarks:         '',
})

function loadPreset() {
  const preset = props.presets.find(p => p.id === Number(selectedPresetId.value))
  if (!preset) return
  submitForm.preset_id       = preset.id
  submitForm.name            = preset.name
  submitForm.daily_schedules = JSON.parse(JSON.stringify(preset.daily_schedules ?? {}))
}

function toggleDay(day) {
  if (submitForm.daily_schedules[day]) {
    const updated = { ...submitForm.daily_schedules }
    delete updated[day]
    submitForm.daily_schedules = updated
  } else {
    const firstActive = Object.values(submitForm.daily_schedules)[0]
    submitForm.daily_schedules = {
      ...submitForm.daily_schedules,
      [day]: {
        time_in: firstActive?.time_in ?? '08:00',
        time_out: firstActive?.time_out ?? '17:00',
        lunch_start: firstActive?.lunch_start ?? '12:00',
        lunch_end: firstActive?.lunch_end ?? '13:00',
        work_from_home: false,
      },
    }
  }
}

function copyFirstToAll() {
  const first = submitForm.daily_schedules[
    allDays.find(d => submitForm.daily_schedules[d])
  ]
  if (!first) return
  const updated = {}
  for (const d of allDays) {
    if (submitForm.daily_schedules[d]) {
      updated[d] = {
        time_in: first.time_in,
        time_out: first.time_out,
        lunch_start: first.lunch_start,
        lunch_end: first.lunch_end,
        work_from_home: submitForm.daily_schedules[d].work_from_home ?? false,
      }
    }
  }
  submitForm.daily_schedules = updated
}

function submitSchedule() {
  submitForm.post(route('hr.schedules.submit'))
}

// ── Cancel pending submission ─────────────────────────────────────────────────

function cancelSubmission() {
  if (!confirm('Cancel your pending schedule submission?')) return
  useForm({}).delete(route('hr.schedules.cancel', props.pendingSubmission.id))
}

// ── Helpers ───────────────────────────────────────────────────────────────────

const DAY_ORDER = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

function sortedDailySchedules(dailySchedules) {
  if (!dailySchedules) return []
  return DAY_ORDER
    .filter(d => d in dailySchedules)
    .map(d => ({ day: d, ...dailySchedules[d] }))
}

function formatDays(days) {
  if (!days?.length) return 'Mon–Fri'
  if (days.length === 5 && !days.includes('Sat') && !days.includes('Sun')) return 'Mon–Fri'
  if (days.length === 6 && !days.includes('Sun')) return 'Mon–Sat'
  return days.join(', ')
}

function formatDaysWithTimes(dailySchedules) {
  if (!dailySchedules || !Object.keys(dailySchedules).length) return '—'
  const officeGroups = {}
  const wfhDays = []
  for (const [day, t] of Object.entries(dailySchedules)) {
    if (t.work_from_home) {
      wfhDays.push(day)
    } else {
      const key = `${t.time_in}–${t.time_out}`
      if (!officeGroups[key]) officeGroups[key] = []
      officeGroups[key].push(day)
    }
  }
  const parts = Object.entries(officeGroups).map(([times, days]) => `${formatDays(days)}: ${times}`)
  if (wfhDays.length) parts.push(`${formatDays(wfhDays)}: WFH`)
  return parts.join(' | ')
}
</script>
