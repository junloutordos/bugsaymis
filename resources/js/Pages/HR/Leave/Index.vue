<template>
  <Head title="Leave Applications" />
  <AdminLayout title="Leave Applications">
    <div class="space-y-5">

      <AppPageHeader title="Leave Applications" :subtitle="canApprove ? 'All employee leave requests' : 'Your leave applications'">
        <template #actions>
          <AppButton v-if="canFile" as="link" :href="route('hr.leave.create')">+ File Leave</AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.success }}
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative w-full sm:w-96">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input
            v-model="search"
            type="text"
            placeholder="Search control no., employee, leave type, status, or date..."
            @keydown.enter.prevent="applyFilters"
            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>

        <select v-model="activeStatus"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>

        <template #actions>
          <AppButton size="sm" @click="applyFilters">Search</AppButton>
          <AppButton v-if="search || activeStatus" size="sm" variant="secondary" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!applications.data?.length" :skeleton-cols="canApprove ? 8 : 7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Control No.</th>
            <th v-if="canApprove" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Leave Type</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Dates</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Days</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Filed</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="app in applications.data" :key="app.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ app.control_no ?? '—' }}</td>
          <td v-if="canApprove" class="px-4 py-3">
            <div class="font-medium text-slate-800">{{ app.user?.name }}</div>
          </td>
          <td class="px-4 py-3 text-slate-700">{{ app.leave_type?.name }}</td>
          <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
            {{ fmtDate(app.date_from) }} – {{ fmtDate(app.date_to) }}
          </td>
          <td class="px-4 py-3 text-center text-slate-700 font-medium">{{ app.days_applied }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColor(app.status)">{{ statusLabel(app.status) }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">{{ fmtDate(app.filed_at) }}</td>
          <td class="px-4 py-3">
            <Link :href="route('hr.leave.show', app.id)"
                  class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
              View
            </Link>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="app in applications.data" :key="app.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-mono text-xs text-slate-500">{{ app.control_no ?? '—' }}</p>
                <p class="font-medium text-slate-800">{{ canApprove ? app.user?.name : app.leave_type?.name }}</p>
                <p v-if="canApprove" class="text-xs text-slate-500">{{ app.leave_type?.name }}</p>
              </div>
              <AppBadge :color="statusColor(app.status)">{{ statusLabel(app.status) }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">{{ fmtDate(app.date_from) }} – {{ fmtDate(app.date_to) }} &middot; {{ app.days_applied }} day(s)</p>
            <div class="flex items-center justify-between pt-1">
              <span class="text-xs text-slate-400">Filed {{ fmtDate(app.filed_at) }}</span>
              <Link :href="route('hr.leave.show', app.id)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</Link>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No leave applications found" />
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

<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { ref } from "vue"

const props = defineProps({
  applications: Object,
  leaveTypes:   Array,
  filters:      Object,
})

const page = usePage()
const canApprove = page.props.auth?.user?.permissions?.includes('hr.leave.approve')
  || page.props.auth?.user?.permissions?.includes('hr.employee.manage')
const canFile = page.props.auth?.user?.permissions?.includes('hr.leave.file')
const search = ref(props.filters?.search ?? '')
const activeStatus = ref(props.filters?.status ?? '')

const statusOptions = [
  { value: '',            label: 'All' },
  { value: 'pending',     label: 'Pending' },
  { value: 'hr_verified', label: 'For Division Chief' },
  { value: 'forwarded',   label: 'For Campus Director' },
  { value: 'approved',    label: 'Approved' },
  { value: 'rejected',    label: 'Rejected' },
  { value: 'cancelled',   label: 'Cancelled' },
]

function applyFilters() {
  router.get(route('hr.leave.index'), {
    search: search.value || undefined,
    status: activeStatus.value || undefined,
  }, {
    preserveState: true,
    replace: true,
  })
}

function clearFilters() {
  search.value = ''
  activeStatus.value = ''
  router.get(route('hr.leave.index'), {}, {
    preserveState: true,
    replace: true,
  })
}

function statusColor(s) {
  const map = {
    pending:     'amber',
    hr_verified: 'purple',
    forwarded:   'blue',
    approved:    'green',
    rejected:    'red',
    cancelled:   'slate',
  }
  return map[s] ?? 'slate'
}

function statusLabel(s) {
  const map = {
    pending:     'Pending',
    hr_verified: 'For Division Chief',
    forwarded:   'For Campus Director',
    approved:    'Approved',
    rejected:    'Rejected',
    cancelled:   'Cancelled',
  }
  return map[s] ?? s
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>
