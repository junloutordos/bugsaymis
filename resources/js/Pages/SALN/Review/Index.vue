<template>
  <Head title="SALN Review Queue" />
  <AdminLayout title="SALN Review Queue">
    <div class="space-y-5">

      <!-- Header -->
      <div>
        <h1 class="text-xl font-semibold text-slate-800">SALN Review Queue</h1>
        <p class="text-sm text-slate-500 mt-0.5">Submitted SALN records pending committee review</p>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>

      <!-- Stats bar -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div v-for="stat in stats" :key="stat.label"
          class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold" :class="stat.color">{{ stat.value }}</p>
          <p class="text-xs text-slate-500 mt-0.5">{{ stat.label }}</p>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="records.data.length === 0"
        class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <ClipboardDocumentCheckIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">No pending SALN submissions</p>
        <p class="text-xs text-slate-400 mt-1">All SALN records have been reviewed.</p>
      </div>

      <!-- Records table -->
      <div v-else class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Year</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Net Worth</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Submitted</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="rec in records.data" :key="rec.id" class="hover:bg-slate-50 transition-colors">
              <td class="px-5 py-3">
                <div>
                  <p class="font-medium text-slate-800">{{ rec.user?.name }}</p>
                  <p class="text-xs text-slate-400">{{ rec.user?.employee_id ?? rec.user?.email }}</p>
                </div>
              </td>
              <td class="px-5 py-3 font-semibold text-slate-700">{{ rec.year }}</td>
              <td class="px-5 py-3">
                <span :class="rec.net_worth >= 0 ? 'text-emerald-700' : 'text-red-600'" class="font-medium">
                  {{ fmtMoney(rec.net_worth) }}
                </span>
              </td>
              <td class="px-5 py-3">
                <span :class="statusBadge(rec.status)"
                  class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold">
                  {{ rec.status_label }}
                </span>
              </td>
              <td class="px-5 py-3 text-slate-500 text-xs">{{ fmtDate(rec.submitted_at) }}</td>
              <td class="px-5 py-3 text-right">
                <Link :href="route('saln.review.show', rec.id)"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors">
                  <EyeIcon class="h-3.5 w-3.5" />
                  Review
                </Link>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <PaginationControl :links="records.links" :total="records.total" />
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import {
  CheckCircleIcon, ClipboardDocumentCheckIcon, EyeIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  records: Object, // paginated
  filters: Object,
})

const stats = computed(() => {
  const data = props.records?.data ?? []
  const submitted    = data.filter(r => r.status === 'submitted').length
  const under_review = data.filter(r => r.status === 'under_review').length
  return [
    { label: 'Total Pending',   value: props.records?.total ?? 0,    color: 'text-slate-700' },
    { label: 'Submitted',       value: submitted,                     color: 'text-blue-600' },
    { label: 'Under Review',    value: under_review,                  color: 'text-amber-600' },
    { label: 'This Page',       value: data.length,                   color: 'text-indigo-600' },
  ]
})

const statusBadge = (s) => ({
  submitted:    'bg-blue-100 text-blue-700',
  under_review: 'bg-amber-100 text-amber-700',
}[s] ?? 'bg-slate-100 text-slate-600')

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
const fmtMoney = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(v ?? 0)
</script>
