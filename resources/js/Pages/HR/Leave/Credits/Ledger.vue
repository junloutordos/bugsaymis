<script setup>
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

const props = defineProps({
  employee:     Object,
  transactions: Object,
  balances:     Object,
  year:         Number,
  years:        Array,
})

const changeYear = (y) => {
  router.get(route('hr.leave-credits.ledger', props.employee.id), { year: y }, { preserveState: false })
}

const goToPage = (p) => {
  router.get(route('hr.leave-credits.ledger', props.employee.id), { year: props.year, page: p }, {
    preserveState: true, replace: true,
  })
}

// ── Balance cards ─────────────────────────────────────────────────────────────
const balanceCards = computed(() => {
  return Object.entries(props.balances ?? {}).map(([code, b]) => ({
    code,
    isService: !!b.is_service_credit,
    balance:     Number(b.balance ?? 0),
    earned:      b.earned !== undefined ? Number(b.earned) : null,
    carriedOver: b.carried_over !== undefined ? Number(b.carried_over) : null,
    used:        b.used !== undefined ? Number(b.used) : null,
    forfeited:   b.forfeited !== undefined ? Number(b.forfeited) : null,
    monetized:   b.monetized !== undefined ? Number(b.monetized) : null,
  }))
})

const balanceColor = (bal) => {
  if (bal <= 0) return 'text-red-600'
  if (bal <= 5) return 'text-amber-600'
  return 'text-emerald-600'
}

const balanceBg = (bal) => {
  if (bal <= 0) return 'from-red-50 to-red-100/60 border-red-200'
  if (bal <= 5) return 'from-amber-50 to-amber-100/60 border-amber-200'
  return 'from-emerald-50 to-emerald-100/60 border-emerald-200'
}

const txTypeBadgeColor = (type) => ({
  INITIAL:      'purple',
  ACCRUAL:      'green',
  DEDUCTION:    'red',
  ADJUSTMENT:   'blue',
  RESTORATION:  'amber',
  MONETIZATION: 'orange',
  CARRYOVER:    'indigo',
  FORFEITURE:   'slate',
})[type] ?? 'slate'

const signed = (n) => { const v = Number(n); return v >= 0 ? `+${v.toFixed(4)}` : v.toFixed(4) }
const fmtDt  = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'

const txData   = computed(() => props.transactions?.data ?? [])
const curPage  = computed(() => props.transactions?.current_page ?? 1)
const lastPage = computed(() => props.transactions?.last_page ?? 1)
const total    = computed(() => props.transactions?.total ?? 0)
</script>

<template>
  <Head :title="`Leave Ledger — ${employee?.name ?? ''}`" />
  <AdminLayout :title="`Leave Ledger — ${employee?.name ?? ''}`">
    <div class="space-y-5">

      <AppPageHeader :title="`Leave Credit Ledger — ${employee?.name ?? ''}`"
                      :subtitle="`${employee?.badge_id ?? ''} · ${employee?.position ?? ''} · ${employee?.emp_category ?? ''}`">
        <template #actions>
          <Link :href="route('hr.reports.leave-credits.utilization')"
                class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 font-medium">
            <ArrowLeftIcon class="w-4 h-4" />
            Back to Utilization Report
          </Link>
          <label class="text-sm text-slate-500 font-medium ml-2">Year:</label>
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
              <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">{{ card.code }}</p>
            </div>
            <AppBadge v-if="card.isService" color="blue">Service Credit</AppBadge>
          </div>

          <p :class="['text-4xl font-extrabold mt-3 tabular-nums', balanceColor(card.balance)]">
            {{ card.balance.toFixed(2) }}
            <span class="text-base font-medium text-slate-500 ml-1">days</span>
          </p>

          <div v-if="!card.isService" class="mt-4 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-slate-500">
            <div class="flex justify-between">
              <span>Earned</span>
              <span class="font-medium text-slate-700">{{ (card.earned ?? 0).toFixed(4) }}</span>
            </div>
            <div class="flex justify-between">
              <span>Carried over</span>
              <span class="font-medium text-slate-700">{{ (card.carriedOver ?? 0).toFixed(4) }}</span>
            </div>
            <div class="flex justify-between">
              <span>Used</span>
              <span class="font-medium text-slate-700">{{ (card.used ?? 0).toFixed(4) }}</span>
            </div>
            <div class="flex justify-between">
              <span>Forfeited</span>
              <span class="font-medium text-slate-700">{{ (card.forfeited ?? 0).toFixed(4) }}</span>
            </div>
            <div v-if="(card.monetized ?? 0) > 0" class="flex justify-between col-span-2">
              <span>Monetized</span>
              <span class="font-medium text-slate-700">{{ card.monetized.toFixed(4) }}</span>
            </div>
          </div>

          <p v-if="card.isService" class="mt-3 text-xs text-slate-400">
            Sum of all approved, unconsumed, non-expired service credit records.
          </p>
        </div>
      </div>

      <EmptyState v-else :title="`No leave credit records found for ${year}.`" />

      <!-- ── Transaction Ledger ──────────────────────────────────────────────── -->
      <AppCard :padded="false" title="Credit Transaction History" :subtitle="`${year} — Full audit trail of all changes to this employee's leave credit balances.`">
        <AppTable :is-empty="txData.length === 0" :skeleton-cols="7" :card="false">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Type</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Leave Type</th>
              <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Amount</th>
              <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Balance After</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Remarks</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Recorded By</th>
            </tr>
          </template>

          <tr v-for="tx in txData" :key="tx.id" class="hover:bg-indigo-50/40">
            <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">{{ fmtDt(tx.created_at) }}</td>
            <td class="px-4 py-3">
              <AppBadge :color="txTypeBadgeColor(tx.type)">{{ tx.type }}</AppBadge>
            </td>
            <td class="px-4 py-3 text-slate-600 text-xs">{{ tx.leave_type?.code ?? '—' }}</td>
            <td class="px-4 py-3 text-right font-mono font-semibold text-xs"
                :class="Number(tx.amount) >= 0 ? 'text-emerald-600' : 'text-red-600'">
              {{ signed(tx.amount) }}
            </td>
            <td class="px-4 py-3 text-right font-mono text-slate-700 text-xs">{{ Number(tx.balance_after).toFixed(4) }}</td>
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
              :total="total"
              @prev="goToPage(curPage - 1)"
              @next="goToPage(curPage + 1)"
              @page="goToPage"
            />
          </template>
        </AppTable>
      </AppCard>

    </div>
  </AdminLayout>
</template>
