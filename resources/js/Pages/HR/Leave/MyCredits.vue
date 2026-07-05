<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

const props = defineProps({
  balances:       Object,
  creditRows:     Array,
  transactions:   Object,
  applications:   Array,
  serviceRecords: { type: Array, default: () => [] },
  year:           Number,
  years:          Array,
  isTeaching:     Boolean,
})

// ── Service Credit submission ─────────────────────────────────────────────────
const showScModal = ref(false)

const scForm = useForm({
  service_date:   '',
  service_type:   '',
  hours_rendered: '',
  remarks:        '',
})

const serviceTypeOptions = [
  { value: 'extra_teaching_load', label: 'Extra Teaching Load' },
  { value: 'committee_work',      label: 'Committee Work' },
  { value: 'school_activity',     label: 'School Activity / Event' },
  { value: 'special_assignment',  label: 'Special Assignment' },
  { value: 'other',               label: 'Other' },
]

// Preview: hours → days (floor to nearest 0.5)
const daysPreview = computed(() => {
  const h = parseFloat(scForm.hours_rendered)
  if (!h || h < 4) return null
  return Math.floor((h / 8) * 2) / 2
})

const openScModal = () => {
  scForm.reset()
  showScModal.value = true
}
const closeScModal = () => {
  showScModal.value = false
  scForm.reset()
}

const submitServiceCredit = () => {
  scForm.post(route('hr.leave-credits.my.service-credits.store'), {
    preserveScroll: true,
    onSuccess: () => {
      closeScModal()
      Swal.fire('Submitted!', 'Your service credit record has been submitted and is pending HR approval.', 'success')
    },
    onError: () => {
      Swal.fire('Error', 'Please correct the form errors and try again.', 'error')
    },
  })
}

// Status helpers for service records
const scStatusColor = (s) => ({
  pending:  'amber',
  approved: 'green',
  rejected: 'red',
  consumed: 'blue',
  expired:  'slate',
})[s] ?? 'slate'

const scTypeLabel = (t) => ({
  extra_teaching_load: 'Extra Teaching Load',
  committee_work:      'Committee Work',
  school_activity:     'School Activity',
  special_assignment:  'Special Assignment',
  other:               'Other',
})[t] ?? t

// Today's date for max attribute
const today = new Date().toISOString().split('T')[0]

// ── Year switcher ─────────────────────────────────────────────────────────────
const changeYear = (y) => {
  router.get(route('hr.leave-credits.my'), { year: y }, { preserveState: false })
}

// ── Balance cards ─────────────────────────────────────────────────────────────
const balanceCards = computed(() => {
  const cards = []

  // Regular leave types from credit rows
  props.creditRows.forEach(row => {
    const code = row.leave_type?.code ?? '?'
    cards.push({
      code,
      name:         row.leave_type?.name ?? code,
      balance:      Number(row.balance ?? 0),
      earned:       Number(row.earned ?? 0),
      carriedOver:  Number(row.carried_over ?? 0),
      used:         Number(row.used ?? 0),
      forfeited:    Number(row.forfeited ?? 0),
      monetized:    Number(row.monetized ?? 0),
      isService:    false,
    })
  })

  // Teaching Service Credits — separate pool, distinct from CTO
  if (props.isTeaching && props.balances?.SC) {
    const sc = props.balances.SC
    cards.push({
      code:        'SC',
      name:        'Service Credits',
      balance:     Number(sc.balance ?? 0),
      earned:      null,
      carriedOver: null,
      used:        null,
      forfeited:   null,
      monetized:   null,
      isService:   true,
    })
  }

  return cards
})

// ── Helpers ───────────────────────────────────────────────────────────────────
const balanceColor = (bal) => {
  if (bal <= 0)   return 'text-red-600'
  if (bal <= 5)   return 'text-amber-600'
  return 'text-emerald-600'
}

const balanceBg = (bal) => {
  if (bal <= 0)   return 'from-red-50 to-red-100/60 border-red-200'
  if (bal <= 5)   return 'from-amber-50 to-amber-100/60 border-amber-200'
  return 'from-emerald-50 to-emerald-100/60 border-emerald-200'
}

