<template>
  <Head :title="`DTR — ${employee.name}`" />
  <AdminLayout :title="`DTR — ${employee.name}`">
    <div class="space-y-5">

      <!-- Employee Header -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-sm shrink-0">
              {{ initials(employee.name) }}
            </div>
            <div>
              <h1 class="text-lg font-semibold text-slate-800">{{ employee.name }}</h1>
              <p class="text-sm text-slate-500">
                {{ employee.position || 'No position' }}
                <span v-if="employee.badge_id" class="ml-1 text-slate-400">· Badge {{ employee.badge_id }}</span>
              </p>
              <p v-if="activeScheduleName" class="text-xs text-indigo-500 mt-0.5">
                Schedule: <span class="font-medium">{{ activeScheduleName }}</span>
                <span v-if="activeScheduleTimes" class="ml-1 font-mono text-indigo-400">({{ activeScheduleTimes }})</span>
              </p>
              <p v-else class="text-xs text-amber-500 mt-0.5">⚠ No schedule assigned — late & undertime will not be computed</p>
            </div>
          </div>
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
            <button @click="doRecompute" :disabled="recomputing"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition-colors font-medium disabled:opacity-50"
              title="Recompute late/undertime based on current schedule">
              <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': recomputing }" />
              {{ recomputing ? 'Recomputing…' : 'Recompute' }}
            </button>
            <button v-if="canAdvanceGenerate" @click="submitAdvanceGenerate" :disabled="advanceGenerating"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white rounded-lg transition-colors font-medium"
              title="Generate advance cut-off entry for this COS employee">
              <BoltIcon class="h-4 w-4" :class="{ 'animate-pulse': advanceGenerating }" />
              {{ advanceGenerating ? 'Generating…' : 'Advance Entry' }}
            </button>
            <a :href="route('hr.dtr.checklist', employee.id) + '?month=' + currentMonth" target="_blank"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors font-medium">
              <ClipboardDocumentListIcon class="h-4 w-4" />Checklist
            </a>
            <a :href="route('hr.dtr.print', employee.id) + '?month=' + currentMonth" target="_blank"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors font-medium">
              <PrinterIcon class="h-4 w-4" />Print
            </a>
          </div>
        </div>
      </div>

      <!-- Summary Stats -->
      <div class="grid grid-cols-3 sm:grid-cols-5 lg:grid-cols-9 gap-2 print:hidden">
        <div class="bg-emerald-50 rounded-xl border border-emerald-100 p-3 text-center">
          <p class="text-[10px] text-emerald-600 font-semibold uppercase tracking-wide">Present</p>
          <p class="text-xl font-bold text-emerald-700 mt-0.5">{{ summary.present }}</p>
        </div>
        <div class="bg-red-50 rounded-xl border border-red-100 p-3 text-center">
          <p class="text-[10px] text-red-500 font-semibold uppercase tracking-wide">Absent</p>
          <p class="text-xl font-bold text-red-600 mt-0.5">{{ summary.absent }}</p>
        </div>
        <div class="bg-amber-50 rounded-xl border border-amber-100 p-3 text-center">
          <p class="text-[10px] text-amber-600 font-semibold uppercase tracking-wide">Half Day</p>
          <p class="text-xl font-bold text-amber-700 mt-0.5">{{ summary.half_day }}</p>
        </div>
        <div class="bg-blue-50 rounded-xl border border-blue-100 p-3 text-center">
          <p class="text-[10px] text-blue-600 font-semibold uppercase tracking-wide">On Leave</p>
          <p class="text-xl font-bold text-blue-700 mt-0.5">{{ summary.on_leave }}</p>
        </div>
        <div class="bg-violet-50 rounded-xl border border-violet-100 p-3 text-center">
          <p class="text-[10px] text-violet-600 font-semibold uppercase tracking-wide">Holiday</p>
          <p class="text-xl font-bold text-violet-700 mt-0.5">{{ summary.holiday }}</p>
        </div>
        <div class="bg-rose-50 rounded-xl border border-rose-100 p-3 text-center">
          <p class="text-[10px] text-rose-600 font-semibold uppercase tracking-wide">WFH</p>
          <p class="text-xl font-bold text-rose-700 mt-0.5">{{ summary.wfh ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-3 text-center">
          <p class="text-[10px] text-slate-500 font-semibold uppercase tracking-wide">Total Hrs</p>
          <p class="text-xl font-bold text-slate-800 mt-0.5">{{ summary.total_hours }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-3 text-center">
          <p class="text-[10px] text-amber-500 font-semibold uppercase tracking-wide">Late (m)</p>
          <p class="text-xl font-bold text-amber-600 mt-0.5">{{ summary.total_late }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-3 text-center">
          <p class="text-[10px] text-orange-500 font-semibold uppercase tracking-wide">Undertime</p>
          <p class="text-xl font-bold text-orange-600 mt-0.5">{{ summary.total_ut }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-3 text-center">
          <p class="text-[10px] text-emerald-500 font-semibold uppercase tracking-wide">Overtime</p>
          <p class="text-xl font-bold text-emerald-600 mt-0.5">{{ summary.total_ot }}</p>
        </div>
      </div>

      <!-- Calendar Grid -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">{{ monthLabel }}</h2>

        <!-- Day headers -->
        <div class="grid grid-cols-7 mb-1">
          <div v-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d"
               class="text-center text-[11px] font-semibold text-slate-400 py-1.5">{{ d }}</div>
        </div>

        <!-- Cells -->
        <div class="grid grid-cols-7 gap-1">
          <div v-for="n in firstDayOfWeek" :key="'pad-'+n" class="min-h-[76px]"></div>

          <div
            v-for="cell in calendarCells"
            :key="cell.date"
            :class="['min-h-[76px] rounded-lg border p-1.5 text-xs relative flex flex-col', cellBg(cell)]"
          >
            <!-- Day number -->
            <span :class="['text-[11px] font-bold leading-none mb-1', cell.isToday ? 'text-indigo-600' : 'text-slate-500']">
              {{ cell.day }}
              <span v-if="cell.isToday" class="ml-0.5 inline-block h-1.5 w-1.5 rounded-full bg-indigo-500 align-middle"></span>
            </span>

            <template v-if="cell.record">
              <!-- Time punches -->
              <div :class="['font-mono text-[9px] leading-[1.4] space-y-px flex-1', cell.record.wfh_attendance_id ? 'text-rose-500' : 'text-slate-500']">
                <div v-if="cell.record.time_in_am" class="flex gap-1">
                  <span class="text-slate-400">in</span>{{ fmtTime(cell.record.time_in_am) }}
                </div>
                <div v-if="cell.record.time_out_am" class="flex gap-1">
                  <span class="text-slate-400">out</span>{{ fmtTime(cell.record.time_out_am) }}
                </div>
                <div v-if="cell.record.time_in_pm" class="flex gap-1">
                  <span class="text-slate-400">in</span>{{ fmtTime(cell.record.time_in_pm) }}
                </div>
                <div v-if="cell.record.time_out_pm" class="flex gap-1">
                  <span class="text-slate-400">out</span>{{ fmtTime(cell.record.time_out_pm) }}
                </div>
              </div>
              <!-- Scheduled time (indigo, below punches) -->
              <div v-if="cell.schedIn || cell.schedOut"
                class="font-mono text-[8px] text-indigo-400 leading-tight border-t border-indigo-100 mt-0.5 pt-0.5 text-center">
                {{ fmtTime(cell.schedIn) }}{{ cell.schedIn && cell.schedOut ? '–' : '' }}{{ fmtTime(cell.schedOut) }}
              </div>

              <!-- Status badge -->
              <div :class="[statusBadge(cell.record.attendance_status), 'mt-1 text-center rounded text-[8px] font-bold py-0.5 px-1 uppercase tracking-wide']">
                {{ statusLabel(cell.record.attendance_status) }}
              </div>

              <!-- Late / UT mini chips -->
              <div v-if="cell.record.late_minutes > 0 || cell.record.undertime_minutes > 0"
                class="flex gap-0.5 mt-0.5 justify-center flex-wrap">
                <span v-if="cell.record.late_minutes > 0"
                  class="text-[7px] font-bold bg-amber-100 text-amber-700 rounded px-1 leading-tight">
                  L {{ fmtMinutes(cell.record.late_minutes) }}
                </span>
                <span v-if="cell.record.undertime_minutes > 0"
                  class="text-[7px] font-bold bg-orange-100 text-orange-700 rounded px-1 leading-tight">
                  UT {{ fmtMinutes(cell.record.undertime_minutes) }}
                </span>
              </div>
            </template>

            <!-- No record: rest day -->
            <template v-else-if="!cell.isWorkDay">
              <span class="text-[9px] text-slate-300 mt-auto text-center">rest</span>
            </template>

            <!-- No record: work day = absent -->
            <template v-else>
              <span class="text-[9px] font-bold text-red-300 mt-auto text-center uppercase tracking-wide">absent</span>
            </template>
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
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Day</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">AM In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">AM Out</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">PM In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">PM Out</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-indigo-400 uppercase tracking-wide whitespace-nowrap">Sched. In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-indigo-400 uppercase tracking-wide whitespace-nowrap">Sched. Out</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Hrs</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-amber-500 uppercase tracking-wide">Late</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-orange-500 uppercase tracking-wide">UT</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-emerald-500 uppercase tracking-wide">OT</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                <th class="px-4 py-3 print:hidden"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!records.length">
                <td colspan="14" class="px-4 py-12 text-center text-slate-400 text-sm">No records for this month.</td>
              </tr>
              <tr v-for="r in records" :key="r.id" :class="r.is_advance ? 'bg-amber-50/60 hover:bg-amber-50' : 'hover:bg-slate-50/60'">
                <td class="px-4 py-2.5 text-slate-700 whitespace-nowrap text-xs">{{ toDateStr(r.work_date) }}</td>
                <td class="px-4 py-2.5 text-slate-500 text-xs font-medium">{{ getDayName(r.work_date) }}</td>
                <td v-for="f in ['time_in_am','time_out_am','time_in_pm','time_out_pm']" :key="f"
                    class="px-4 py-2.5 font-mono text-xs whitespace-nowrap"
                    :class="r.wfh_attendance_id && r[f] ? 'text-rose-600 font-medium' : (r[f] ? 'text-slate-700' : (r['penned_'+f] ? 'text-amber-700 font-semibold' : (r.attendance_status === 'on_leave' ? 'text-amber-600 font-bold' : (r.attendance_status === 'on_official_business' ? 'text-blue-500 font-bold' : 'text-slate-200'))))">
                  {{ timeCell(r, f) }}
                </td>
                <!-- Scheduled times (indigo, derived from employee schedule) -->
                <td class="px-4 py-2.5 font-mono text-xs whitespace-nowrap text-indigo-400">
                  {{ fmtTime(r.scheduled_time_in) || '–' }}
                </td>
                <td class="px-4 py-2.5 font-mono text-xs whitespace-nowrap text-indigo-400">
                  {{ fmtTime(r.scheduled_time_out) || '–' }}
                </td>
                <td class="px-4 py-2.5 text-right text-slate-700 text-xs tabular-nums">{{ r.hours_worked > 0 ? r.hours_worked : '—' }}</td>
                <!-- Late -->
                <td class="px-4 py-2.5 text-right text-xs tabular-nums" :class="r.late_minutes > 0 ? 'text-amber-600 font-semibold' : 'text-slate-300'">
                  <span v-if="!r.schedule_name" title="No schedule — late cannot be computed" class="cursor-help text-slate-200">n/a</span>
                  <span v-else>{{ fmtMinutes(r.late_minutes) }}</span>
                </td>
                <!-- Undertime -->
                <td class="px-4 py-2.5 text-right text-xs tabular-nums" :class="r.undertime_minutes > 0 ? 'text-orange-600 font-semibold' : 'text-slate-300'">
                  <span v-if="!r.schedule_name" title="No schedule — undertime cannot be computed" class="cursor-help text-slate-200">n/a</span>
                  <span v-else>{{ fmtMinutes(r.undertime_minutes) }}</span>
                </td>
                <!-- Overtime -->
                <td class="px-4 py-2.5 text-right text-xs tabular-nums" :class="r.overtime_minutes > 0 ? 'text-emerald-600 font-semibold' : 'text-slate-300'">
                  {{ fmtMinutes(r.overtime_minutes) }}
                </td>
                <td class="px-4 py-2.5">
                  <div class="flex items-center gap-1 flex-wrap">
                    <span :class="statusBadge(r.attendance_status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium whitespace-nowrap">
                      {{ statusLabel(r.attendance_status) }}
                    </span>
                    <span v-if="r.wfh_attendance_id"
                      class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-600 whitespace-nowrap"
                      title="Times sourced from WFH attendance log">WFH</span>
                    <span v-if="r.is_advance"
                      class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 whitespace-nowrap"
                      title="Advance entry — cut-off date, pending biometric confirmation">Advance</span>
                  </div>
                </td>
                <td class="px-4 py-2.5 print:hidden">
                  <button v-if="!r.is_locked && (isAdmin || canManageDtr)" @click="openEdit(r)" class="text-slate-300 hover:text-indigo-600 transition-colors">
                    <PencilSquareIcon class="h-4 w-4" />
                  </button>
                  <LockClosedIcon v-else-if="r.is_locked" class="h-4 w-4 text-red-300" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- Edit / Penned Entry Modal -->
    <Teleport to="body">
      <div v-if="editModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm print:hidden">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4">
          <h3 class="text-base font-semibold text-slate-800 mb-0.5">DTR Record</h3>
          <p class="text-sm text-slate-400 mb-1">{{ toDateStr(editModal.record?.work_date) }} — {{ getDayName(editModal.record?.work_date) }}</p>

          <!-- Admin mode: all fields editable, no red styling -->
          <template v-if="isAdmin">
            <p class="text-[11px] text-slate-400 mb-4">Edit time entries directly. Changes are saved as official records.</p>
            <div class="grid grid-cols-2 gap-3">
              <div v-for="field in ['time_in_am','time_out_am','time_in_pm','time_out_pm']" :key="field">
                <label class="block text-xs font-medium text-slate-600 mb-1">{{ fieldLabel(field) }}</label>
                <input
                  v-model="editForm[field]"
                  type="time"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
              <div class="col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
                <input v-model="editForm.remarks" type="text" placeholder="Reason for edit…"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
            </div>
          </template>

          <!-- Employee mode: biometric slots read-only, empty slots accept penned entry -->
          <template v-else>
            <p class="text-[11px] text-slate-400 mb-4">Biometric punches are read-only. Empty slots accept a <span class="text-amber-600 font-medium">penned entry</span>.</p>
            <div class="grid grid-cols-2 gap-3">
              <div v-for="field in ['time_in_am','time_out_am','time_in_pm','time_out_pm']" :key="field">
                <label class="block text-xs font-medium mb-1"
                  :class="editModal.record?.[field] ? 'text-slate-500' : 'text-amber-600'">
                  {{ fieldLabel(field) }}
                  <span v-if="editModal.record?.[field]" class="font-normal text-slate-400">(biometric)</span>
                  <span v-else class="font-normal text-amber-500">(penned)</span>
                </label>
                <!-- Biometric value: read-only display -->
                <div v-if="editModal.record?.[field]"
                  class="w-full border border-slate-100 bg-slate-50 rounded-lg px-3 py-2 text-sm font-mono text-slate-400 select-none">
                  {{ fmtTime(editModal.record[field]) }}
                </div>
                <!-- Empty slot: penned entry input -->
                <input v-else
                  v-model="editForm['penned_' + field]"
                  type="time"
                  class="w-full border border-amber-200 rounded-lg px-3 py-2 text-sm font-mono text-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-400" />
              </div>
              <div class="col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
                <input v-model="editForm.penned_remarks" type="text" placeholder="Reason for penned entry…"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400" />
              </div>
            </div>
          </template>

          <div class="flex gap-3 justify-end mt-5">
            <button @click="editModal.open = false" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="submitEdit" :disabled="editForm.processing"
              class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg transition-colors">
              {{ editForm.processing ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  ChevronLeftIcon, ChevronRightIcon, PrinterIcon,
  PencilSquareIcon, LockClosedIcon, ArrowPathIcon,
  ClipboardDocumentListIcon, BoltIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  employee: Object,
  records:  Array,
  summary:  Object,
  month:    String,
})

const page = usePage()
const isAdmin = computed(() =>
  page.props.auth?.user?.roleNames?.includes('Administrator') ?? false
)
const canManageDtr = computed(() =>
  page.props.auth?.user?.permissions?.includes('hr.dtr.manage') ?? false
)

const isCos = computed(() =>
  ['COS Teaching', 'COS Non Teaching'].includes(props.employee?.emp_category)
)

const isCurrentMonth = computed(() => {
  const n = new Date()
  const curr = `${n.getFullYear()}-${String(n.getMonth()+1).padStart(2,'0')}`
  return currentMonth.value === curr
})

const tomorrowStr = computed(() => {
  const t = new Date()
  t.setDate(t.getDate() + 1)
  return `${t.getFullYear()}-${String(t.getMonth()+1).padStart(2,'0')}-${String(t.getDate()).padStart(2,'0')}`
})

const hasAdvanceRecord = computed(() => props.records.some(r => r.is_advance))

const canAdvanceGenerate = computed(() =>
  isCos.value && isCurrentMonth.value && !hasAdvanceRecord.value && (isAdmin.value || canManageDtr.value)
)

const advanceGenerating = ref(false)

function submitAdvanceGenerate() {
  const [y, m] = currentMonth.value.split('-')
  advanceGenerating.value = true
  router.post(route('hr.dtr.generate'), {
    user_id:   props.employee.id,
    category:  'single',
    date_from: `${y}-${m}-01`,
    date_to:   tomorrowStr.value,
  }, {
    onFinish: () => { advanceGenerating.value = false },
  })
}

const currentMonth = ref(props.month)

// ── Helpers ────────────────────────────────────────────────────────────────

/** Normalize any date value (ISO string, YYYY-MM-DD, etc.) to YYYY-MM-DD */
function toDateStr(val) {
  if (!val) return ''
  return String(val).slice(0, 10)
}

function initials(name) {
  return (name ?? '').split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase()
}

function fmtTime(t) {
  if (!t) return ''
  return String(t).slice(0, 5)
}

function timeCell(record, field) {
  const bio    = record[field]
  const penned = record['penned_' + field]
  if (bio || penned) return fmtTime(bio || penned)
  const s = record.attendance_status
  if (s === 'on_leave')             return 'L'
  if (s === 'on_official_business') return 'OB'
  return '–'
}

function fmtMinutes(m) {
  const n = Math.round(Number(m) || 0)
  if (n <= 0) return '—'
  const h = Math.floor(n / 60)
  const r = n % 60
  if (h > 0 && r > 0) return `${h}h ${r}m`
  if (h > 0)           return `${h}h`
  return `${r}m`
}

function getDayName(dateVal) {
  const d = toDateStr(dateVal)
  if (!d) return ''
  // Append T00:00:00 only if it's a plain date string to avoid TZ shift
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { weekday: 'short' })
}

// ── Month navigation ───────────────────────────────────────────────────────

function goMonth() {
  router.get(route('hr.dtr.show', props.employee.id), { month: currentMonth.value }, { preserveState: false })
}

function changeMonth(delta) {
  const [y, m] = currentMonth.value.split('-').map(Number)
  const d = new Date(y, m - 1 + delta, 1)
  currentMonth.value = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0')
  goMonth()
}

const monthLabel = computed(() => {
  const [y, m] = currentMonth.value.split('-')
  return new Date(+y, +m - 1, 1).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })
})

// Derive active schedule name from the most recent record that has one
const activeScheduleName = computed(() => {
  const rec = [...props.records].reverse().find(r => r.schedule_name)
  return rec?.schedule_name ?? null
})

const activeScheduleTimes = computed(() => {
  const rec = [...props.records].reverse().find(r => r.scheduled_time_in || r.scheduled_time_out)
  if (!rec) return null
  const i = fmtTime(rec.scheduled_time_in)
  const o = fmtTime(rec.scheduled_time_out)
  if (i && o) return `${i} – ${o}`
  return i || o || null
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

/** Key records by normalized YYYY-MM-DD */
const recordMap = computed(() => {
  const map = {}
  props.records.forEach(r => { map[toDateStr(r.work_date)] = r })
  return map
})

const now = new Date()
const todayStr = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`

const calendarCells = computed(() => {
  const cells = []
  const [y, m] = currentMonth.value.split('-').map(Number)
  for (let d = 1; d <= daysInMonth.value; d++) {
    const dateStr = `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`
    const dow = new Date(y, m - 1, d).getDay()
    const rec = recordMap.value[dateStr] ?? null
    cells.push({
      day:       d,
      date:      dateStr,
      record:    rec,
      // A day is a work day if a DTR record was generated for it;
      // fall back to Mon-Fri for days with no record yet.
      isWorkDay: rec ? rec.day_type !== 'rest_day' : (dow >= 1 && dow <= 5),
      isToday:   dateStr === todayStr,
      schedIn:   rec?.scheduled_time_in  ?? null,
      schedOut:  rec?.scheduled_time_out ?? null,
    })
  }
  return cells
})

function cellBg(cell) {
  if (!cell.record) {
    if (!cell.isWorkDay) return 'bg-slate-50 border-slate-100'
    return 'bg-red-50/30 border-red-100'
  }
  if (cell.record.is_advance) return 'bg-amber-50 border-amber-300'
  const s = cell.record.attendance_status
  if (s === 'present')    return 'bg-emerald-50/70 border-emerald-100'
  if (s === 'absent')     return 'bg-red-50 border-red-200'
  if (s === 'half_day')   return 'bg-amber-50 border-amber-200'
  if (s === 'on_leave')   return 'bg-blue-50 border-blue-100'
  if (s === 'holiday')    return 'bg-violet-50 border-violet-100'
  if (s === 'wfh')        return 'bg-rose-50/70 border-rose-100'
  return 'bg-white border-slate-100'
}

function statusBadge(status) {
  return {
    present:              'bg-emerald-100 text-emerald-700',
    absent:               'bg-red-100 text-red-600',
    half_day:             'bg-amber-100 text-amber-700',
    on_leave:             'bg-blue-100 text-blue-700',
    holiday:              'bg-violet-100 text-violet-700',
    on_official_business: 'bg-cyan-100 text-cyan-700',
    wfh:                  'bg-rose-100 text-rose-700',
  }[status] ?? 'bg-slate-100 text-slate-600'
}

function statusLabel(status) {
  return {
    present:              'Present',
    absent:               'Absent',
    half_day:             'Half Day',
    on_leave:             'On Leave',
    holiday:              'Holiday',
    on_official_business: 'OB',
    wfh:                  'WFH',
  }[status] ?? (status ?? '').replace(/_/g, ' ')
}

// ── Helpers ────────────────────────────────────────────────────────────────

function fieldLabel(field) {
  return { time_in_am: 'AM In', time_out_am: 'AM Out', time_in_pm: 'PM In', time_out_pm: 'PM Out' }[field] ?? field
}

// ── Edit / Penned entry ─────────────────────────────────────────────────────

// ── Recompute ───────────────────────────────────────────────────────────────

const recomputing = ref(false)

function doRecompute() {
  recomputing.value = true
  router.post(
    route('hr.dtr.recompute', props.employee.id),
    { month: currentMonth.value },
    {
      onFinish: () => { recomputing.value = false },
      preserveScroll: true,
    }
  )
}

// ── Edit / Penned entry ─────────────────────────────────────────────────────

const editModal = reactive({ open: false, record: null })
const editForm  = useForm({
  // Admin direct edit fields
  time_in_am:  '', time_out_am:  '',
  time_in_pm:  '', time_out_pm:  '',
  remarks:     '',
  // Employee penned entry fields
  penned_time_in_am: '', penned_time_out_am: '',
  penned_time_in_pm: '', penned_time_out_pm: '',
  penned_remarks: '',
})

function openEdit(record) {
  editModal.record = record
  editModal.open   = true
  const p = (val) => (fmtTime(val) || '')
  if (isAdmin.value) {
    // Admin: pre-fill with current biometric values (all editable)
    editForm.time_in_am  = p(record.time_in_am)
    editForm.time_out_am = p(record.time_out_am)
    editForm.time_in_pm  = p(record.time_in_pm)
    editForm.time_out_pm = p(record.time_out_pm)
    editForm.remarks     = record.remarks ?? ''
  } else {
    // Employee: pre-fill penned fields only
    editForm.penned_time_in_am  = p(record.penned_time_in_am)
    editForm.penned_time_out_am = p(record.penned_time_out_am)
    editForm.penned_time_in_pm  = p(record.penned_time_in_pm)
    editForm.penned_time_out_pm = p(record.penned_time_out_pm)
    editForm.penned_remarks     = record.penned_remarks ?? ''
  }
}

function submitEdit() {
  if (isAdmin.value) {
    // Admin: write directly to biometric fields via dtr.edit
    editForm
      .transform(data => ({
        time_in_am:  data.time_in_am  ? data.time_in_am  + ':00' : null,
        time_out_am: data.time_out_am ? data.time_out_am + ':00' : null,
        time_in_pm:  data.time_in_pm  ? data.time_in_pm  + ':00' : null,
        time_out_pm: data.time_out_pm ? data.time_out_pm + ':00' : null,
        remarks:     data.remarks || null,
      }))
      .patch(route('hr.dtr.edit', editModal.record.id), {
        onSuccess: () => { editModal.open = false },
      })
  } else {
    // Employee: write to penned fields via dtr.penned
    editForm.patch(route('hr.dtr.penned', editModal.record.id), {
      onSuccess: () => { editModal.open = false },
    })
  }
}
</script>

<style>
@media print {
  .print\:hidden { display: none !important; }
}
</style>
