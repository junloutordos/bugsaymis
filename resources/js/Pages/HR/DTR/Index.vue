<template>
  <Head title="DTR Records" />
  <AdminLayout title="DTR Records">
    <div class="space-y-5">

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Daily Time Records</h1>
          <p class="text-sm text-slate-500 mt-0.5">View, edit, and manage employee attendance records.</p>
        </div>
        <button
          @click="generateModal = true"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors shadow-sm"
        >
          <Cog6ToothIcon class="h-4 w-4" />
          Generate DTR
        </button>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.error }}
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[200px]">
          <label class="block text-xs font-medium text-slate-500 mb-1">Employee</label>
          <select v-model="filters.user_id" @change="applyFilters" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">All Employees</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Month</label>
          <input v-model="filters.month" type="month" @change="applyFilters" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
          <select v-model="filters.status" @change="applyFilters" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">All Statuses</option>
            <option value="present">Present</option>
            <option value="absent">Absent</option>
            <option value="half_day">Half Day</option>
            <option value="on_leave">On Leave</option>
            <option value="holiday">Holiday</option>
            <option value="on_official_business">Official Business</option>
          </select>
        </div>
        <button @click="applyFilters" class="bg-slate-700 hover:bg-slate-800 text-white text-sm px-4 py-2 rounded-lg transition-colors">Filter</button>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Day Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">AM In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">AM Out</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">PM In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">PM Out</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Hrs</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Late</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">UT</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">OT</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!records.data?.length">
                <td colspan="13" class="px-4 py-12 text-center text-slate-400 text-sm">No DTR records found. Generate DTR from biometric logs.</td>
              </tr>
              <tr v-for="r in records.data" :key="r.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-800">{{ r.user?.name }}</div>
                  <div class="text-xs text-slate-400">{{ r.user?.badge_id }}</div>
                </td>
                <td class="px-4 py-3 text-slate-700 whitespace-nowrap">{{ r.work_date }}</td>
                <td class="px-4 py-3">
                  <span :class="dayTypeBadge(r.day_type)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium">
                    {{ dayTypeLabel(r.day_type) }}
                  </span>
                </td>
                <td class="px-4 py-3 font-mono text-slate-600 text-xs">{{ r.time_in_am ?? '—' }}</td>
                <td class="px-4 py-3 font-mono text-slate-600 text-xs">{{ r.time_out_am ?? '—' }}</td>
                <td class="px-4 py-3 font-mono text-slate-600 text-xs">{{ r.time_in_pm ?? '—' }}</td>
                <td class="px-4 py-3 font-mono text-slate-600 text-xs">{{ r.time_out_pm ?? '—' }}</td>
                <td class="px-4 py-3 text-right text-slate-700">{{ r.hours_worked ?? '—' }}</td>
                <td class="px-4 py-3 text-right" :class="r.late_minutes > 0 ? 'text-amber-600 font-medium' : 'text-slate-400'">{{ r.late_minutes > 0 ? r.late_minutes + 'm' : '—' }}</td>
                <td class="px-4 py-3 text-right" :class="r.undertime_minutes > 0 ? 'text-orange-600 font-medium' : 'text-slate-400'">{{ r.undertime_minutes > 0 ? r.undertime_minutes + 'm' : '—' }}</td>
                <td class="px-4 py-3 text-right" :class="r.overtime_minutes > 0 ? 'text-emerald-600 font-medium' : 'text-slate-400'">{{ r.overtime_minutes > 0 ? r.overtime_minutes + 'm' : '—' }}</td>
                <td class="px-4 py-3">
                  <span :class="statusBadgeClass(r.attendance_status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium">
                    {{ r.attendance_status?.replace('_', ' ') }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <button @click="$inertia.visit(route('hr.dtr.show', r.user_id) + '?month=' + r.work_date.slice(0, 7))" class="text-slate-400 hover:text-indigo-600" title="View Monthly DTR">
                      <EyeIcon class="h-4 w-4" />
                    </button>
                    <button v-if="!r.is_locked" @click="openEdit(r)" class="text-slate-400 hover:text-amber-600" title="Edit">
                      <PencilSquareIcon class="h-4 w-4" />
                    </button>
                    <button @click="toggleLock(r)" :title="r.is_locked ? 'Unlock' : 'Lock'" :class="r.is_locked ? 'text-red-400 hover:text-red-600' : 'text-slate-300 hover:text-slate-500'">
                      <LockClosedIcon class="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ records.current_page }} of {{ records.last_page }}</span>
          <div class="flex gap-2">
            <button @click="goToPage(records.prev_page_url)" :disabled="!records.prev_page_url" class="px-3 py-1.5 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50">Prev</button>
            <button @click="goToPage(records.next_page_url)" :disabled="!records.next_page_url" class="px-3 py-1.5 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50">Next</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Generate DTR Modal -->
    <Teleport to="body">
      <div v-if="generateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md mx-4">
          <h3 class="text-base font-semibold text-slate-800 mb-4">Generate DTR Records</h3>
          <div class="space-y-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Employee <span class="font-normal text-slate-400">(leave blank for all)</span></label>
              <select v-model="genForm.user_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="">All active employees</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date From</label>
                <input v-model="genForm.date_from" type="date" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date To</label>
                <input v-model="genForm.date_to" type="date" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
            </div>
          </div>
          <div class="flex gap-3 justify-end mt-5">
            <button @click="generateModal = false" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="submitGenerate" :disabled="genForm.processing || !genForm.date_from || !genForm.date_to" class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg transition-colors">
              {{ genForm.processing ? 'Queueing…' : 'Generate' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Edit Modal -->
    <Teleport to="body">
      <div v-if="editModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4">
          <h3 class="text-base font-semibold text-slate-800 mb-1">Edit DTR Record</h3>
          <p class="text-sm text-slate-400 mb-4">{{ editModal.record?.user?.name }} — {{ editModal.record?.work_date }}</p>
          <div class="grid grid-cols-2 gap-3">
            <div v-for="field in ['time_in_am','time_out_am','time_in_pm','time_out_pm']" :key="field">
              <label class="block text-xs font-medium text-slate-600 mb-1">{{ field.replace(/_/g, ' ').toUpperCase() }}</label>
              <input v-model="editForm[field]" type="time" step="1" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            </div>
            <div class="col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
              <input v-model="editForm.remarks" type="text" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            </div>
          </div>
          <div class="flex gap-3 justify-end mt-5">
            <button @click="editModal.open = false" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="submitEdit" :disabled="editForm.processing" class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg transition-colors">Save</button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { statusBadgeClass } from '@/Composables/useStatusBadge.js'
import {
  EyeIcon,
  PencilSquareIcon,
  LockClosedIcon,
  Cog6ToothIcon,
  CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  records: Object,
  users:   Array,
  filters: Object,
})

// ── Filters ────────────────────────────────────────────────────────────────
const filters = reactive({ ...props.filters })

function applyFilters () {
  router.get(route('hr.dtr.index'), filters, { preserveState: true, replace: true })
}

function goToPage (url) {
  if (url) router.visit(url, { preserveState: true })
}

// ── Generate DTR ───────────────────────────────────────────────────────────
const generateModal = ref(false)
const genForm = useForm({ user_id: '', date_from: '', date_to: '' })

function submitGenerate () {
  genForm.post(route('hr.dtr.generate'), {
    onSuccess: () => { generateModal.value = false; genForm.reset() },
  })
}

// ── Edit ───────────────────────────────────────────────────────────────────
const editModal = reactive({ open: false, record: null })
const editForm  = useForm({ time_in_am: '', time_out_am: '', time_in_pm: '', time_out_pm: '', remarks: '' })

function openEdit (record) {
  editModal.record = record
  editModal.open   = true
  editForm.time_in_am  = record.time_in_am  ?? ''
  editForm.time_out_am = record.time_out_am ?? ''
  editForm.time_in_pm  = record.time_in_pm  ?? ''
  editForm.time_out_pm = record.time_out_pm ?? ''
  editForm.remarks     = record.remarks ?? ''
}

function submitEdit () {
  editForm.patch(route('hr.dtr.edit', editModal.record.id), {
    onSuccess: () => { editModal.open = false },
  })
}

function toggleLock (record) {
  router.patch(route('hr.dtr.lock', record.id))
}

// ── Badge helpers ──────────────────────────────────────────────────────────
function dayTypeBadge (type) {
  return {
    regular:          'bg-slate-100 text-slate-600',
    holiday_regular:  'bg-red-50 text-red-600',
    holiday_special:  'bg-orange-50 text-orange-700',
    rest_day:         'bg-gray-50 text-gray-500',
    rest_day_holiday: 'bg-purple-50 text-purple-700',
  }[type] ?? 'bg-slate-100 text-slate-600'
}

function dayTypeLabel (type) {
  return {
    regular:          'Regular',
    holiday_regular:  'Reg. Holiday',
    holiday_special:  'Sp. Holiday',
    rest_day:         'Rest Day',
    rest_day_holiday: 'Rest+Holiday',
  }[type] ?? type
}
</script>
