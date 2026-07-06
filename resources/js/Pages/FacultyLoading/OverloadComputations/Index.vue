<template>
  <Head title="Overload Computations" />
  <AdminLayout title="Overload Computations">
    <div class="space-y-5">

      <AppPageHeader title="Overload Computations" subtitle="PHTR = (Annual Rate ÷ 1,600) × 1.25 · Pay = PHTR × hrs/wk × weeks">
        <template #actions>
          <AppButton variant="secondary" as="link" :href="route('faculty-loading.salary-schedules.index')">
            <TableCellsIcon class="h-4 w-4" /> Salary Schedule
          </AppButton>
          <AppButton variant="secondary" @click="openBulk()">
            <BoltIcon class="h-4 w-4" /> Bulk Compute
          </AppButton>
          <AppButton @click="openForm()">
            <PlusIcon class="h-4 w-4" /> New Computation
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="Object.keys($page.props.errors ?? {}).length" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm space-y-1">
        <p v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</p>
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <select v-model="filters.term_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option v-for="t in terms" :key="t.id" :value="t.id">
            {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
          </option>
        </select>
      </AppFilterBar>

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
      <AppCard v-if="computations.length === 0">
        <EmptyState title="No overload computations for this term" subtitle="Use Bulk Compute or add individual computations for overloaded faculty." :icon="BanknotesIcon" />
      </AppCard>

      <!-- Computation cards -->
      <div v-else class="space-y-3">
        <AppCard v-for="c in computations" :key="c.id">
          <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">

            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <p class="font-semibold text-slate-800">{{ c.faculty?.name ?? '—' }}</p>
                <AppBadge v-if="c.faculty?.position" color="indigo">
                  {{ c.faculty.position }}
                  <span v-if="c.faculty.salary_grade" class="ml-1">SG {{ c.faculty.salary_grade }}</span>
                </AppBadge>
                <AppBadge :color="statusBadge(c.status)">{{ statusLabel(c.status) }}</AppBadge>
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
                <p class="text-base font-bold text-success-700">{{ phpFmt(c.total_overload_pay) }}</p>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div v-if="['for_approval', 'approved'].includes(c.status)"
            class="flex gap-2 mt-4 pt-4 border-t border-slate-50">
            <template v-if="c.status === 'for_approval'">
              <AppButton size="sm" variant="danger" @click="act(c, false)">Reject</AppButton>
              <AppButton size="sm" variant="success" @click="act(c, true)">Approve</AppButton>
            </template>
            <AppButton v-if="c.status === 'approved'" size="sm" @click="markPaid(c)">Mark as Paid</AppButton>
          </div>
        </AppCard>
      </div>

    </div>

    <!-- Single computation modal -->
    <AppModal :show="modal" title="New Overload Computation" size="lg" @close="modal = false; preview = null">
      <!-- Preview -->
      <div v-if="preview" class="bg-slate-50 rounded-xl border border-slate-200 px-4 py-3 text-sm space-y-1 mb-4">
        <div class="flex justify-between"><span class="text-slate-500">PHTR:</span><span class="font-semibold">{{ phpFmt(preview.phtr) }}</span></div>
        <div class="flex justify-between"><span class="text-slate-500">Overload units:</span><span class="font-semibold">{{ preview.overload_units }}</span></div>
        <div class="flex justify-between"><span class="text-slate-500">Hrs/week × weeks:</span><span class="font-semibold">{{ preview.overload_hours }} × {{ preview.term_weeks }}</span></div>
        <div class="flex justify-between pt-1 border-t border-slate-200 mt-1">
          <span class="text-slate-600 font-medium">Estimated Total Pay:</span>
          <span class="font-bold text-success-700">{{ phpFmt(preview.total_overload_pay) }}</span>
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
          <AppInput v-model.number="salaryGrade" type="number" min="1" max="33" label="Salary Grade" @change="fillRate" />
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Step</label>
            <select v-model.number="salaryStep" @change="fillRate"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option v-for="s in 8" :key="s" :value="s">Step {{ s }}</option>
            </select>
          </div>
          <div class="flex items-end">
            <AppButton variant="secondary" block @click="fillRate">Auto-fill Rate</AppButton>
          </div>
        </div>

        <!-- Annual rate -->
        <div>
          <AppInput v-model.number="form.annual_rate" type="number" step="1" min="1" label="Annual Rate (₱)" required />
          <p v-if="form.annual_rate" class="text-xs text-slate-400 mt-1">
            PHTR: {{ phpFmt((form.annual_rate / 1600) * 1.25) }}
          </p>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model.number="form.overload_hours" type="number" step="0.5" min="0.5" label="Overload Hrs/week" required />
          <AppInput v-model.number="form.term_weeks" type="number" min="1" max="26" label="Term Weeks" />
        </div>

        <AppTextarea v-model="form.remarks" rows="2" label="Remarks" />
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="runPreview">
          <CalculatorIcon class="h-4 w-4" /> Preview
        </AppButton>
        <AppButton variant="secondary" @click="modal = false; preview = null">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="save">Submit</AppButton>
      </template>
    </AppModal>

    <!-- Bulk compute modal -->
    <AppModal :show="bulkModal" title="Bulk Overload Computation" @close="bulkModal = false">
      <div class="space-y-4">
        <p class="text-sm text-slate-600">
          Generates computations for all <strong>{{ pendingLoads.length }}</strong> overloaded faculty in the current term
          that don't have an existing computation. Annual rates are looked up from the current salary schedule using
          each faculty member's assigned SST position.
        </p>

        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model.number="bulkForm.overload_hours" type="number" step="0.5" min="0.5" label="Overload Hrs/week" required />
          <AppInput v-model.number="bulkForm.term_weeks" type="number" min="1" max="26" label="Term Weeks" />
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Salary Step</label>
            <select v-model.number="bulkForm.step"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option v-for="s in 8" :key="s" :value="s">Step {{ s }}</option>
            </select>
          </div>
        </div>

        <div class="bg-warning-50 border border-warning-100 rounded-lg px-3 py-2 text-xs text-warning-700">
          Faculty without an SST position or a matching salary schedule entry will be skipped.
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="bulkModal = false">Cancel</AppButton>
        <AppButton :loading="bulkForm.processing" @click="submitBulk">Generate Computations</AppButton>
      </template>
    </AppModal>

    <!-- Approval modal -->
    <AppModal :show="!!approvalTarget" :title="`${approvalApproved ? 'Approve' : 'Reject'} Computation`" @close="approvalTarget = null">
      <div class="space-y-3">
        <p class="text-sm text-slate-600">{{ approvalTarget?.faculty?.name }} — {{ phpFmt(approvalTarget?.total_overload_pay) }}</p>
        <AppTextarea v-model="approvalRemarks" rows="3" placeholder="Remarks (optional)" />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="approvalTarget = null">Cancel</AppButton>
        <AppButton :variant="approvalApproved ? 'success' : 'danger'" @click="submitApproval">
          {{ approvalApproved ? 'Approve' : 'Reject' }}
        </AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
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

async function markPaid(c) {
  if (! await confirmAction({ title: 'Mark as paid?', text: `Mark payment for ${c.faculty?.name} as paid?`, confirmText: 'Mark as Paid' })) return
  useForm({}).post(route('faculty-loading.overload-computations.mark-paid', c.id))
}

function statusBadge(status) {
  return {
    for_approval: 'amber',
    approved:     'green',
    rejected:     'red',
    paid:         'blue',
    pending:      'slate',
  }[status] ?? 'slate'
}

function statusLabel(status) {
  return {
    for_approval: 'For Approval', approved: 'Approved',
    rejected: 'Rejected', paid: 'Paid', pending: 'Pending',
  }[status] ?? status
}
</script>
