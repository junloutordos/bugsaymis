<template>
  <Head :title="`DTR — ${employee.name}`" />
  <AdminLayout :title="`DTR — ${employee.name}`">
    <div class="space-y-5">

      <!-- Employee Header -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 class="text-lg font-semibold text-slate-800">{{ employee.name }}</h1>
            <p class="text-sm text-slate-500">
              {{ employee.position }} — Badge: {{ employee.badge_id ?? 'N/A' }}
            </p>
          </div>
          <!-- Month navigator -->
          <div class="flex items-center gap-2">
            <button @click="changeMonth(-1)" class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50">
              <ChevronLeftIcon class="h-4 w-4 text-slate-600" />
            </button>
            <input
              v-model="currentMonth"
              type="month"
              @change="goMonth"
              class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400"
            />
            <button @click="changeMonth(1)" class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50">
              <ChevronRightIcon class="h-4 w-4 text-slate-600" />
            </button>
            <button @click="window.print()" class="ml-2 inline-flex items-center gap-1.5 px-3 py-1.5 text-sm border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-600">
              <PrinterIcon class="h-4 w-4" />
              Print
            </button>
          </div>
        </div>
      </div>

      <!-- Summary Stats -->
      <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-3 print:hidden">
        <div class="bg-emerald-50 rounded-xl border border-emerald-100 p-4 text-center">
          <p class="text-xs text-emerald-600 font-medium uppercase">Present</p>
          <p class="text-2xl font-bold text-emerald-700 mt-1">{{ summary.present }}</p>
        </div>
        <div class="bg-red-50 rounded-xl border border-red-100 p-4 text-center">
          <p class="text-xs text-red-500 font-medium uppercase">Absent</p>
          <p class="text-2xl font-bold text-red-600 mt-1">{{ summary.absent }}</p>
        </div>
        <div class="bg-amber-50 rounded-xl border border-amber-100 p-4 text-center">
          <p class="text-xs text-amber-600 font-medium uppercase">Late Days</p>
          <p class="text-2xl font-bold text-amber-700 mt-1">{{ summary.late_days }}</p>
        </div>
        <div class="bg-blue-50 rounded-xl border border-blue-100 p-4 text-center">
          <p class="text-xs text-blue-600 font-medium uppercase">On Leave</p>
          <p class="text-2xl font-bold text-blue-700 mt-1">{{ summary.on_leave }}</p>
        </div>
        <div class="bg-slate-50 rounded-xl border border-slate-200 p-4 text-center">
          <p class="text-xs text-slate-500 font-medium uppercase">Half Day</p>
          <p class="text-2xl font-bold text-slate-700 mt-1">{{ summary.half_day }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-xs text-slate-500 font-medium uppercase">Total Hrs</p>
          <p class="text-2xl font-bold text-slate-800 mt-1">{{ summary.total_hours }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-xs text-slate-500 font-medium uppercase">Late (min)</p>
          <p class="text-2xl font-bold text-amber-600 mt-1">{{ summary.total_late }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-xs text-slate-500 font-medium uppercase">Undertime</p>
          <p class="text-2xl font-bold text-orange-600 mt-1">{{ summary.total_ut }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-xs text-slate-500 font-medium uppercase">Overtime</p>
          <p class="text-2xl font-bold text-emerald-600 mt-1">{{ summary.total_ot }}</p>
        </div>
      </div>

      <!-- Calendar Grid -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">{{ monthLabel }}</h2>

        <!-- Day headers -->
        <div class="grid grid-cols-7 gap-1 mb-1">
          <div v-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d"
               class="text-center text-xs font-semibold text-slate-400 py-1">
            {{ d }}
          </div>
        </div>

        <!-- Cells -->
        <div class="grid grid-cols-7 gap-1">
          <!-- Empty padding cells for first day of month -->
          <div v-for="n in firstDayOfWeek" :key="'pad-' + n" class="min-h-[80px]"></div>

          <!-- Day cells -->
          <div
            v-for="cell in calendarCells"
            :key="cell.date"
            :class="[
              'min-h-[80px] rounded-lg border p-1.5 text-xs relative',
              cellBg(cell),
            ]"
          >
            <div class="font-semibold text-slate-600 mb-1">{{ cell.day }}</div>
            <template v-if="cell.record">
              <div class="text-[10px] font-mono text-slate-500 leading-tight">
                <div>{{ fmtTime(cell.record.time_in_am) }}</div>
                <div>{{ fmtTime(cell.record.time_out_am) }}</div>
                <div>{{ fmtTime(cell.record.time_in_pm) }}</div>
                <div>{{ fmtTime(cell.record.time_out_pm) }}</div>
              </div>
              <!-- Status band -->
              <div :class="[statusBadge(cell.record.attendance_status), 'absolute bottom-1 left-1 right-1 text-center rounded text-[9px] font-semibold py-0.5']">
                {{ cell.record.attendance_status?.replace('_', ' ') }}
              </div>
            </template>
            <div v-else-if="cell.isWorkDay" class="absolute inset-0 flex items-center justify-center text-red-300 text-[10px] font-medium rounded-lg">
              ABSENT
            </div>
          </div>
        </div>
      </div>

      <!-- Detail Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700">Detail Records</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Day</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">AM In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">AM Out</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">PM In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">PM Out</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Hrs</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Late</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">UT</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">OT</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide print:hidden">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!records.length">
                <td colspan="12" class="px-4 py-12 text-center text-slate-400 text-sm">No records for this month.</td>
              </tr>
              <tr v-for="r in records" :key="r.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-2.5 text-slate-700 whitespace-nowrap">{{ r.work_date }}</td>
                <td class="px-4 py-2.5 text-slate-500 text-xs">{{ getDayName(r.work_date) }}</td>
                <td class="px-4 py-2.5 font-mono text-slate-600 text-xs">{{ r.time_in_am ?? '—' }}</td>
                <td class="px-4 py-2.5 font-mono text-slate-600 text-xs">{{ r.time_out_am ?? '—' }}</td>
                <td class="px-4 py-2.5 font-mono text-slate-600 text-xs">{{ r.time_in_pm ?? '—' }}</td>
                <td class="px-4 py-2.5 font-mono text-slate-600 text-xs">{{ r.time_out_pm ?? '—' }}</td>
                <td class="px-4 py-2.5 text-right text-slate-700">{{ r.hours_worked ?? '—' }}</td>
                <td class="px-4 py-2.5 text-right text-xs" :class="r.late_minutes > 0 ? 'text-amber-600 font-medium' : 'text-slate-400'">{{ r.late_minutes > 0 ? r.late_minutes + 'm' : '—' }}</td>
                <td class="px-4 py-2.5 text-right text-xs" :class="r.undertime_minutes > 0 ? 'text-orange-600 font-medium' : 'text-slate-400'">{{ r.undertime_minutes > 0 ? r.undertime_minutes + 'm' : '—' }}</td>
                <td class="px-4 py-2.5 text-right text-xs" :class="r.overtime_minutes > 0 ? 'text-emerald-600 font-medium' : 'text-slate-400'">{{ r.overtime_minutes > 0 ? r.overtime_minutes + 'm' : '—' }}</td>
                <td class="px-4 py-2.5">
                  <span :class="statusBadge(r.attendance_status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium">
                    {{ r.attendance_status?.replace('_', ' ') }}
                  </span>
                </td>
                <td class="px-4 py-2.5 print:hidden">
                  <button v-if="!r.is_locked" @click="openEdit(r)" class="text-slate-400 hover:text-indigo-600">
                    <PencilSquareIcon class="h-4 w-4" />
                  </button>
                  <LockClosedIcon v-else class="h-4 w-4 text-red-300" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <Teleport to="body">
      <div v-if="editModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm print:hidden">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4">
          <h3 class="text-base font-semibold text-slate-800 mb-1">Edit DTR Record</h3>
          <p class="text-sm text-slate-400 mb-4">{{ editModal.record?.work_date }}</p>
          <div class="grid grid-cols-2 gap-3">
            <div v-for="field in ['time_in_am','time_out_am','time_in_pm','time_out_pm']" :key="field">
              <label class="block text-xs font-medium text-slate-600 mb-1">{{ field.replace(/_/g,' ').toUpperCase() }}</label>
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
import { ref, computed, reactive } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  ChevronLeftIcon,
  ChevronRightIcon,
  PrinterIcon,
  PencilSquareIcon,
  LockClosedIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  employee: Object,
  records:  Array,
  summary:  Object,
  month:    String,
})

const currentMonth = ref(props.month)

// ── Month navigation ───────────────────────────────────────────────────────
function goMonth () {
  router.get(route('hr.dtr.show', props.employee.id), { month: currentMonth.value }, { preserveState: false })
}

function changeMonth (delta) {
  const [y, m] = currentMonth.value.split('-').map(Number)
  const d = new Date(y, m - 1 + delta, 1)
  currentMonth.value = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0')
  goMonth()
}

const monthLabel = computed(() => {
  const [y, m] = currentMonth.value.split('-')
  return new Date(+y, +m - 1, 1).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })
})

