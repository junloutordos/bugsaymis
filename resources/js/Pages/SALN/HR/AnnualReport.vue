<template>
  <Head :title="`SALN Annual Report — ${selectedYear}`" />
  <AdminLayout title="SALN Annual Compliance Report">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">SALN Annual Compliance Report</h1>
          <p class="text-sm text-slate-500 mt-0.5">Summary of filing status per calendar year</p>
        </div>
        <div class="flex gap-2 items-center">
          <select v-model="selectedYear" @change="changeYear"
            class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
          <Link :href="route('saln.hr.index')"
            class="px-4 py-2 text-sm border border-slate-200 text-slate-600 rounded-lg hover:bg-slate-50 transition-colors">
            All Records
          </Link>
        </div>
      </div>

      <!-- Summary cards -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-indigo-600">{{ summary.filed }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Filed</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-emerald-600">{{ summary.approved }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Approved</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-amber-600">{{ summary.review }}</p>
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
          <p class="text-2xl font-bold text-red-500">{{ summary.nonFilers }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Non-Filers</p>
        </div>
      </div>

      <!-- Compliance gauge -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-2">
          <p class="text-sm font-semibold text-slate-700">Compliance Rate</p>
          <p class="text-sm font-bold"
            :class="complianceRate >= 80 ? 'text-emerald-600' : complianceRate >= 50 ? 'text-amber-600' : 'text-red-600'">
            {{ complianceRate }}%
          </p>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-3">
          <div class="h-3 rounded-full transition-all duration-500"
            :class="complianceRate >= 80 ? 'bg-emerald-500' : complianceRate >= 50 ? 'bg-amber-500' : 'bg-red-500'"
            :style="{ width: complianceRate + '%' }" />
        </div>
        <p class="text-xs text-slate-400 mt-2">
          {{ summary.filers }} of {{ totalEmployees }} employees have filed SALN for {{ selectedYear }}.
          <span v-if="summary.nonFilers > 0" class="text-red-500 font-medium">
            {{ summary.nonFilers }} non-filers.
          </span>
        </p>
      </div>

      <!-- Detail table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700">Per-Record Status — {{ selectedYear }}</h2>
          <span class="text-xs text-slate-400">{{ detail.length }} records</span>
        </div>
        <div v-if="detail.length === 0" class="py-12 text-center text-sm text-slate-400">
          No SALN records for {{ selectedYear }}.
        </div>
        <table v-else class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Net Worth</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Filed Date</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="row in detail" :key="row.id" class="hover:bg-slate-50 transition-colors">
              <td class="px-5 py-3">
                <Link :href="route('saln.hr.employee', row.user?.id)"
                  class="font-medium text-indigo-600 hover:text-indigo-800">
                  {{ row.user?.name }}
                </Link>
              </td>
              <td class="px-5 py-3">
                <span :class="statusBadge(row.status)"
                  class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold">
                  {{ row.status_label }}
                </span>
              </td>
              <td class="px-5 py-3 text-right font-medium"
                :class="row.net_worth >= 0 ? 'text-emerald-700' : 'text-red-600'">
                {{ fmtMoney(row.net_worth) }}
              </td>
              <td class="px-5 py-3 text-xs text-slate-500">
                {{ fmtDate(row.filed_at) }}
              </td>
              <td class="px-5 py-3 text-right">
                <Link :href="route('saln.show', row.id)"
                  class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors inline-flex"
                  title="View">
                  <EyeIcon class="h-4 w-4" />
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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
  draft:        'bg-slate-100 text-slate-600',
  submitted:    'bg-blue-100 text-blue-700',
  under_review: 'bg-amber-100 text-amber-700',
  approved:     'bg-emerald-100 text-emerald-700',
  returned:     'bg-red-100 text-red-600',
  filed:        'bg-indigo-100 text-indigo-700',
}[s] ?? 'bg-slate-100 text-slate-600')

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
const fmtMoney = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(v ?? 0)
</script>
