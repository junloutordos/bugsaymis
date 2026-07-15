<template>
  <Head title="All PDS" />
  <AdminLayout title="All PDS">
    <div class="space-y-5">

      <AppPageHeader title="Personal Data Sheets" subtitle="HR view — status of every employee's PDS" />

      <!-- Summary strip -->
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
          <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Total</p>
          <p class="text-xl font-semibold text-slate-800">{{ counts.total }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
          <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Submitted</p>
          <p class="text-xl font-semibold text-success-700">{{ counts.submitted }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
          <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">In Progress</p>
          <p class="text-xl font-semibold text-slate-600">{{ counts.in_progress }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
          <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Needs Annual Update</p>
          <p class="text-xl font-semibold text-warning-700">{{ counts.overdue }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
          <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Not Started</p>
          <p class="text-xl font-semibold text-danger-700">{{ counts.not_started }}</p>
        </div>
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <input v-model="filters.search" type="text" placeholder="Search name or email…"
          @input="applyFilters"
          class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        <select v-model="filters.status" @change="applyFilters"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Statuses</option>
          <option value="not_started">Not Started</option>
          <option value="in_progress">In Progress</option>
          <option value="submitted">Submitted</option>
          <option value="overdue">Needs Annual Update</option>
        </select>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="employees.data.length === 0" :skeleton-cols="4">
        <template #head>
          <tr>
            <th class="px-5 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Employee</th>
            <th class="px-5 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Email</th>
            <th class="px-5 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-5 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Action</th>
          </tr>
        </template>

        <tr v-for="emp in employees.data" :key="emp.id" class="hover:bg-indigo-50/40">
          <td class="px-5 py-3 text-sm font-medium text-slate-700">{{ emp.name }}</td>
          <td class="px-5 py-3 text-sm text-slate-500">{{ emp.email }}</td>
          <td class="px-5 py-3">
            <AppBadge :color="pdsStatus(emp).color">{{ pdsStatus(emp).label }}</AppBadge>
          </td>
          <td class="px-5 py-3 text-right">
            <AppButton v-if="emp.pds" as="link" size="sm" variant="secondary" :href="route('pds.edit', emp.pds.id)">View</AppButton>
            <span v-else class="text-xs text-slate-400">—</span>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="emp in employees.data" :key="emp.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ emp.name }}</p>
                <p class="text-xs text-slate-500">{{ emp.email }}</p>
              </div>
              <AppBadge :color="pdsStatus(emp).color">{{ pdsStatus(emp).label }}</AppBadge>
            </div>
            <div class="flex justify-end">
              <AppButton v-if="emp.pds" as="link" size="sm" variant="secondary" :href="route('pds.edit', emp.pds.id)">View</AppButton>
              <span v-else class="text-xs text-slate-400">Not started</span>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No employees found" subtitle="Try adjusting your filters." />
        </template>

        <template #footer>
          <PaginationControl
            :links="employees.links"
            :current-page="employees.current_page"
            :total-pages="employees.last_page"
            :total="employees.total" />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

const props = defineProps({
  employees: Object, // paginated
  counts: Object,
  filters: Object,
})

const filters = reactive({
  search: props.filters?.search ?? '',
  status: props.filters?.status ?? '',
})

let debounceTimer = null
function applyFilters() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    router.get(route('pds.index'), filters, { preserveState: true, replace: true })
  }, 400)
}

function pdsStatus(emp) {
  if (!emp.pds) return { label: 'Not Started', color: 'red' }
  if (!emp.pds.submitted_at) return { label: 'In Progress', color: 'slate' }

  const submitted = new Date(emp.pds.submitted_at)
  const oneYearAgo = new Date()
  oneYearAgo.setFullYear(oneYearAgo.getFullYear() - 1)

  if (submitted < oneYearAgo) return { label: 'Needs Annual Update', color: 'amber' }

  return { label: `Submitted ${fmtDate(emp.pds.submitted_at)}`, color: 'green' }
}

function fmtDate(d) {
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>
