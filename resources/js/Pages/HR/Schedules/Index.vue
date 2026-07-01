<template>
  <Head title="Work Schedules" />
  <AdminLayout title="Work Schedules">
    <div class="space-y-6">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Work Schedules</h1>
          <p class="text-sm text-slate-500 mt-0.5">Review employee submissions and manage schedule presets.</p>
        </div>
        <button @click="openAddPreset"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors shadow-sm">
          <PlusIcon class="h-4 w-4" /> New Preset
        </button>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" /> {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.error }}
      </div>

      <!-- ── Pending Submissions ──────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-amber-200 shadow-sm">
        <div class="px-5 py-4 border-b border-amber-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <ClockIcon class="h-4 w-4 text-amber-500" /> Pending Schedule Submissions
          </h2>
          <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
            :class="pendingSubmissions.length ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-400'">
            {{ pendingSubmissions.length }} pending
          </span>
        </div>

        <div v-if="!pendingSubmissions.length" class="px-5 py-10 text-center text-slate-400 text-sm">
          No pending submissions. Employees can submit schedule requests from their My Work Schedule page.
        </div>

        <div v-else class="divide-y divide-slate-100">
          <div v-for="sub in pendingSubmissions" :key="sub.id"
            class="px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <p class="font-medium text-slate-800 text-sm">{{ sub.user?.name ?? '—' }}</p>
                <span class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">{{ sub.user?.emp_category ?? '—' }}</span>
              </div>
              <p class="text-sm text-slate-700 mt-0.5">{{ sub.name }}</p>
              <p class="text-xs text-slate-500 mt-0.5">
                {{ formatDaysWithTimes(sub.daily_schedules) }}
              </p>
              <p class="text-xs text-slate-400 mt-0.5">
                Effective: <span class="font-medium text-slate-600">{{ sub.effective_date }}</span>
                <template v-if="sub.remarks"> · {{ sub.remarks }}</template>
              </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <button @click="approveSubmission(sub)"
                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                <CheckCircleIcon class="h-3.5 w-3.5" /> Approve
              </button>
              <button @click="openReject(sub)"
                class="inline-flex items-center gap-1.5 border border-red-200 hover:bg-red-50 text-red-600 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                <XCircleIcon class="h-3.5 w-3.5" /> Reject
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        <!-- ── Schedule Presets ─────────────────────────────── -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
          <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
              <ClockIcon class="h-4 w-4 text-indigo-500" /> Schedule Presets
            </h2>
            <span class="text-xs text-slate-400">{{ presets.length }} preset(s)</span>
          </div>

          <div v-if="!presets.length" class="px-5 py-12 text-center text-slate-400 text-sm">
            No presets yet. Create one to get started.
          </div>

          <ul class="divide-y divide-slate-100">
            <li v-for="p in presets" :key="p.id" class="px-5 py-4 flex items-start justify-between gap-3 hover:bg-slate-50/60">
              <div class="min-w-0">
                <p class="font-medium text-slate-800 text-sm">{{ p.name }}</p>
                <p class="text-xs text-slate-500 mt-0.5">
                  {{ formatDaysWithTimes(p.daily_schedules) }} &bull;
                  Grace: {{ p.grace_period_minutes }}m
                </p>
                <p v-if="p.remarks" class="text-xs text-slate-400 mt-0.5 truncate max-w-xs">{{ p.remarks }}</p>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <button @click="openEditPreset(p)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-indigo-600" title="Edit">
                  <PencilSquareIcon class="h-4 w-4" />
                </button>
                <button @click="deletePreset(p)" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-400 hover:text-red-500" title="Delete">
                  <TrashIcon class="h-4 w-4" />
                </button>
              </div>
            </li>
          </ul>
        </div>

        <!-- ── Assign Schedule ──────────────────────────────── -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
          <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
              <UserGroupIcon class="h-4 w-4 text-indigo-500" /> Assign Schedule to Employees
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">Force-assign a schedule to employees directly, bypassing the submission flow.</p>
          </div>

          <div class="px-5 py-4 space-y-4">
            <!-- Step 1: Pick preset -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">1. Select Schedule Preset</label>
              <select v-model="assignForm.preset_id"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="">— Choose a preset —</option>
                <option v-for="p in presets" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>

            <!-- Step 2: Effective date -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">2. Effective Date</label>
              <input v-model="assignForm.effective_date" type="date"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            </div>

            <!-- Step 3: Filter + pick employees -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">3. Select Employees</label>

              <!-- Category filter tabs -->
              <div class="flex flex-wrap gap-2 mb-3">
                <button @click="selectedCategory = ''"
                  :class="selectedCategory === '' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                  class="px-3 py-1 rounded-full text-xs font-medium transition-colors">
                  All
                </button>
                <button v-for="cat in categories" :key="cat"
                  @click="selectedCategory = cat"
                  :class="selectedCategory === cat ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                  class="px-3 py-1 rounded-full text-xs font-medium transition-colors">
                  {{ cat }}
                </button>
              </div>

              <!-- Select all for category -->
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs text-slate-400">{{ filteredEmployees.length }} employee(s) shown</span>
                <div class="flex gap-3">
                  <button @click="selectAll" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Select All</button>
                  <button @click="clearAll" class="text-xs text-slate-500 hover:text-slate-700">Clear</button>
                </div>
              </div>

              <!-- Employee list -->
              <div class="border border-slate-200 rounded-lg overflow-hidden max-h-64 overflow-y-auto">
                <div v-if="!filteredEmployees.length" class="px-4 py-6 text-center text-slate-400 text-xs">
                  No employees found.
                </div>
                <label v-for="emp in filteredEmployees" :key="emp.id"
                  class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-0">
                  <input type="checkbox" :value="emp.id" v-model="assignForm.user_ids"
                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-400" />
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-700 truncate">{{ emp.name }}</p>
                    <p class="text-xs text-slate-400">
                      {{ emp.emp_category || '—' }}<template v-if="emp.badge_id"> · Badge: {{ emp.badge_id }}</template>
                      <template v-if="currentAssignment(emp.id)">
                        · <span class="text-indigo-500">{{ currentAssignment(emp.id) }}</span>
                      </template>
                    </p>
                  </div>
                </label>
              </div>

              <p v-if="assignForm.user_ids.length" class="text-xs text-indigo-600 mt-1.5 font-medium">
                {{ assignForm.user_ids.length }} employee(s) selected
              </p>
            </div>

            <button @click="submitAssign"
              :disabled="assignForm.processing || !assignForm.preset_id || !assignForm.effective_date || !assignForm.user_ids.length"
              class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2">
              <svg v-if="assignForm.processing" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
              </svg>
              {{ assignForm.processing ? 'Assigning…' : 'Assign Schedule' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Current Assignments Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <TableCellsIcon class="h-4 w-4 text-indigo-500" /> Current Assignments
          </h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Schedule</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Work Days / Times</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Effective</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!assignments.length">
                <td colspan="5" class="px-4 py-10 text-center text-slate-400 text-sm">No schedules assigned yet.</td>
              </tr>
              <tr v-for="a in assignments" :key="a.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 font-medium text-slate-800">{{ a.user?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-500 text-xs">{{ a.user?.emp_category ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-700">{{ a.name }}</td>
                <td class="px-4 py-3 text-slate-600 text-xs">{{ formatDaysWithTimes(a.daily_schedules) }}</td>
                <td class="px-4 py-3 text-slate-500 text-xs">{{ a.effective_date }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- Add/Edit Preset Modal -->
    <Teleport to="body">
      <div v-if="presetModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl mx-4 flex flex-col max-h-[90vh]">
          <div class="px-6 py-4 border-b border-slate-100 shrink-0">
            <h3 class="text-base font-semibold text-slate-800">
              {{ presetModal.mode === 'add' ? 'New Schedule Preset' : 'Edit Preset' }}
            </h3>
          </div>

          <div class="px-6 py-4 overflow-y-auto space-y-4">
            <!-- Name -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Preset Name <span class="text-red-500">*</span></label>
              <input v-model="presetForm.name" type="text" placeholder="e.g. Plantilla Non-Teaching"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              <p v-if="presetForm.errors.name" class="text-red-500 text-xs mt-1">{{ presetForm.errors.name }}</p>
            </div>

            <!-- Per-day schedule -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <label class="text-xs font-medium text-slate-600">Work Days &amp; Times <span class="text-red-500">*</span></label>
                <button type="button" @click="copyFirstToAll"
                  class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Copy Mon to all active days</button>
              </div>
              <div class="border border-slate-200 rounded-lg overflow-hidden">
                <div class="grid grid-cols-[80px_1fr_1fr_1fr_1fr_40px] bg-slate-50 px-3 py-2 text-[11px] font-semibold text-slate-500 uppercase tracking-wide">
                  <span>Day</span><span>Time In</span><span>Time Out</span><span>Lunch Start</span><span>Lunch End</span><span></span>
                </div>
                <div v-for="d in allDays" :key="d"
                  class="grid grid-cols-[80px_1fr_1fr_1fr_1fr_40px] items-center px-3 py-2 border-t border-slate-100"
                  :class="presetForm.daily_schedules[d] ? 'bg-white' : 'bg-slate-50/60 opacity-60'">
                  <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" :checked="!!presetForm.daily_schedules[d]"
                      @change="toggleDay(d)"
                      class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-400" />
                    <span class="text-sm font-medium text-slate-700">{{ d }}</span>
                  </label>
                  <input v-if="presetForm.daily_schedules[d]"
                    v-model="presetForm.daily_schedules[d].time_in"
                    type="time"
                    class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm font-mono mr-1 focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                  <span v-else class="text-xs text-slate-400 italic mr-1">Rest day</span>
                  <input v-if="presetForm.daily_schedules[d]"
                    v-model="presetForm.daily_schedules[d].time_out"
                    type="time"
                    class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm font-mono mr-1 focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                  <span v-else></span>
                  <input v-if="presetForm.daily_schedules[d]"
                    v-model="presetForm.daily_schedules[d].lunch_start"
                    type="time"
                    placeholder="12:00"
                    class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm font-mono mr-1 focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                  <span v-else></span>
                  <input v-if="presetForm.daily_schedules[d]"
                    v-model="presetForm.daily_schedules[d].lunch_end"
                    type="time"
                    placeholder="13:00"
                    class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                  <span v-else></span>
                  <span></span>
                </div>
              </div>
              <p v-if="presetForm.errors.daily_schedules" class="text-red-500 text-xs mt-1">{{ presetForm.errors.daily_schedules }}</p>
            </div>

            <!-- Metrics -->
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Grace (min)</label>
                <input v-model.number="presetForm.grace_period_minutes" type="number" min="0" max="60"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Late threshold (min)</label>
                <input v-model.number="presetForm.late_threshold_minutes" type="number" min="0" max="480"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Half-day (hrs)</label>
                <input v-model.number="presetForm.half_day_hours" type="number" min="1" max="8"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
              <input v-model="presetForm.remarks" type="text" placeholder="Optional notes…"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            </div>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex gap-3 justify-end shrink-0">
            <button @click="presetModal.open = false" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="submitPreset" :disabled="presetForm.processing"
              class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg transition-colors">
              {{ presetForm.processing ? 'Saving…' : 'Save Preset' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Reject Modal -->
    <Teleport to="body">
      <div v-if="rejectModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4">
          <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="text-base font-semibold text-slate-800">Reject Schedule Submission</h3>
            <p class="text-sm text-slate-500 mt-0.5">{{ rejectModal.submission?.user?.name }}</p>
          </div>
          <div class="px-6 py-4">
            <label class="block text-xs font-medium text-slate-600 mb-1">Reason (optional)</label>
            <textarea v-model="rejectForm.reason" rows="3" placeholder="e.g. Schedule conflicts with department requirements…"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 resize-none" />
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex gap-3 justify-end">
            <button @click="rejectModal.open = false" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="submitReject" :disabled="rejectForm.processing"
              class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-lg transition-colors">
              {{ rejectForm.processing ? 'Rejecting…' : 'Reject' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  PlusIcon, ClockIcon, PencilSquareIcon, TrashIcon,
  UserGroupIcon, TableCellsIcon, CheckCircleIcon, XCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  presets:             Array,
  employees:           Object,   // keyed by emp_category
  categories:          Array,
  assignments:         Array,
  pendingSubmissions:  Array,
})

const allDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

// ── Pending Submissions ───────────────────────────────────────────────────────

function approveSubmission(sub) {
  if (!confirm(`Approve the schedule submission for ${sub.user?.name}?`)) return
  useForm({}).post(route('hr.schedules.approve', sub.id))
}

const rejectModal = reactive({ open: false, submission: null })
const rejectForm  = useForm({ reason: '' })

function openReject(sub) {
  rejectModal.submission = sub
  rejectForm.reason      = ''
  rejectModal.open       = true
}

function submitReject() {
  rejectForm.post(route('hr.schedules.reject', rejectModal.submission.id), {
    onSuccess: () => { rejectModal.open = false },
  })
}

// ── Preset Modal ──────────────────────────────────────────────────────────────

const presetModal = reactive({ open: false, mode: 'add', id: null })

const defaultDailySchedules = () => ({
  Mon: { time_in: '08:00', time_out: '17:00', lunch_start: '12:00', lunch_end: '13:00' },
  Tue: { time_in: '08:00', time_out: '17:00', lunch_start: '12:00', lunch_end: '13:00' },
  Wed: { time_in: '08:00', time_out: '17:00', lunch_start: '12:00', lunch_end: '13:00' },
  Thu: { time_in: '08:00', time_out: '17:00', lunch_start: '12:00', lunch_end: '13:00' },
  Fri: { time_in: '08:00', time_out: '17:00', lunch_start: '12:00', lunch_end: '13:00' },
})

const presetForm = useForm({
  name:                   '',
  schedule_type:          'fixed',
  daily_schedules:        defaultDailySchedules(),
  grace_period_minutes:   15,
  late_threshold_minutes: 240,
  half_day_hours:         4,
  remarks:                '',
})

function toggleDay(day) {
  if (presetForm.daily_schedules[day]) {
    const updated = { ...presetForm.daily_schedules }
    delete updated[day]
    presetForm.daily_schedules = updated
  } else {
    const firstActive = Object.values(presetForm.daily_schedules)[0]
    presetForm.daily_schedules = {
      ...presetForm.daily_schedules,
      [day]: {
        time_in: firstActive?.time_in ?? '08:00',
        time_out: firstActive?.time_out ?? '17:00',
        lunch_start: firstActive?.lunch_start ?? '12:00',
        lunch_end: firstActive?.lunch_end ?? '13:00',
      },
    }
  }
}

function copyFirstToAll() {
  const first = presetForm.daily_schedules[
    allDays.find(d => presetForm.daily_schedules[d])
  ]
  if (!first) return
  const updated = {}
  for (const d of allDays) {
    if (presetForm.daily_schedules[d]) {
      updated[d] = { time_in: first.time_in, time_out: first.time_out, lunch_start: first.lunch_start, lunch_end: first.lunch_end }
    }
  }
  presetForm.daily_schedules = updated
}

function openAddPreset() {
  presetForm.reset()
  presetForm.daily_schedules        = defaultDailySchedules()
  presetForm.grace_period_minutes   = 15
  presetForm.late_threshold_minutes = 240
  presetForm.half_day_hours         = 4
  presetModal.mode = 'add'
  presetModal.id   = null
  presetModal.open = true
}

function openEditPreset(preset) {
  presetForm.name                   = preset.name
  presetForm.schedule_type          = preset.schedule_type
  presetForm.daily_schedules        = JSON.parse(JSON.stringify(preset.daily_schedules ?? defaultDailySchedules()))
  presetForm.grace_period_minutes   = preset.grace_period_minutes
  presetForm.late_threshold_minutes = preset.late_threshold_minutes
  presetForm.half_day_hours         = preset.half_day_hours
  presetForm.remarks                = preset.remarks ?? ''
  presetModal.mode = 'edit'
  presetModal.id   = preset.id
  presetModal.open = true
}

function submitPreset() {
  if (presetModal.mode === 'add') {
    presetForm.post(route('hr.schedules.presets.store'), {
      onSuccess: () => { presetModal.open = false },
    })
  } else {
    presetForm.put(route('hr.schedules.presets.update', presetModal.id), {
      onSuccess: () => { presetModal.open = false },
    })
  }
}

function deletePreset(preset) {
  if (!confirm(`Delete preset "${preset.name}"? This won't affect existing employee schedules.`)) return
  useForm({}).delete(route('hr.schedules.presets.destroy', preset.id))
}

// ── Assign ────────────────────────────────────────────────────────────────────

const selectedCategory = ref('')

const allEmployees = computed(() => Object.values(props.employees).flat())

const filteredEmployees = computed(() =>
  selectedCategory.value
    ? (props.employees[selectedCategory.value] ?? [])
    : allEmployees.value
)

const assignForm = useForm({
  preset_id:      '',
  user_ids:       [],
  effective_date: new Date().toISOString().slice(0, 10),
})

function selectAll() {
  assignForm.user_ids = filteredEmployees.value.map(e => e.id)
}

function clearAll() {
  assignForm.user_ids = []
}

function submitAssign() {
  assignForm.post(route('hr.schedules.assign'), {
    onSuccess: () => { assignForm.user_ids = [] },
  })
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatDays(days) {
  if (!days?.length) return 'Mon–Fri'
  if (days.length === 5 && !days.includes('Sat') && !days.includes('Sun')) return 'Mon–Fri'
  if (days.length === 6 && !days.includes('Sun')) return 'Mon–Sat'
  return days.join(', ')
}

function formatDaysWithTimes(dailySchedules) {
  if (!dailySchedules || !Object.keys(dailySchedules).length) return '—'
  const groups = {}
  for (const [day, t] of Object.entries(dailySchedules)) {
    const key = `${t.time_in}–${t.time_out}`
    if (!groups[key]) groups[key] = []
    groups[key].push(day)
  }
  return Object.entries(groups)
    .map(([times, days]) => `${formatDays(days)}: ${times}`)
    .join(' | ')
}

const assignmentMap = computed(() => {
  const m = {}
  for (const a of props.assignments) {
    m[a.user_id] = a.name
  }
  return m
})

function currentAssignment(userId) {
  return assignmentMap.value[userId] ?? null
}
</script>
