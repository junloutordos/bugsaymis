<template>
  <Head title="Overload Computations" />
  <AdminLayout title="Overload Computations">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Overload Computations</h1>
          <p class="text-sm text-slate-500 mt-0.5">PHTR = (Annual Rate ÷ 1,600) × 1.25 · Pay = PHTR × hrs/wk × weeks</p>
        </div>
        <div class="flex gap-2">
          <Link :href="route('faculty-loading.salary-schedules.index')"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-lg font-medium">
            <TableCellsIcon class="h-4 w-4" /> Salary Schedule
          </Link>
          <button @click="openBulk()"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm border border-indigo-200 text-indigo-700 hover:bg-indigo-50 rounded-lg font-medium">
            <BoltIcon class="h-4 w-4" /> Bulk Compute
          </button>
          <button @click="openForm()"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm shrink-0">
            <PlusIcon class="h-4 w-4" /> New Computation
          </button>
        </div>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="Object.keys($page.props.errors ?? {}).length" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm space-y-1">
        <p v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</p>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-2">
        <select v-model="filters.term_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option v-for="t in terms" :key="t.id" :value="t.id">
            {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
          </option>
        </select>
      </div>

      <!-- Pending overloaded faculty (no computation yet) -->
      <div v-if="pendingLoads.length" class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <div class="flex items-center gap-2 mb-2">
          <ExclamationTriangleIcon class="h-4 w-4 text-amber-600 shrink-0" />
          <p class="text-sm font-semibold text-amber-800">{{ pendingLoads.length }} overloaded faculty without a computation</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <span v-for="l in pendingLoads" :key="l.id"
            class="inline-flex items-center gap-1.5 bg-white border border-amber-200 rounded-full px-2.5 py-0.5 text-xs text-amber-800">
            {{ l.faculty?.name }}
            <span v-if="l.faculty?.position" class="text-amber-500">({{ l.faculty.position }})</span>
            — +{{ l.overload_units }} units
          </span>
        </div>
      </div>

      <!-- Empty -->
      <div v-if="computations.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <BanknotesIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">No overload computations for this term</p>
        <p class="text-xs text-slate-400 mt-1">Use Bulk Compute or add individual computations for overloaded faculty.</p>
      </div>

      <!-- Computation cards -->
      <div v-else class="space-y-3">
        <div v-for="c in computations" :key="c.id"
          class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">

            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <p class="font-semibold text-slate-800">{{ c.faculty?.name ?? '—' }}</p>
                <span v-if="c.faculty?.position"
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-indigo-50 text-indigo-700">
                  {{ c.faculty.position }}
                  <span v-if="c.faculty.salary_grade" class="ml-1 text-indigo-400">SG {{ c.faculty.salary_grade }}</span>
                </span>
                <span :class="statusBadge(c.status)"
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                  {{ statusLabel(c.status) }}
                </span>
              </div>
              <p class="text-xs text-slate-400 mt-0.5">{{ c.term?.label ?? '—' }}</p>
            </div>

            <!-- Breakdown grid -->
            <div class="flex flex-wrap gap-x-5 gap-y-1 text-center shrink-0">
              <div>
                <p class="text-xs text-slate-400">Annual Rate</p>
                <p class="text-sm font-semibold text-slate-700">{{ phpFmt(c.annual_rate) }}</p>
              </div>
              <div>
                <p class="text-xs text-slate-400">PHTR</p>
                <p class="text-sm font-semibold text-slate-700">{{ phpFmt(c.phtr) }}</p>
              </div>
              <div>
                <p class="text-xs text-slate-400">OL Units</p>
                <p class="text-sm font-semibold text-slate-700">{{ c.overload_units }}</p>
              </div>
              <div>
                <p class="text-xs text-slate-400">Hrs/wk</p>
                <p class="text-sm font-semibold text-slate-700">{{ c.overload_hours }}</p>
              </div>
              <div>
                <p class="text-xs text-slate-400">Weeks</p>
                <p class="text-sm font-semibold text-slate-700">{{ c.term_weeks }}</p>
              </div>
              <div class="border-l border-slate-100 pl-4">
                <p class="text-xs text-slate-400">Total Pay</p>
                <p class="text-base font-bold text-emerald-700">{{ phpFmt(c.total_overload_pay) }}</p>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div v-if="['for_approval', 'approved'].includes(c.status)"
            class="flex gap-2 mt-4 pt-4 border-t border-slate-50">
            <template v-if="c.status === 'for_approval'">
              <button @click="act(c, false)"
                class="px-3 py-1.5 text-xs bg-red-50 text-red-700 hover:bg-red-100 rounded-lg font-medium">
                Reject
              </button>
              <button @click="act(c, true)"
                class="px-3 py-1.5 text-xs bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium">
                Approve
              </button>
            </template>
            <button v-if="c.status === 'approved'" @click="markPaid(c)"
              class="px-3 py-1.5 text-xs bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium">
              Mark as Paid
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- Single computation modal -->
    <div v-if="modal" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 backdrop-blur-sm p-4 overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 space-y-4 my-8">
        <h2 class="text-lg font-semibold text-slate-800">New Overload Computation</h2>

        <!-- Preview -->
        <div v-if="preview" class="bg-slate-50 rounded-xl border border-slate-200 px-4 py-3 text-sm space-y-1">
          <div class="flex justify-between"><span class="text-slate-500">PHTR:</span><span class="font-semibold">{{ phpFmt(preview.phtr) }}</span></div>
          <div class="flex justify-between"><span class="text-slate-500">Overload units:</span><span class="font-semibold">{{ preview.overload_units }}</span></div>
          <div class="flex justify-between"><span class="text-slate-500">Hrs/week × weeks:</span><span class="font-semibold">{{ preview.overload_hours }} × {{ preview.term_weeks }}</span></div>
          <div class="flex justify-between pt-1 border-t border-slate-200 mt-1">
            <span class="text-slate-600 font-medium">Estimated Total Pay:</span>
            <span class="font-bold text-emerald-700">{{ phpFmt(preview.total_overload_pay) }}</span>
          </div>
        </div>

        <div class="space-y-3">
          <!-- Faculty load selector -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Faculty (overloaded only)</label>
            <select v-model="form.faculty_load_id" @change="onLoadChange"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select faculty load...</option>
              <option v-for="l in pendingLoads" :key="l.id" :value="l.id">
                {{ l.faculty?.name }} — +{{ l.overload_units }} OL units
                {{ l.faculty?.position ? `(${l.faculty.position}, SG ${l.faculty.salary_grade ?? '?'})` : '' }}
              </option>
            </select>
          </div>

          <!-- Salary grade + step for auto-fill -->
          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Salary Grade</label>
              <input v-model.number="salaryGrade" type="number" min="1" max="33" @change="fillRate"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Step</label>
              <select v-model.number="salaryStep" @change="fillRate"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                <option v-for="s in 8" :key="s" :value="s">Step {{ s }}</option>
              </select>
            </div>
            <div class="flex items-end">
              <button @click="fillRate"
                class="w-full px-3 py-2 text-sm border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-lg font-medium">
                Auto-fill Rate
              </button>
            </div>
          </div>

          <!-- Annual rate -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Annual Rate (₱) *</label>
            <input v-model.number="form.annual_rate" type="number" step="1" min="1"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
            <p v-if="form.annual_rate" class="text-xs text-slate-400 mt-1">
              PHTR: {{ phpFmt((form.annual_rate / 1600) * 1.25) }}
            </p>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Overload Hrs/week *</label>
              <input v-model.number="form.overload_hours" type="number" step="0.5" min="0.5"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Term Weeks</label>
              <input v-model.number="form.term_weeks" type="number" min="1" max="26"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
            <textarea v-model="form.remarks" rows="2"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none" />
          </div>
        </div>

        <div class="flex justify-between gap-3 pt-1">
          <button type="button" @click="runPreview"
            class="px-4 py-2 text-sm border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-lg font-medium flex items-center gap-1.5">
            <CalculatorIcon class="h-4 w-4" /> Preview
          </button>
          <div class="flex gap-2">
            <button @click="modal = false; preview = null" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
            <button @click="save" :disabled="form.processing"
              class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium disabled:opacity-50">
              Submit
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Bulk compute modal -->
    <div v-if="bulkModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-semibold text-slate-800">Bulk Overload Computation</h2>
        <p class="text-sm text-slate-600">
          Generates computations for all <strong>{{ pendingLoads.length }}</strong> overloaded faculty in the current term
          that don't have an existing computation. Annual rates are looked up from the current salary schedule using
          each faculty member's assigned SST position.
        </p>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Overload Hrs/week *</label>
            <input v-model.number="bulkForm.overload_hours" type="number" step="0.5" min="0.5"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Term Weeks</label>
            <input v-model.number="bulkForm.term_weeks" type="number" min="1" max="26"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Salary Step</label>
            <select v-model.number="bulkForm.step" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option v-for="s in 8" :key="s" :value="s">Step {{ s }}</option>
            </select>
          </div>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs text-amber-800">
          Faculty without an SST position or a matching salary schedule entry will be skipped.
        </div>

        <div class="flex justify-end gap-2">
          <button @click="bulkModal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="submitBulk" :disabled="bulkForm.processing"
            class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium disabled:opacity-50">
            Generate Computations
          </button>
        </div>
      </div>
    </div>

    <!-- Approval modal -->
    <div v-if="approvalTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-semibold text-slate-800">{{ approvalApproved ? 'Approve' : 'Reject' }} Computation</h2>
        <p class="text-sm text-slate-600">{{ approvalTarget.faculty?.name }} — {{ phpFmt(approvalTarget.total_overload_pay) }}</p>
        <textarea v-model="approvalRemarks" rows="3" placeholder="Remarks (optional)"
          class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none" />
        <div class="flex justify-end gap-3">
          <button @click="approvalTarget = null" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="submitApproval"
            :class="approvalApproved ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700'"
            class="px-4 py-2 text-sm text-white rounded-lg font-medium">
            {{ approvalApproved ? 'Approve' : 'Reject' }}
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'
import {
  BanknotesIcon, BoltIcon, CalculatorIcon, CheckCircleIcon,
  ExclamationTriangleIcon, PlusIcon, TableCellsIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  computations: { type: Array,  default: () => [] },
  pendingLoads: { type: Array,  default: () => [] },
  terms:        { type: Array,  default: () => [] },
  positions:    { type: Array,  default: () => [] },
  currentTerm:  { type: Object, default: null },
  filters:      { type: Object, default: () => ({}) },
})

