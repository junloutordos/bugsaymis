<template>
  <Head title="Work Schedules" />
  <AdminLayout title="Work Schedules">
    <div class="space-y-6">

      <AppPageHeader title="Work Schedules" subtitle="Review employee submissions and manage schedule presets.">
        <template #actions>
          <AppButton @click="openAddPreset">
            <PlusIcon class="h-4 w-4" /> New Preset
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" /> {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.error }}
      </div>

      <!-- ── Pending Submissions ──────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-amber-200 shadow-sm">
        <div class="px-5 py-4 border-b border-amber-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <ClockIcon class="h-4 w-4 text-amber-500" /> Pending Schedule Submissions
          </h2>
          <AppBadge :color="pendingSubmissions.length ? 'amber' : 'slate'">{{ pendingSubmissions.length }} pending</AppBadge>
        </div>

        <EmptyState
          v-if="!pendingSubmissions.length"
          title="No pending submissions"
          subtitle="Employees can submit schedule requests from their My Work Schedule page." />

        <div v-else class="divide-y divide-slate-100">
          <div v-for="sub in pendingSubmissions" :key="sub.id"
            class="px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <p class="font-medium text-slate-800 text-sm">{{ sub.user?.name ?? '—' }}</p>
                <AppBadge color="slate">{{ sub.user?.emp_category ?? '—' }}</AppBadge>
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
              <AppButton size="sm" variant="success" @click="approveSubmission(sub)">
                <CheckCircleIcon class="h-3.5 w-3.5" /> Approve
              </AppButton>
              <AppButton size="sm" variant="danger" @click="openReject(sub)">
                <XCircleIcon class="h-3.5 w-3.5" /> Reject
              </AppButton>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        <!-- ── Schedule Presets ─────────────────────────────── -->
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
          <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
              <ClockIcon class="h-4 w-4 text-indigo-500" /> Schedule Presets
            </h2>
            <span class="text-xs text-slate-400">{{ presets.length }} preset(s)</span>
          </div>

          <EmptyState v-if="!presets.length" title="No presets yet" subtitle="Create one to get started." />

          <ul v-else class="divide-y divide-slate-100">
            <li v-for="p in presets" :key="p.id" class="px-5 py-4 flex items-start justify-between gap-3 hover:bg-indigo-50/40">
              <div class="min-w-0">
                <p class="font-medium text-slate-800 text-sm">{{ p.name }}</p>
                <p class="text-xs text-slate-500 mt-0.5">
                  {{ formatDaysWithTimes(p.daily_schedules) }} &bull;
                  Grace: {{ p.grace_period_minutes }}m
                </p>
                <p v-if="p.remarks" class="text-xs text-slate-400 mt-0.5 truncate max-w-xs">{{ p.remarks }}</p>
              </div>
              <div class="flex items-center gap-1 shrink-0">
                <AppIconButton label="Edit" @click="openEditPreset(p)">
                  <PencilSquareIcon class="h-4 w-4" />
                </AppIconButton>
                <AppIconButton label="Delete" variant="danger" @click="deletePreset(p)">
                  <TrashIcon class="h-4 w-4" />
                </AppIconButton>
              </div>
            </li>
          </ul>
        </div>

        <!-- ── Assign Schedule ──────────────────────────────── -->
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
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

            <AppButton
              block
              :loading="assignForm.processing"
              :disabled="assignForm.processing || !assignForm.preset_id || !assignForm.effective_date || !assignForm.user_ids.length"
              @click="submitAssign">
              {{ assignForm.processing ? 'Assigning…' : 'Assign Schedule' }}
            </AppButton>
          </div>
        </div>
      </div>

      <!-- Current Assignments Table -->
      <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <TableCellsIcon class="h-4 w-4 text-indigo-500" /> Current Assignments
          </h2>
        </div>

        <AppTable :card="false" :is-empty="!assignments.length" :skeleton-cols="5">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Employee</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Category</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Schedule</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Work Days / Times</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Effective</th>
            </tr>
          </template>

          <tr v-for="a in assignments" :key="a.id" class="hover:bg-indigo-50/40">
            <td class="px-4 py-3 font-medium text-slate-800">{{ a.user?.name ?? '—' }}</td>
            <td class="px-4 py-3 text-slate-500 text-xs">{{ a.user?.emp_category ?? '—' }}</td>
            <td class="px-4 py-3 text-slate-700">{{ a.name }}</td>
            <td class="px-4 py-3 text-slate-600 text-xs">{{ formatDaysWithTimes(a.daily_schedules) }}</td>
            <td class="px-4 py-3 text-slate-500 text-xs">{{ a.effective_date }}</td>
          </tr>

          <template #mobileCard>
            <div v-for="a in assignments" :key="a.id" class="p-4 space-y-1">
              <div class="flex items-start justify-between gap-2">
                <p class="font-medium text-slate-800 text-sm">{{ a.user?.name ?? '—' }}</p>
                <span class="text-xs text-slate-400">{{ a.user?.emp_category ?? '—' }}</span>
              </div>
              <p class="text-xs text-slate-600">{{ a.name }}</p>
              <p class="text-xs text-slate-500">{{ formatDaysWithTimes(a.daily_schedules) }}</p>
              <p class="text-xs text-slate-400">Effective {{ a.effective_date }}</p>
            </div>
          </template>

          <template #empty>
            <EmptyState title="No schedules assigned yet" />
          </template>
        </AppTable>
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
                  <span>Day</span><span>Time In</span><span>Lunch Start</span><span>Lunch End</span><span>Time Out</span><span></span>
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
                    v-model="presetForm.daily_schedules[d].lunch_start"
                    type="time"
                    placeholder="12:00"
                    class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm font-mono mr-1 focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                  <span v-else></span>
                  <input v-if="presetForm.daily_schedules[d]"
                    v-model="presetForm.daily_schedules[d].lunch_end"
                    type="time"
                    placeholder="13:00"
                    class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm font-mono mr-1 focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                  <span v-else></span>
                  <input v-if="presetForm.daily_schedules[d]"
                    v-model="presetForm.daily_schedules[d].time_out"
                    type="time"
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
            <AppButton variant="secondary" @click="presetModal.open = false">Cancel</AppButton>
            <AppButton :loading="presetForm.processing" :disabled="presetForm.processing" @click="submitPreset">
              {{ presetForm.processing ? 'Saving…' : 'Save Preset' }}
            </AppButton>
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
            <AppButton variant="secondary" @click="rejectModal.open = false">Cancel</AppButton>
            <AppButton variant="danger" :loading="rejectForm.processing" :disabled="rejectForm.processing" @click="submitReject">
              {{ rejectForm.processing ? 'Rejecting…' : 'Reject' }}
            </AppButton>
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
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmAction, confirmDelete } from '@/Composables/useConfirm.js'
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

async function approveSubmission(sub) {
  if (!(await confirmAction({ title: 'Approve schedule submission?', text: `Approve the schedule submission for ${sub.user?.name}?`, confirmText: 'Approve' }))) return
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

async function deletePreset(preset) {
  if (!(await confirmDelete(`Delete preset "${preset.name}"? This won't affect existing employee schedules.`))) return
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
