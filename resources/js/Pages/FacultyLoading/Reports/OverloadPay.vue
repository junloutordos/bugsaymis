<template>
  <Head title="Overload Pay Report" />
  <AdminLayout title="Overload Pay Report">
    <div class="space-y-5">

      <AppPageHeader title="Overload Pay Report" subtitle="PHTR-based overload pay computations per faculty and term">
        <template #actions>
          <AppButton as="a" :href="exportUrl" target="_blank" variant="secondary">
            <ArrowDownTrayIcon class="h-4 w-4" /> Export CSV
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <div class="flex-1 min-w-[180px]">
          <AppSelect v-model="filters.term_id" label="Academic Term" placeholder="All Terms" @change="applyFilters">
            <option v-for="t in terms" :key="t.id" :value="t.id">
              {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
            </option>
          </AppSelect>
        </div>
        <div class="w-44">
          <AppSelect v-model="filters.status" label="Status" placeholder="All Statuses" @change="applyFilters">
            <option value="for_approval">For Approval</option>
            <option value="approved">Approved</option>
            <option value="paid">Paid</option>
            <option value="rejected">Rejected</option>
          </AppSelect>
        </div>
        <template #actions>
          <AppButton v-if="hasActiveFilter" size="sm" variant="secondary" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Grand Total banner -->
      <div v-if="computations.length" class="bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3 flex items-center justify-between">
        <div>
          <p class="text-xs font-semibold text-indigo-700 uppercase tracking-wide">Total Overload Pay</p>
          <p class="text-2xl font-bold text-indigo-900 mt-0.5">{{ phpFmt(grandTotal) }}</p>
        </div>
        <div class="text-right">
          <p class="text-xs text-indigo-600">{{ computations.length }} computation{{ computations.length !== 1 ? 's' : '' }}</p>
          <p class="text-xs text-indigo-500 mt-0.5">
            {{ computations.filter(c => c.status === 'paid').length }} paid ·
            {{ computations.filter(c => c.status === 'approved').length }} approved
          </p>
        </div>
      </div>

      <!-- Table -->
      <AppTable :is-empty="!computations.length" :skeleton-cols="11">
        <template #head>
          <tr>
            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-8">#</th>
            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Faculty</th>
            <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">SG</th>
            <th class="px-4 py-2.5 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Annual Rate</th>
            <th class="px-4 py-2.5 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">PHTR</th>
            <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">OL Units</th>
            <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Hrs/Week</th>
            <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Weeks</th>
            <th class="px-4 py-2.5 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Pay</th>
            <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Approved</th>
          </tr>
        </template>

        <tr v-for="(c, i) in computations" :key="c.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-2.5 text-slate-400 text-xs">{{ i + 1 }}</td>
          <td class="px-4 py-2.5">
            <p class="font-medium text-slate-800">{{ c.faculty_name }}</p>
            <p v-if="c.position" class="text-xs text-slate-400">{{ c.position }}</p>
          </td>
          <td class="px-4 py-2.5 text-center text-slate-600">{{ c.salary_grade ?? '—' }}</td>
          <td class="px-4 py-2.5 text-right text-slate-600 font-mono text-xs">{{ phpFmt(c.annual_rate) }}</td>
          <td class="px-4 py-2.5 text-right text-success-700 font-mono text-xs">{{ phpFmt(c.phtr) }}</td>
          <td class="px-4 py-2.5 text-center text-slate-600">{{ c.overload_units }}</td>
          <td class="px-4 py-2.5 text-center text-slate-600">{{ c.overload_hours }}</td>
          <td class="px-4 py-2.5 text-center text-slate-600">{{ c.term_weeks }}</td>
          <td class="px-4 py-2.5 text-right font-semibold text-slate-800">{{ phpFmt(c.total_overload_pay) }}</td>
          <td class="px-4 py-2.5 text-center">
            <AppBadge :color="statusColor(c.status)">{{ statusLabel(c.status) }}</AppBadge>
          </td>
          <td class="px-4 py-2.5 text-center text-xs text-slate-500">
            {{ c.approved_at ?? '—' }}
          </td>
        </tr>

        <!-- Grand total row -->
        <tr v-if="computations.length" class="bg-slate-50/80 border-t border-slate-200">
          <td colspan="8" class="px-4 py-2.5 text-right text-xs font-semibold text-slate-600 uppercase tracking-wide">
            Grand Total
          </td>
          <td class="px-4 py-2.5 text-right font-bold text-slate-800">{{ phpFmt(grandTotal) }}</td>
          <td colspan="2"></td>
        </tr>

        <template #mobileCard>
          <div v-for="(c, i) in computations" :key="c.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ c.faculty_name }}</p>
                <p v-if="c.position" class="text-xs text-slate-400">{{ c.position }}</p>
              </div>
              <AppBadge :color="statusColor(c.status)">{{ statusLabel(c.status) }}</AppBadge>
            </div>
            <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-500">
              <span>SG {{ c.salary_grade ?? '—' }}</span>
              <span>{{ c.overload_units }} OL units · {{ c.overload_hours }}h × {{ c.term_weeks }}w</span>
              <span>PHTR {{ phpFmt(c.phtr) }}</span>
              <span>Approved {{ c.approved_at ?? '—' }}</span>
            </div>
            <div class="flex justify-between text-xs pt-1">
              <span class="text-slate-400">Annual {{ phpFmt(c.annual_rate) }}</span>
              <span class="font-semibold text-slate-800">Total {{ phpFmt(c.total_overload_pay) }}</span>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No overload computations found" subtitle="Try adjusting the filters above." :icon="BanknotesIcon" />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { ArrowDownTrayIcon, BanknotesIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  computations: { type: Array,  default: () => [] },
  terms:        { type: Array,  default: () => [] },
  currentTerm:  { type: Object, default: null },
  filters:      { type: Object, default: () => ({}) },
  grandTotal:   { type: Number, default: 0 },
})

const filters = ref({
  term_id: props.filters.term_id ?? props.currentTerm?.id ?? '',
  status:  props.filters.status ?? '',
})

const hasActiveFilter = computed(() =>
  filters.value.term_id !== (props.currentTerm?.id ?? '') ||
  filters.value.status !== ''
)

const exportUrl = computed(() => {
  const p = new URLSearchParams()
  if (filters.value.term_id) p.set('term_id', filters.value.term_id)
  if (filters.value.status)  p.set('status',  filters.value.status)
  return route('faculty-loading.reports.overload-pay.export') + (p.toString() ? '?' + p.toString() : '')
})

function applyFilters() {
  router.get(route('faculty-loading.reports.overload-pay'), {
    term_id: filters.value.term_id || undefined,
    status:  filters.value.status  || undefined,
  }, { preserveState: true, replace: true })
}

function clearFilters() {
  filters.value = { term_id: props.currentTerm?.id ?? '', status: '' }
  applyFilters()
}

function phpFmt(val) {
  if (val == null) return '—'
  return '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function statusLabel(s) {
  return {
    for_approval: 'For Approval',
    approved:     'Approved',
    paid:         'Paid',
    rejected:     'Rejected',
    pending:      'Pending',
  }[s] ?? s
}

function statusColor(s) {
  return {
    for_approval: 'amber',
    approved:     'green',
    paid:         'indigo',
    rejected:     'red',
    pending:      'slate',
  }[s] ?? 'slate'
}
</script>