const filters = reactive({
  term_id: props.filters.term_id ?? props.currentTerm?.id ?? null,
})

function applyFilters() {
  router.get(route('faculty-loading.overload-computations.index'), filters, { preserveState: true })
}

function phpFmt(val) {
  if (val == null) return '—'
  return '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// ── Single computation form ───────────────────────────────────────────────────
const modal       = ref(false)
const preview     = ref(null)
const salaryGrade = ref(null)
const salaryStep  = ref(1)

const form = useForm({
  faculty_load_id: null,
  annual_rate:     0,
  overload_hours:  3,
  term_weeks:      18,
  remarks:         '',
})

function openForm() {
  form.reset()
  form.overload_hours = 3
  form.term_weeks     = 18
  preview.value       = null
  salaryGrade.value   = null
  salaryStep.value    = 1
  modal.value         = true
}

function onLoadChange() {
  const l = props.pendingLoads.find(l => l.id === form.faculty_load_id)
  if (l?.faculty?.salary_grade) {
    salaryGrade.value = l.faculty.salary_grade
    fillRate()
  }
}

async function fillRate() {
  if (!salaryGrade.value) return
  try {
    const { data } = await axios.get(route('faculty-loading.salary-schedules.lookup'), {
      params: { salary_grade: salaryGrade.value, step: salaryStep.value },
    })
    if (data.found) {
      form.annual_rate = data.annual_rate
    }
  } catch (e) {
    console.error(e)
  }
}

async function runPreview() {
  if (!form.annual_rate || !form.overload_hours) return
  const selected = props.pendingLoads.find(l => l.id === form.faculty_load_id)
  try {
    const { data } = await axios.post(route('faculty-loading.overload-computations.preview'), {
      annual_rate:    form.annual_rate,
      overload_units: selected?.overload_units ?? 0,
      overload_hours: form.overload_hours,
      term_weeks:     form.term_weeks,
    })
    preview.value = data
  } catch (e) {
    console.error(e)
  }
}

function save() {
  form.post(route('faculty-loading.overload-computations.store'), {
    onSuccess: () => { modal.value = false; preview.value = null },
  })
}

// ── Bulk compute ──────────────────────────────────────────────────────────────
const bulkModal = ref(false)
const bulkForm  = useForm({
  academic_term_id: null,
  overload_hours:   3,
  term_weeks:       18,
  step:             1,
})

function openBulk() {
  bulkForm.academic_term_id = filters.term_id ?? null
  bulkForm.overload_hours   = 3
  bulkForm.term_weeks       = 18
  bulkForm.step             = 1
  bulkModal.value           = true
}

function submitBulk() {
  bulkForm.post(route('faculty-loading.overload-computations.bulk-compute'), {
    onSuccess: () => { bulkModal.value = false },
  })
}

// ── Approve/reject ────────────────────────────────────────────────────────────
const approvalTarget   = ref(null)
const approvalApproved = ref(true)
const approvalRemarks  = ref('')

function act(c, approved) {
  approvalTarget.value   = c
  approvalApproved.value = approved
  approvalRemarks.value  = ''
}

function submitApproval() {
  useForm({ approved: approvalApproved.value, remarks: approvalRemarks.value })
    .post(route('faculty-loading.overload-computations.approve', approvalTarget.value.id), {
      onSuccess: () => { approvalTarget.value = null },
    })
}

function markPaid(c) {
  if (!confirm(`Mark payment for ${c.faculty?.name} as paid?`)) return
  useForm({}).post(route('faculty-loading.overload-computations.mark-paid', c.id))
}

function statusBadge(status) {
  return {
    for_approval: 'bg-amber-50 text-amber-700',
    approved:     'bg-emerald-50 text-emerald-700',
    rejected:     'bg-red-50 text-red-600',
    paid:         'bg-blue-50 text-blue-700',
    pending:      'bg-slate-100 text-slate-500',
  }[status] ?? 'bg-slate-50 text-slate-600'
}

function statusLabel(status) {
  return {
    for_approval: 'For Approval', approved: 'Approved',
    rejected: 'Rejected', paid: 'Paid', pending: 'Pending',
  }[status] ?? status
}
</script>
