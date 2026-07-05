<template>
  <Head title="SALN Review Queue" />
  <AdminLayout title="SALN Review Queue">
    <div class="space-y-5">

      <AppPageHeader title="SALN Review Queue" subtitle="Submitted SALN records pending committee review" />

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
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

      <!-- Records table -->
      <AppTable :is-empty="!records.data?.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee</th>
            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Year</th>
            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Net Worth</th>
            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Submitted</th>
            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Action</th>
          </tr>
        </template>

        <tr v-for="rec in records.data" :key="rec.id" class="hover:bg-slate-50/60">
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
            <AppBadge :color="statusBadge(rec.status)">{{ rec.status_label }}</AppBadge>
          </td>
          <td class="px-5 py-3 text-slate-500 text-xs">{{ fmtDate(rec.submitted_at) }}</td>
          <td class="px-5 py-3 text-right">
            <AppButton as="link" :href="route('saln.review.show', rec.id)" variant="secondary" size="sm">
              <EyeIcon class="h-3.5 w-3.5" />
              Review
            </AppButton>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="rec in records.data" :key="rec.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ rec.user?.name }}</p>
                <p class="text-xs text-slate-400">{{ rec.user?.employee_id ?? rec.user?.email }}</p>
              </div>
              <AppBadge :color="statusBadge(rec.status)">{{ rec.status_label }}</AppBadge>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-500">{{ rec.year }}</span>
              <span :class="rec.net_worth >= 0 ? 'text-emerald-700' : 'text-red-600'" class="font-medium">
                {{ fmtMoney(rec.net_worth) }}
              </span>
            </div>
            <div class="flex items-center justify-between pt-1">
              <span class="text-xs text-slate-400">Submitted {{ fmtDate(rec.submitted_at) }}</span>
              <AppButton as="link" :href="route('saln.review.show', rec.id)" variant="secondary" size="sm">
                <EyeIcon class="h-3.5 w-3.5" />
                Review
              </AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No pending SALN submissions" subtitle="All SALN records have been reviewed." :icon="ClipboardDocumentCheckIcon" />
        </template>

        <template #footer>
          <PaginationControl
            :links="records.links"
            :current-page="records.current_page"
            :total-pages="records.last_page"
            :total="records.total"
          />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
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
  submitted:    'blue',
  under_review: 'amber',
}[s] ?? 'slate')

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
const fmtMoney = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(v ?? 0)
</script>