const txTypeColor = (type) => ({
  INITIAL:      'purple',
  ACCRUAL:      'green',
  DEDUCTION:    'red',
  ADJUSTMENT:   'blue',
  RESTORATION:  'amber',
  MONETIZATION: 'orange',
  CARRYOVER:    'indigo',
  FORFEITURE:   'slate',
})[type] ?? 'slate'

const statusColor = (s) => ({
  pending:     'amber',
  hr_verified: 'purple',
  forwarded:   'blue',
  approved:    'green',
  rejected:    'red',
  cancelled:   'slate',
})[s] ?? 'slate'

const fmtDate = (d) => {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

const fmtDatetime = (d) => {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const signedAmount = (amount) => {
  const n = Number(amount)
  return n >= 0 ? `+${n.toFixed(4)}` : n.toFixed(4)
}

const txData    = computed(() => props.transactions?.data ?? [])
const curPage   = computed(() => props.transactions?.current_page ?? 1)
const lastPage  = computed(() => props.transactions?.last_page ?? 1)
const txTotal   = computed(() => props.transactions?.total ?? null)

const goToPage = (p) => {
  router.get(route('hr.leave-credits.my'), { year: props.year, page: p }, { preserveState: true, replace: true })
}
</script>

<template>
  <Head title="My Leave Credits" />
  <AdminLayout title="My Leave Credits">
    <div class="space-y-6">

      <!-- Header + Year picker -->
      <AppPageHeader
        title="My Leave Credits"
        :subtitle="isTeaching ? 'Teaching personnel — Service Credits are earned per case and tracked separately from CTO.' : 'Non-Teaching — 1.25 VL + 1.25 SL accrued monthly.'"
      >
        <template #actions>
          <label class="text-sm text-slate-500 font-medium">Year:</label>
          <select :value="year" @change="changeYear($event.target.value)"
                  class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </template>
      </AppPageHeader>

      <!-- ── Balance Cards ──────────────────────────────────────────────────── -->
      <div v-if="balanceCards.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="card in balanceCards" :key="card.code"
             :class="['rounded-2xl border bg-gradient-to-br p-5 shadow-sm', balanceBg(card.balance)]">

          <div class="flex items-start justify-between">
            <div>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ card.code }}</p>
              <p class="text-sm text-slate-600 mt-0.5">{{ card.name }}</p>
            </div>
            <AppBadge v-if="card.isService" color="blue">Service Credit</AppBadge>
          </div>

          <p :class="['text-4xl font-extrabold mt-3 tabular-nums', balanceColor(card.balance)]">
            {{ card.balance.toFixed(2) }}
            <span class="text-base font-medium text-slate-500 ml-1">days</span>
          </p>

          <!-- Breakdown (non-service) -->
          <div v-if="!card.isService" class="mt-4 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-slate-500">
            <div class="flex justify-between">
              <span>Earned</span>
              <span class="font-medium text-slate-700">{{ card.earned.toFixed(4) }}</span>
            </div>
            <div class="flex justify-between">
              <span>Carried over</span>
              <span class="font-medium text-slate-700">{{ card.carriedOver.toFixed(4) }}</span>
            </div>
            <div class="flex justify-between">
              <span>Used</span>
              <span class="font-medium text-slate-700">{{ card.used.toFixed(4) }}</span>
            </div>
            <div class="flex justify-between">
              <span>Forfeited</span>
              <span class="font-medium text-slate-700">{{ card.forfeited.toFixed(4) }}</span>
            </div>
            <div v-if="card.monetized > 0" class="flex justify-between col-span-2">
              <span>Monetized</span>
              <span class="font-medium text-slate-700">{{ card.monetized.toFixed(4) }}</span>
            </div>
          </div>

          <p v-if="card.isService" class="mt-3 text-xs text-slate-400">
            Sum of all approved, unconsumed, non-expired service credit records.
          </p>
        </div>
      </div>

      <EmptyState v-else :title="`No leave credit records found for ${year}.`" subtitle="Credits are initialized by HR." />

      <!-- ── Leave Applications (with deduction info) ───────────────────────── -->
      <AppCard :padded="false" title="Leave Applications" :subtitle="`${year} — Your leave requests and the credits deducted on approval.`">
        <AppTable :is-empty="!applications.length" :skeleton-cols="7" :card="false">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Control No.</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Leave Type</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Dates</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Applied</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Deducted</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">LWOP</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            </tr>
          </template>

          <tr v-for="app in applications" :key="app.id" class="hover:bg-slate-50/60">
            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ app.control_no ?? '—' }}</td>
            <td class="px-4 py-3 text-slate-700">{{ app.leave_type?.name ?? '—' }}</td>
            <td class="px-4 py-3 text-slate-600 whitespace-nowrap text-xs">
              {{ fmtDate(app.date_from) }} – {{ fmtDate(app.date_to) }}
            </td>
            <td class="px-4 py-3 text-center font-medium text-slate-700">{{ app.days_applied }}</td>
            <td class="px-4 py-3 text-center">
              <span v-if="Number(app.days_deducted) > 0" class="font-medium text-danger-600">
                {{ app.days_deducted }}
              </span>
              <span v-else class="text-slate-400 text-xs">—</span>
            </td>
            <td class="px-4 py-3 text-center">
              <AppBadge v-if="app.is_without_pay" color="red">LWOP</AppBadge>
              <span v-else class="text-slate-400 text-xs">—</span>
            </td>
            <td class="px-4 py-3">
              <AppBadge :color="statusColor(app.status)">{{ app.status }}</AppBadge>
            </td>
          </tr>

          <template #empty>
            <EmptyState :title="`No leave applications for ${year}.`" />
          </template>
        </AppTable>
      </AppCard>

      <!-- ── Service Credit Records (Teaching only) ───────────────────────────── -->
      <AppCard v-if="isTeaching" :padded="false">
        <template #header>
          <div>
            <h3 class="text-sm font-semibold text-slate-700">My Service Credit Records</h3>
            <p class="text-xs text-slate-400 mt-0.5">
              Service credits are earned for extra duties and must be approved by HR before they count toward your CTO balance.
            </p>
          </div>
          <AppButton size="sm" @click="openScModal">
            <PlusIcon class="w-4 h-4" />
            Submit Service Credit
          </AppButton>
        </template>

        <AppTable :is-empty="serviceRecords.length === 0" :skeleton-cols="7" :card="false">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Service Date</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Hours</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Days Equiv.</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Expires</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Remarks</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            </tr>
          </template>

          <tr v-for="rec in serviceRecords" :key="rec.id" class="hover:bg-slate-50/60">
            <td class="px-4 py-3 text-slate-700 whitespace-nowrap">{{ fmtDate(rec.service_date) }}</td>
            <td class="px-4 py-3 text-slate-600">{{ scTypeLabel(rec.service_type) }}</td>
            <td class="px-4 py-3 text-right text-slate-600">{{ rec.hours_rendered }}h</td>
            <td class="px-4 py-3 text-right font-semibold text-slate-800">{{ Number(rec.days_equivalent).toFixed(2) }}</td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ rec.expires_at ? fmtDate(rec.expires_at) : '—' }}</td>
            <td class="px-4 py-3 text-xs text-slate-500 max-w-xs truncate" :title="rec.remarks">{{ rec.remarks || '—' }}</td>
            <td class="px-4 py-3">
              <AppBadge :color="scStatusColor(rec.status)">{{ rec.status }}</AppBadge>
            </td>
          </tr>

          <template #empty>
            <EmptyState title="No service credit records yet" subtitle="Submit one using the button above." />
          </template>
        </AppTable>
      </AppCard>

      <!-- ── Transaction Ledger ──────────────────────────────────────────────── -->
      <AppCard :padded="false" title="Credit Transaction History" :subtitle="`${year} — Full audit trail of all changes to your leave credit balances.`">
        <AppTable :is-empty="txData.length === 0" :skeleton-cols="7" :card="false">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Leave Type</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Amount</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Balance After</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Remarks</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Recorded By</th>
            </tr>
          </template>

          <tr v-for="tx in txData" :key="tx.id" class="hover:bg-slate-50/60">
            <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">{{ fmtDatetime(tx.created_at) }}</td>
            <td class="px-4 py-3">
              <AppBadge :color="txTypeColor(tx.type)">{{ tx.type }}</AppBadge>
            </td>
            <td class="px-4 py-3 text-slate-600">{{ tx.leave_type?.code ?? '—' }}</td>
            <td class="px-4 py-3 text-right font-mono font-semibold"
                :class="Number(tx.amount) >= 0 ? 'text-success-600' : 'text-danger-600'">
              {{ signedAmount(tx.amount) }}
            </td>
            <td class="px-4 py-3 text-right font-mono text-slate-700">{{ Number(tx.balance_after).toFixed(4) }}</td>
            <td class="px-4 py-3 text-xs text-slate-500 max-w-xs truncate" :title="tx.remarks">{{ tx.remarks ?? '—' }}</td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ tx.recorder?.name ?? 'System' }}</td>
          </tr>

          <template #empty>
            <EmptyState :title="`No transactions recorded for ${year}.`" />
          </template>

          <template #footer>
            <PaginationControl
              :current-page="curPage"
              :total-pages="lastPage"
              :total="txTotal"
              @prev="goToPage(curPage - 1)"
              @next="goToPage(curPage + 1)"
              @page="goToPage"
            />
          </template>
        </AppTable>
      </AppCard>

    </div>

    <!-- ── Submit Service Credit Modal ────────────────────────────────────────── -->
    <AppModal :show="showScModal" title="Submit Service Credit"
              subtitle="Record extra service rendered. HR must approve before it counts toward your CTO balance."
              size="lg" @close="closeScModal">
      <form @submit.prevent="submitServiceCredit" class="space-y-4">

        <!-- Service Date -->
        <AppInput v-model="scForm.service_date" type="date" :max="today" required
                  label="Date of Service" :error="scForm.errors.service_date" />

        <!-- Service Type -->
        <AppSelect v-model="scForm.service_type" required label="Nature of Service" placeholder="Select service type…" :error="scForm.errors.service_type">
          <option v-for="opt in serviceTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </AppSelect>

        <!-- Hours Rendered + Days Preview -->
        <div>
          <AppInput v-model="scForm.hours_rendered" type="number" min="4" max="24" step="0.5" required
                    placeholder="e.g. 8" label="Hours Rendered (minimum 4 hours)" :error="scForm.errors.hours_rendered" />

          <!-- Days equivalent preview -->
          <div v-if="daysPreview !== null"
               class="mt-2 flex items-center gap-2 rounded-lg bg-indigo-50 border border-indigo-100 px-3 py-2 text-sm">
            <span class="text-indigo-500 font-medium">≈</span>
            <span class="text-indigo-700 font-semibold">{{ daysPreview }} day(s)</span>
            <span class="text-indigo-400 text-xs">credit (rounded down to nearest 0.5 per CSC rules)</span>
          </div>
        </div>

        <!-- Remarks -->
        <div>
          <AppTextarea v-model="scForm.remarks" :rows="3" maxlength="500"
                       placeholder="Describe the service rendered, e.g. committee meeting on curriculum review…"
                       label="Remarks / Details" :error="scForm.errors.remarks" />
          <p class="mt-1 text-xs text-slate-400">{{ (scForm.remarks || '').length }}/500</p>
        </div>

        <div class="flex justify-end gap-2 pt-1">
          <AppButton type="button" variant="secondary" @click="closeScModal">Cancel</AppButton>
          <AppButton type="submit" :loading="scForm.processing">
            {{ scForm.processing ? 'Submitting…' : 'Submit for Approval' }}
          </AppButton>
        </div>
      </form>
    </AppModal>

  </AdminLayout>
</template>
