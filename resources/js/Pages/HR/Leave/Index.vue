<template>
  <Head title="Leave Applications" />
  <AdminLayout title="Leave Applications">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Leave Applications</h1>
          <p class="text-sm text-slate-500 mt-0.5">{{ canApprove ? 'All employee leave requests' : 'Your leave applications' }}</p>
        </div>
        <Link v-if="canFile" :href="route('hr.leave.create')"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors font-medium">
          + File Leave
        </Link>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.success }}
      </div>

      <!-- Status Filters -->
      <div class="flex flex-wrap gap-2">
        <button v-for="s in statusOptions" :key="s.value"
                @click="setFilter(s.value)"
                :class="[
                  'px-3 py-1.5 rounded-full text-xs font-medium border transition-colors',
                  filters.status === s.value
                    ? 'bg-indigo-600 text-white border-indigo-600'
                    : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'
                ]">
          {{ s.label }}
        </button>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
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
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!applications.data?.length">
                <td :colspan="canApprove ? 8 : 7" class="px-4 py-12 text-center text-slate-400 text-sm">
                  No leave applications found.
                </td>
              </tr>
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
                  <span :class="statusClass(app.status)"
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold">
                    {{ statusLabel(app.status) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">{{ fmtDate(app.filed_at) }}</td>
                <td class="px-4 py-3">
                  <Link :href="route('hr.leave.show', app.id)"
                        class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                    View
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ applications.current_page }} of {{ applications.last_page }} &bull; {{ applications.total }} total</span>
          <div class="flex gap-2">
            <button @click="goTo(applications.prev_page_url)" :disabled="!applications.prev_page_url"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50">Prev</button>
            <button @click="goTo(applications.next_page_url)" :disabled="!applications.next_page_url"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50">Next</button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  applications: Object,
  leaveTypes:   Array,
  filters:      Object,
})

const page = usePage()
const canApprove = page.props.auth?.user?.permissions?.includes('hr.leave.approve')
  || page.props.auth?.user?.permissions?.includes('hr.employee.manage')
const canFile = page.props.auth?.user?.permissions?.includes('hr.leave.file')

const statusOptions = [
  { value: '',            label: 'All' },
  { value: 'pending',     label: 'Pending' },
  { value: 'hr_verified', label: 'For Division Chief' },
  { value: 'forwarded',   label: 'For Campus Director' },
  { value: 'approved',    label: 'Approved' },
  { value: 'rejected',    label: 'Rejected' },
  { value: 'cancelled',   label: 'Cancelled' },
]

function setFilter(status) {
  router.get(route('hr.leave.index'), { status: status || undefined }, { preserveState: true, replace: true })
}

function goTo(url) {
  if (url) router.visit(url, { preserveState: true })
}

function statusClass(s) {
  const map = {
    pending:     'bg-amber-100 text-amber-700',
    hr_verified: 'bg-violet-100 text-violet-700',
    forwarded:   'bg-blue-100 text-blue-700',
    approved:    'bg-emerald-100 text-emerald-700',
    rejected:    'bg-red-100 text-red-600',
    cancelled:   'bg-slate-100 text-slate-500',
  }
  return map[s] ?? 'bg-slate-100 text-slate-500'
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
