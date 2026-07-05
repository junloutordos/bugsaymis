<template>
  <Head :title="`SALN Annual Report — ${selectedYear}`" />
  <AdminLayout title="SALN Annual Compliance Report">
    <div class="space-y-5">

      <AppPageHeader title="SALN Annual Compliance Report" subtitle="Summary of filing status per calendar year">
        <template #actions>
          <select v-model="selectedYear" @change="changeYear"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
          <AppButton as="link" variant="secondary" :href="route('saln.hr.index')">All Records</AppButton>
        </template>
      </AppPageHeader>

      <!-- Summary cards -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-indigo-600">{{ summary.filed }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Filed</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-success-600">{{ summary.approved }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Approved</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-warning-600">{{ summary.review }}</p>
          <p class="text-xs text-slate-500 mt-0.5">For Review</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-slate-500">{{ summary.draft }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Draft</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-slate-700">{{ summary.filers }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Total Filed</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-danger-500">{{ summary.nonFilers }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Non-Filers</p>
        </div>
      </div>

      <!-- Compliance gauge -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-2">
          <p class="text-sm font-semibold text-slate-700">Compliance Rate</p>
          <p class="text-sm font-bold"
            :class="complianceRate >= 80 ? 'text-success-600' : complianceRate >= 50 ? 'text-warning-600' : 'text-danger-600'">
            {{ complianceRate }}%
          </p>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-3">
          <div class="h-3 rounded-full transition-all duration-500"
            :class="complianceRate >= 80 ? 'bg-success-500' : complianceRate >= 50 ? 'bg-warning-500' : 'bg-danger-500'"
            :style="{ width: complianceRate + '%' }" />
        </div>
        <p class="text-xs text-slate-400 mt-2">
          {{ summary.filers }} of {{ totalEmployees }} employees have filed SALN for {{ selectedYear }}.
          <span v-if="summary.nonFilers > 0" class="text-danger-500 font-medium">
            {{ summary.nonFilers }} non-filers.
          </span>
        </p>
      </div>

      <!-- Detail table -->
      <AppTable :is-empty="detail.length === 0" :skeleton-cols="5">
        <template #head>
          <tr>
            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee</th>
            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Net Worth</th>
            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Filed Date</th>
            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
          </tr>
        </template>

        <tr v-for="row in detail" :key="row.id" class="hover:bg-slate-50/60">
          <td class="px-5 py-3">
            <Link :href="route('saln.hr.employee', row.user?.id)"
              class="font-medium text-indigo-600 hover:text-indigo-800">
              {{ row.user?.name }}
            </Link>
          </td>
          <td class="px-5 py-3">
            <AppBadge :color="statusBadge(row.status)">{{ row.status_label }}</AppBadge>
          </td>
          <td class="px-5 py-3 text-right font-medium"
            :class="row.net_worth >= 0 ? 'text-success-700' : 'text-danger-600'">
            {{ fmtMoney(row.net_worth) }}
          </td>
          <td class="px-5 py-3 text-xs text-slate-500">
            {{ fmtDate(row.filed_at) }}
          </td>
          <td class="px-5 py-3 text-right">
            <Link :href="route('saln.show', row.id)"
              class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
              title="View">
              <EyeIcon class="h-4 w-4" />
            </Link>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="row in detail" :key="row.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <Link :href="route('saln.hr.employee', row.user?.id)" class="font-medium text-indigo-600 hover:text-indigo-800">
                {{ row.user?.name }}
              </Link>
              <AppBadge :color="statusBadge(row.status)">{{ row.status_label }}</AppBadge>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-500">
              <span>Filed {{ fmtDate(row.filed_at) }}</span>
              <span class="font-medium" :class="row.net_worth >= 0 ? 'text-success-700' : 'text-danger-600'">
                {{ fmtMoney(row.net_worth) }}
              </span>
            </div>
            <div class="flex justify-end pt-1">
              <Link :href="route('saln.show', row.id)"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                title="View">
                <EyeIcon class="h-4 w-4" />
              </Link>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState :title="`No SALN records for ${selectedYear}.`" />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { EyeIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  year: Number,
  summary: Object,    // { filed, approved, review, draft, nonFilers, filers }
  detail: Array,      // [{ id, user: { id, name }, status, status_label, net_worth, filed_at, submitted_at }]
  totalEmployees: Number,
})

const selectedYear = ref(props.year)
const yearOptions = Array.from({ length: 10 }, (_, i) => new Date().getFullYear() - i)

const complianceRate = computed(() => {
  if (!props.totalEmployees) return 0
  return Math.round(((props.summary?.filers ?? 0) / props.totalEmployees) * 100)
})

function changeYear() {
  router.get(route('saln.hr.reports.annual'), { year: selectedYear.value }, { preserveState: false })
}

const statusBadge = (s) => ({
  draft:        'slate',
  submitted:    'blue',
  under_review: 'amber',
  approved:     'green',
  returned:     'red',
  filed:        'indigo',
}[s] ?? 'slate')

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
const fmtMoney = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(v ?? 0)
</script>
