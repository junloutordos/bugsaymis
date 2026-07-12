<script setup>
import { ref } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppButton from '@/Components/AppButton.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  applications: Object,
  counts:       Object,
  filters:      Object,
})

const TABS = [
  { key: 'pending',      label: 'Pending' },
  { key: 'under_review', label: 'Under Review' },
  { key: 'approved',     label: 'Approved' },
  { key: 'rejected',     label: 'Rejected' },
]

const search = ref(props.filters.search ?? '')

function applyFilter(status) {
  router.get(route('registrar.enrollment-applications.index'), { status, search: search.value }, { preserveState: true })
}

function applySearch() {
  router.get(route('registrar.enrollment-applications.index'),
    { status: props.filters.status, search: search.value },
    { preserveState: true })
}

function statusColor(status) {
  const map = { pending: 'amber', under_review: 'blue', approved: 'green', rejected: 'red' }
  return map[status] ?? 'slate'
}
const statusLabel = {
  pending:      'Pending',
  under_review: 'Under Review',
  approved:     'Approved',
  rejected:     'Rejected',
}

function fmt(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<template>
  <Head title="Enrollment Applications" />
  <AdminLayout title="Enrollment Applications">
    <div class="space-y-5">

      <AppPageHeader title="Enrollment Applications" subtitle="Review and process student enrollment applications." />

      <!-- Tabs -->
      <div class="flex gap-1 border-b border-slate-200">
        <button v-for="tab in TABS" :key="tab.key"
                @click="applyFilter(tab.key)"
                class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition"
                :class="filters.status === tab.key
                  ? 'border-indigo-600 text-indigo-600'
                  : 'border-transparent text-slate-500 hover:text-slate-700'">
          {{ tab.label }}
          <span v-if="counts[tab.key]"
                class="ml-1.5 rounded-full px-2 py-0.5 text-xs"
                :class="filters.status === tab.key ? 'bg-indigo-100 text-indigo-600' : 'bg-slate-100 text-slate-500'">
            {{ counts[tab.key] }}
          </span>
        </button>
      </div>

      <!-- Search -->
      <AppFilterBar>
        <div class="relative w-full sm:w-96">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input
            v-model="search"
            @keyup.enter="applySearch"
            placeholder="Search name, reference, LRN…"
            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>
        <template #actions>
          <AppButton size="sm" @click="applySearch">Search</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!applications.data?.length" :skeleton-cols="8">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Reference</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Applicant</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Grade</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">LRN</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Previous School</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Submitted</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="app in applications.data" :key="app.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 font-mono text-xs text-slate-600 font-medium">{{ app.reference_no }}</td>
          <td class="px-4 py-3">
            <div class="font-medium text-slate-800">{{ app.lastname }}, {{ app.firstname }}</div>
            <div v-if="app.middlename" class="text-xs text-slate-400">{{ app.middlename }}</div>
          </td>
          <td class="px-4 py-3 text-slate-600">Grade {{ app.grade_level }}</td>
          <td class="px-4 py-3 text-slate-500 text-xs font-mono">{{ app.lrn || '—' }}</td>
          <td class="px-4 py-3 text-slate-500 text-xs">{{ app.previous_school || '—' }}</td>
          <td class="px-4 py-3 text-slate-500 text-xs">{{ fmt(app.created_at) }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColor(app.status)">{{ statusLabel[app.status] }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <Link :href="route('registrar.enrollment-applications.show', app.id)"
                  class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
              Review →
            </Link>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="app in applications.data" :key="app.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-mono text-xs text-slate-500">{{ app.reference_no }}</p>
                <p class="font-medium text-slate-800">{{ app.lastname }}, {{ app.firstname }}</p>
                <p class="text-xs text-slate-500">Grade {{ app.grade_level }} &middot; LRN {{ app.lrn || '—' }}</p>
              </div>
              <AppBadge :color="statusColor(app.status)">{{ statusLabel[app.status] }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">{{ app.previous_school || '—' }}</p>
            <div class="flex items-center justify-between pt-1">
              <span class="text-xs text-slate-400">Submitted {{ fmt(app.created_at) }}</span>
              <Link :href="route('registrar.enrollment-applications.show', app.id)"
                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Review →</Link>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState :title="'No ' + filters.status + ' applications.'" />
        </template>

        <template #footer>
          <PaginationControl
            :links="applications.links"
            :current-page="applications.current_page"
            :total-pages="applications.last_page"
            :total="applications.total"
          />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>