// ── Calendar grid ──────────────────────────────────────────────────────────
const firstDayOfWeek = computed(() => {
  const [y, m] = currentMonth.value.split('-').map(Number)
  return new Date(y, m - 1, 1).getDay()
})

const daysInMonth = computed(() => {
  const [y, m] = currentMonth.value.split('-').map(Number)
  return new Date(y, m, 0).getDate()
})

const recordMap = computed(() => {
  const map = {}
  props.records.forEach(r => { map[r.work_date] = r })
  return map
})

const calendarCells = computed(() => {
  const cells = []
  const [y, m] = currentMonth.value.split('-').map(Number)
  for (let d = 1; d <= daysInMonth.value; d++) {
    const dateStr = `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`
    const dow = new Date(y, m - 1, d).getDay()
    cells.push({
      day:       d,
      date:      dateStr,
      record:    recordMap.value[dateStr] ?? null,
      isWorkDay: dow >= 1 && dow <= 5,
    })
  }
  return cells
})

function cellBg (cell) {
  if (! cell.record) {
    if (! cell.isWorkDay) return 'bg-slate-50 border-slate-100'
    return 'bg-red-50/40 border-red-100'
  }
  const s = cell.record.attendance_status
  if (s === 'present')    return 'bg-emerald-50/60 border-emerald-100'
  if (s === 'absent')     return 'bg-red-50 border-red-200'
  if (s === 'half_day')   return 'bg-amber-50 border-amber-200'
  if (s === 'on_leave')   return 'bg-blue-50 border-blue-100'
  if (s === 'holiday')    return 'bg-violet-50 border-violet-100'
  return 'bg-white border-slate-100'
}

function statusBadge (status) {
  return {
    present:             'bg-emerald-50 text-emerald-700',
    absent:              'bg-red-50 text-red-600',
    half_day:            'bg-amber-50 text-amber-700',
    on_leave:            'bg-blue-50 text-blue-700',
    holiday:             'bg-violet-50 text-violet-700',
    on_official_business:'bg-cyan-50 text-cyan-700',
    suspended:           'bg-slate-100 text-slate-600',
  }[status] ?? 'bg-slate-100 text-slate-600'
}

function fmtTime (t) {
  if (! t) return '—'
  return t.slice(0, 5)
}

function getDayName (dateStr) {
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-PH', { weekday: 'short' })
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
</script>

<style>
@media print {
  .print\:hidden { display: none !important; }
}
</style>
