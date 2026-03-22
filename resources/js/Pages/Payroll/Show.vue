<template>
  <Head :title="`Payroll — ${run.control_no}`" />
  <AdminLayout :title="`Payroll Run ${run.control_no}`">
    <div class="space-y-5">

      <!-- Run Header Card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
          <div class="space-y-1">
            <div class="flex items-center gap-3">
              <h1 class="text-lg font-semibold text-slate-800">{{ run.control_no }}</h1>
              <span :class="statusBadgeClass(run.status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold">
                {{ run.status.replace('_', ' ') }}
              </span>
            </div>
            <p class="text-sm text-slate-500">
              {{ monthName(run.month) }} {{ run.year }} — {{ run.period }} half
              &bull; {{ run.period_start }} to {{ run.period_end }}
            </p>
            <p v-if="run.pay_date" class="text-xs text-slate-400">Pay date: {{ run.pay_date }}</p>
          </div>

          <!-- Action buttons -->
          <div class="flex flex-wrap gap-2 shrink-0">
            <button
              v-if="run.status === 'draft' || run.status === 'for_review'"
              @click="processRun"
              class="inline-flex items-center gap-1.5 px-4 py-2 text-sm bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition-colors font-medium"
            >
              <Cog6ToothIcon class="h-4 w-4" />
              {{ run.status === 'for_review' ? 'Re-process' : 'Process' }}
            </button>
            <button
              v-if="run.status === 'for_review'"
              @click="approveModal = true"
              class="inline-flex items-center gap-1.5 px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors font-medium"
            >
              <CheckIcon class="h-4 w-4" />Approve
            </button>
            <button
              v-if="run.status === 'approved'"
              @click="releaseRun"
              class="inline-flex items-center gap-1.5 px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium"
            >
              <PaperAirplaneIcon class="h-4 w-4" />Release
            </button>
            <button
              v-if="!['released','cancelled'].includes(run.status)"
              @click="cancelRun"
              class="inline-flex items-center gap-1.5 px-4 py-2 text-sm border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-lg transition-colors"
            >
              Cancel Run
            </button>

            <!-- Reports dropdown -->
            <div class="relative" ref="reportsMenu">
              <button
                @click="reportsOpen = !reportsOpen"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-lg transition-colors"
              >
                <DocumentArrowDownIcon class="h-4 w-4" />Reports
                <ChevronDownIcon class="h-3 w-3" />
              </button>
              <div v-if="reportsOpen" class="absolute right-0 mt-1 w-52 bg-white rounded-xl shadow-lg border border-slate-100 py-1 z-20">
                <a
                  :href="route('payroll.reports.register', run.id)"
                  target="_blank"
                  class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                  @click="reportsOpen = false"
                >
                  <DocumentTextIcon class="h-4 w-4 text-slate-400" />Payroll Register
                </a>
                <a
                  :href="route('payroll.reports.deductions', run.id)"
                  target="_blank"
                  class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                  @click="reportsOpen = false"
                >
                  <DocumentTextIcon class="h-4 w-4 text-slate-400" />Deductions Register
                </a>
                <hr class="my-1 border-slate-100">
                <button
                  v-if="['for_review','approved','released'].includes(run.status)"
                  @click="downloadZip"
                  class="flex items-center gap-2 w-full px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 text-left"
                >
                  <ArchiveBoxArrowDownIcon class="h-4 w-4 text-slate-400" />All Payslips (ZIP)
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.error }}
      </div>

      <!-- Summary Strip -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
          <p class="text-xs text-slate-500 font-medium uppercase">Employees</p>
          <p class="text-2xl font-bold text-slate-800 mt-1">{{ run.employee_count }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
          <p class="text-xs text-slate-500 font-medium uppercase">Gross Pay</p>
          <p class="text-lg font-bold text-slate-800 mt-1">{{ fmt(run.total_gross) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
          <p class="text-xs text-slate-500 font-medium uppercase">Deductions</p>
          <p class="text-lg font-bold text-red-600 mt-1">{{ fmt(run.total_deductions) }}</p>
        </div>
        <div class="bg-emerald-50 rounded-xl border border-emerald-100 p-4">
          <p class="text-xs text-emerald-600 font-medium uppercase">Net Pay</p>
          <p class="text-lg font-bold text-emerald-700 mt-1">{{ fmt(run.total_net) }}</p>
        </div>
      </div>

      <!-- Records Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700">Employee Records</h2>
          <span class="text-xs text-slate-400">{{ records.total }} records</span>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">SG / Step</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Monthly Salary</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Basic Pay</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Gross Pay</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Deductions</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">WHT</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Net Pay</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Details</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!records.data?.length">
                <td colspan="10" class="px-4 py-12 text-center text-slate-400 text-sm">
                  No records yet.
                  <span v-if="run.status === 'draft'">Click "Process" to compute payroll.</span>
                </td>
              </tr>
              <tr v-for="rec in records.data" :key="rec.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-800">{{ rec.user?.name }}</div>
                  <div class="text-xs text-slate-400">{{ rec.user?.badge_id }}</div>
                </td>
                <td class="px-4 py-3 text-slate-600 text-xs">SG {{ rec.salary_grade }}-{{ rec.step }}</td>
                <td class="px-4 py-3 text-right text-slate-600">{{ fmt(rec.monthly_salary) }}</td>
                <td class="px-4 py-3 text-right text-slate-700">{{ fmt(rec.basic_pay) }}</td>
                <td class="px-4 py-3 text-right text-slate-700">{{ fmt(rec.gross_pay) }}</td>
                <td class="px-4 py-3 text-right text-red-500">{{ fmt(rec.total_deductions) }}</td>
                <td class="px-4 py-3 text-right text-slate-500 text-xs">{{ fmt(rec.withheld_tax) }}</td>
                <td class="px-4 py-3 text-right font-semibold text-slate-800">{{ fmt(rec.net_pay) }}</td>
                <td class="px-4 py-3">
                  <span :class="statusBadgeClass(rec.status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium">
                    {{ rec.status }}
                  </span>
                </td>
                <td class="px-4 py-3 flex items-center gap-3">
                  <button @click="openBreakdown(rec)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Breakdown</button>
                  <a
                    v-if="['for_review','approved','released'].includes(run.status)"
                    :href="route('payroll.payslip.download', { payrollRun: run.id, payrollRecord: rec.id })"
                    target="_blank"
                    class="text-xs text-slate-500 hover:text-slate-700 font-medium inline-flex items-center gap-1"
                  >
                    <DocumentArrowDownIcon class="h-3 w-3" />Payslip
                  </a>
                </td>
              </tr>
            </tbody>
            <!-- Totals row -->
            <tfoot v-if="records.data?.length" class="bg-slate-50 border-t-2 border-slate-200">
              <tr>
                <td colspan="4" class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Page Totals</td>
                <td class="px-4 py-3 text-right font-semibold text-slate-700">{{ fmt(pageTotal('gross_pay')) }}</td>
                <td class="px-4 py-3 text-right font-semibold text-red-500">{{ fmt(pageTotal('total_deductions')) }}</td>
                <td class="px-4 py-3 text-right text-slate-500">{{ fmt(pageTotal('withheld_tax')) }}</td>
                <td class="px-4 py-3 text-right font-bold text-slate-800">{{ fmt(pageTotal('net_pay')) }}</td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
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

    <!-- Approve Modal -->
    <Teleport to="body">
      <div v-if="approveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md mx-4">
          <h3 class="text-base font-semibold text-slate-800 mb-1">Approve Payroll Run</h3>
          <p class="text-sm text-slate-500 mb-4">You are approving <strong>{{ run.control_no }}</strong> with net pay of <strong>{{ fmt(run.total_net) }}</strong>.</p>
          <div class="mb-4">
            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks <span class="font-normal text-slate-400">(optional)</span></label>
            <textarea v-model="approveRemarks" rows="3" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"></textarea>
          </div>
          <div class="flex gap-3 justify-end">
            <button @click="approveModal = false" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="submitApprove" class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors">Confirm Approval</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Breakdown Modal -->
    <Teleport to="body">
      <div v-if="breakdown.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-lg mx-4 max-h-[85vh] overflow-y-auto">
          <h3 class="text-base font-semibold text-slate-800 mb-1">Payroll Breakdown</h3>
          <p class="text-sm text-slate-500 mb-4">{{ breakdown.record?.user?.name }}</p>

          <div class="grid grid-cols-2 gap-x-8 gap-y-1 text-sm mb-4">
            <div class="text-slate-500">Monthly Salary</div><div class="text-right font-medium text-slate-800">{{ fmt(breakdown.record?.monthly_salary) }}</div>
            <div class="text-slate-500">Basic Pay (half-month)</div><div class="text-right text-slate-700">{{ fmt(breakdown.record?.basic_pay) }}</div>
            <div class="text-slate-500">Days Worked</div><div class="text-right text-slate-600">{{ breakdown.record?.days_worked }}</div>
            <div class="text-slate-500">Days Absent</div><div class="text-right text-red-500">{{ breakdown.record?.days_absent }}</div>
            <div class="text-slate-500">Late (min)</div><div class="text-right text-amber-600">{{ breakdown.record?.late_minutes }}</div>
            <div class="text-slate-500">Undertime (min)</div><div class="text-right text-orange-600">{{ breakdown.record?.undertime_minutes }}</div>
            <div class="text-slate-500">Absence Deduction</div><div class="text-right text-red-500">-{{ fmt(breakdown.record?.absence_deduction) }}</div>
            <div class="text-slate-500">Tardiness Deduction</div><div class="text-right text-red-500">-{{ fmt(breakdown.record?.tardiness_deduction) }}</div>
          </div>

          <div v-if="breakdown.record?.allowances?.length" class="mb-4">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Allowances</p>
            <div v-for="a in breakdown.record.allowances" :key="a.id" class="flex justify-between text-sm py-0.5">
              <span class="text-slate-600">{{ a.allowance_type?.name }}</span>
              <span class="text-emerald-600 font-medium">+{{ fmt(a.amount) }}</span>
            </div>
            <div class="border-t border-slate-100 mt-1 pt-1 flex justify-between text-sm font-semibold">
              <span class="text-slate-700">Gross Pay</span>
              <span class="text-slate-800">{{ fmt(breakdown.record?.gross_pay) }}</span>
            </div>
          </div>

          <div v-if="breakdown.record?.deductions?.length" class="mb-4">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Deductions</p>
            <div v-for="d in breakdown.record.deductions" :key="d.id" class="flex justify-between text-sm py-0.5">
              <span class="text-slate-600">{{ d.allowance_type?.name }}</span>
              <span class="text-red-500 font-medium">-{{ fmt(d.amount) }}</span>
            </div>
            <div class="flex justify-between text-sm py-0.5">
              <span class="text-slate-600">Withholding Tax</span>
              <span class="text-red-500 font-medium">-{{ fmt(breakdown.record?.withheld_tax) }}</span>
            </div>
            <div class="border-t border-slate-100 mt-1 pt-1 flex justify-between text-sm font-semibold">
              <span class="text-slate-700">Total Deductions</span>
              <span class="text-red-600">-{{ fmt(breakdown.record?.total_deductions) }}</span>
            </div>
          </div>

          <div class="bg-slate-50 rounded-lg p-3 flex justify-between text-base font-bold">
            <span class="text-slate-700">Net Pay</span>
            <span class="text-slate-800">{{ fmt(breakdown.record?.net_pay) }}</span>
          </div>

          <div class="flex justify-between mt-5">
            <a
              v-if="['for_review','approved','released'].includes(run.status)"
              :href="route('payroll.payslip.download', { payrollRun: run.id, payrollRecord: breakdown.record?.id })"
              target="_blank"
              class="inline-flex items-center gap-1.5 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors font-medium"
            >
              <DocumentArrowDownIcon class="h-4 w-4" />Download Payslip
            </a>
            <button @click="breakdown.open = false" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 ml-auto">Close</button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>

<script setup>
import { ref, reactive, onMounted, onBeforeUnmount } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { statusBadgeClass } from '@/Composables/useStatusBadge.js'
import {
  Cog6ToothIcon,
  CheckIcon,
  PaperAirplaneIcon,
  CheckCircleIcon,
  DocumentArrowDownIcon,
  DocumentTextIcon,
  ArchiveBoxArrowDownIcon,
  ChevronDownIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  run:     Object,
  records: Object,
})

const MONTHS = ['','January','February','March','April','May','June','July','August','September','October','November','December']
const monthName = (m) => MONTHS[m] ?? m

function fmt (val) {
  if (val === null || val === undefined) return '—'
  return '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function goToPage (url) {
  if (url) router.visit(url, { preserveState: true })
}

function pageTotal (field) {
  return (props.records.data ?? []).reduce((s, r) => s + parseFloat(r[field] ?? 0), 0)
}

// ── Process ────────────────────────────────────────────────────────────────
function processRun () {
  if (confirm('Queue payroll computation for all active employees?')) {
    router.post(route('payroll.process', props.run.id))
  }
}

// ── Approve ────────────────────────────────────────────────────────────────
const approveModal   = ref(false)
const approveRemarks = ref('')

function submitApprove () {
  router.post(route('payroll.approve', props.run.id), { remarks: approveRemarks.value }, {
    onSuccess: () => { approveModal.value = false; approveRemarks.value = '' },
  })
}

// ── Release / Cancel ───────────────────────────────────────────────────────
function releaseRun () {
  if (confirm(`Release ${props.run.control_no}? This is final.`)) {
    router.post(route('payroll.release', props.run.id))
  }
}

function cancelRun () {
  if (confirm('Cancel this payroll run?')) {
    router.post(route('payroll.cancel', props.run.id))
  }
}

// ── Reports dropdown ───────────────────────────────────────────────────────
const reportsOpen = ref(false)
const reportsMenu = ref(null)

function handleOutsideClick (e) {
  if (reportsMenu.value && !reportsMenu.value.contains(e.target)) {
    reportsOpen.value = false
  }
}
onMounted(() => document.addEventListener('click', handleOutsideClick))
onBeforeUnmount(() => document.removeEventListener('click', handleOutsideClick))

function downloadZip () {
  reportsOpen.value = false
  // POST via a hidden form so the browser handles the file download response
  const form = document.createElement('form')
  form.method = 'POST'
  form.action = route('payroll.payslips.zip', props.run.id)

  const csrf = document.createElement('input')
  csrf.type  = 'hidden'
  csrf.name  = '_token'
  csrf.value = document.querySelector('meta[name="csrf-token"]')?.content ?? ''
  form.appendChild(csrf)

  document.body.appendChild(form)
  form.submit()
  document.body.removeChild(form)
}

// ── Breakdown ──────────────────────────────────────────────────────────────
const breakdown = reactive({ open: false, record: null })

function openBreakdown (record) {
  breakdown.record = record
  breakdown.open   = true
}
</script>
