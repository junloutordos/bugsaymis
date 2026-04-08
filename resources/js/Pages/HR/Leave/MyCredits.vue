<script setup>
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  balances:     Object,
  creditRows:   Array,
  transactions: Object,
  applications: Array,
  year:         Number,
  years:        Array,
  isTeaching:   Boolean,
})

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

  // Teaching CTO (service credits) — separate pool
  if (props.isTeaching && props.balances?.CTO) {
    const cto = props.balances.CTO
    cards.push({
      code:        'CTO',
      name:        'Service Credits (CTO)',
      balance:     Number(cto.balance ?? 0),
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

const txTypeClass = (type) => ({
  INITIAL:      'bg-purple-100 text-purple-700',
  ACCRUAL:      'bg-emerald-100 text-emerald-700',
  DEDUCTION:    'bg-red-100 text-red-700',
  ADJUSTMENT:   'bg-blue-100 text-blue-700',
  RESTORATION:  'bg-teal-100 text-teal-700',
  MONETIZATION: 'bg-orange-100 text-orange-700',
  CARRYOVER:    'bg-indigo-100 text-indigo-700',
  FORFEITURE:   'bg-slate-100 text-slate-600',
})[type] ?? 'bg-slate-100 text-slate-600'

const statusClass = (s) => ({
  pending:   'bg-amber-100 text-amber-700',
  forwarded: 'bg-blue-100 text-blue-700',
  approved:  'bg-emerald-100 text-emerald-700',
  rejected:  'bg-red-100 text-red-600',
  cancelled: 'bg-slate-100 text-slate-500',
})[s] ?? 'bg-slate-100 text-slate-500'

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

const goToPage = (p) => {
  router.get(route('hr.leave-credits.my'), { year: props.year, page: p }, { preserveState: true, replace: true })
}
</script>

<template>
  <Head title="My Leave Credits" />
  <AdminLayout title="My Leave Credits">
    <div class="space-y-6">

      <!-- Header + Year picker -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">My Leave Credits</h1>
          <p class="text-sm text-slate-500 mt-0.5">
            {{ isTeaching ? 'Teaching personnel — Service Credits apply for CTO.' : 'Non-Teaching — 1.25 VL + 1.25 SL accrued monthly.' }}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm text-slate-500 font-medium">Year:</label>
          <select :value="year" @change="changeYear($event.target.value)"
                  class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
      </div>

      <!-- ── Balance Cards ──────────────────────────────────────────────────── -->
      <div v-if="balanceCards.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="card in balanceCards" :key="card.code"
             :class="['rounded-2xl border bg-gradient-to-br p-5 shadow-sm', balanceBg(card.balance)]">

          <div class="flex items-start justify-between">
            <div>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ card.code }}</p>
              <p class="text-sm text-slate-600 mt-0.5">{{ card.name }}</p>
            </div>
            <span v-if="card.isService"
                  class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">
              Service Credit
            </span>
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

      <div v-else class="bg-white rounded-xl border border-slate-100 shadow-sm p-10 text-center text-slate-400 text-sm">
        No leave credit records found for {{ year }}. Credits are initialized by HR.
      </div>

      <!-- ── Leave Applications (with deduction info) ───────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-800">Leave Applications — {{ year }}</h2>
          <p class="text-xs text-slate-400 mt-0.5">Your leave requests and the credits deducted on approval.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Control No.</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Leave Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Dates</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Applied</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Deducted</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">LWOP</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="app in applications" :key="app.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ app.control_no ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-700">{{ app.leave_type?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-600 whitespace-nowrap text-xs">
                  {{ fmtDate(app.date_from) }} – {{ fmtDate(app.date_to) }}
                </td>
                <td class="px-4 py-3 text-center font-medium text-slate-700">{{ app.days_applied }}</td>
                <td class="px-4 py-3 text-center">
                  <span v-if="Number(app.days_deducted) > 0" class="font-medium text-red-600">
                    {{ app.days_deducted }}
                  </span>
                  <span v-else class="text-slate-400 text-xs">—</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span v-if="app.is_without_pay"
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">
                    LWOP
                  </span>
                  <span v-else class="text-slate-400 text-xs">—</span>
                </td>
                <td class="px-4 py-3">
                  <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold', statusClass(app.status)]">
                    {{ app.status }}
                  </span>
                </td>
              </tr>
              <tr v-if="!applications.length">
                <td colspan="7" class="py-10 text-center text-slate-400 text-sm">No leave applications for {{ year }}.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Transaction Ledger ──────────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-800">Credit Transaction History — {{ year }}</h2>
          <p class="text-xs text-slate-400 mt-0.5">Full audit trail of all changes to your leave credit balances.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Leave Type</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Amount</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Balance After</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Remarks</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Recorded By</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="tx in txData" :key="tx.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">{{ fmtDatetime(tx.created_at) }}</td>
                <td class="px-4 py-3">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold', txTypeClass(tx.type)]">
                    {{ tx.type }}
                  </span>
                </td>
                <td class="px-4 py-3 text-slate-600">{{ tx.leave_type?.code ?? '—' }}</td>
                <td class="px-4 py-3 text-right font-mono font-semibold"
                    :class="Number(tx.amount) >= 0 ? 'text-emerald-600' : 'text-red-600'">
                  {{ signedAmount(tx.amount) }}
                </td>
                <td class="px-4 py-3 text-right font-mono text-slate-700">{{ Number(tx.balance_after).toFixed(4) }}</td>
                <td class="px-4 py-3 text-xs text-slate-500 max-w-xs truncate" :title="tx.remarks">{{ tx.remarks ?? '—' }}</td>
                <td class="px-4 py-3 text-xs text-slate-500">{{ tx.recorder?.name ?? 'System' }}</td>
              </tr>
              <tr v-if="txData.length === 0">
                <td colspan="7" class="py-10 text-center text-slate-400 text-sm">No transactions recorded for {{ year }}.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Ledger pagination -->
        <div v-if="lastPage > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ curPage }} of {{ lastPage }}</span>
          <div class="flex gap-2">
            <button @click="goToPage(curPage - 1)" :disabled="curPage === 1"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-sm font-medium disabled:opacity-50">Prev</button>
            <button @click="goToPage(curPage + 1)" :disabled="curPage === lastPage"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-sm font-medium disabled:opacity-50">Next</button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
