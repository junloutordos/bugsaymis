<template>
  <Head title="Payroll Runs" />
  <AdminLayout title="Payroll">
    <div class="space-y-5">

      <AppPageHeader title="Payroll Runs" subtitle="Manage semi-monthly payroll periods.">
        <template #actions>
          <AppButton as="link" :href="route('payroll.create')">
            <PlusIcon class="h-4 w-4" />
            New Payroll Run
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.error }}
      </div>

      <!-- Table -->
      <AppTable :is-empty="!runs.data?.length" :skeleton-cols="9">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Control No.</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Period</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Pay Date</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employees</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Gross Pay</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Deductions</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Net Pay</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="run in runs.data" :key="run.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 font-mono text-slate-700 font-medium">{{ run.control_no }}</td>
          <td class="px-4 py-3">
            <div class="font-medium text-slate-800">{{ monthName(run.month) }} {{ run.year }}</div>
            <div class="text-xs text-slate-400">{{ run.period }} half &bull; {{ run.period_start }} – {{ run.period_end }}</div>
          </td>
          <td class="px-4 py-3 text-slate-600">{{ run.pay_date ?? '—' }}</td>
          <td class="px-4 py-3 text-right text-slate-700">{{ run.payroll_records_count }}</td>
          <td class="px-4 py-3 text-right text-slate-700">{{ fmt(run.total_gross) }}</td>
          <td class="px-4 py-3 text-right text-slate-700">{{ fmt(run.total_deductions) }}</td>
          <td class="px-4 py-3 text-right font-semibold text-slate-800">{{ fmt(run.total_net) }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusBadgeColor(run.status)">{{ run.status.replace('_', ' ') }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-3">
              <Link :href="route('payroll.show', run.id)" class="text-xs text-slate-500 hover:text-slate-700 font-medium">View</Link>
              <AppButton
                v-if="run.status === 'draft' || run.status === 'for_review'"
                size="sm" variant="warning" @click="processRun(run)"
              >Process</AppButton>
              <AppButton
                v-if="run.status === 'for_review'"
                size="sm" variant="success" @click="approveRun(run)"
              >Approve</AppButton>
              <AppButton
                v-if="run.status === 'approved'"
                size="sm" variant="primary" @click="releaseRun(run)"
              >Release</AppButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="run in runs.data" :key="run.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-mono text-sm font-medium text-slate-800">{{ run.control_no }}</p>
                <p class="text-sm text-slate-700">{{ monthName(run.month) }} {{ run.year }} &middot; {{ run.period }} half</p>
                <p class="text-xs text-slate-400">{{ run.period_start }} – {{ run.period_end }}</p>
              </div>
              <AppBadge :color="statusBadgeColor(run.status)">{{ run.status.replace('_', ' ') }}</AppBadge>
            </div>
            <div class="flex justify-between text-xs text-slate-500">
              <span>{{ run.payroll_records_count }} employees</span>
              <span class="font-semibold text-slate-800">Net {{ fmt(run.total_net) }}</span>
            </div>
            <div class="flex items-center gap-3 pt-1">
              <Link :href="route('payroll.show', run.id)" class="text-xs text-slate-500 hover:text-slate-700 font-medium">View</Link>
              <AppButton
                v-if="run.status === 'draft' || run.status === 'for_review'"
                size="sm" variant="warning" @click="processRun(run)"
              >Process</AppButton>
              <AppButton
                v-if="run.status === 'for_review'"
                size="sm" variant="success" @click="approveRun(run)"
              >Approve</AppButton>
              <AppButton
                v-if="run.status === 'approved'"
                size="sm" variant="primary" @click="releaseRun(run)"
              >Release</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No payroll runs yet" subtitle="Create the first one to get started." />
        </template>

        <template #footer>
          <PaginationControl :links="runs.links" :current-page="runs.current_page" :total-pages="runs.last_page" :total="runs.total" />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
import { PlusIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'

defineProps({ runs: Object })

const MONTHS = ['','January','February','March','April','May','June','July','August','September','October','November','December']
const monthName = (m) => MONTHS[m] ?? m

function fmt (val) {
  if (!val && val !== 0) return '—'
  return '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function statusBadgeColor (status) {
  const map = { draft: 'slate', for_review: 'amber', approved: 'blue', released: 'green' }
  return map[status] ?? 'slate'
}

async function processRun (run) {
  if (await confirmAction({ title: 'Process payroll run?', text: `Process ${run.control_no}? This will queue computation for all active employees.`, confirmText: 'Process' })) {
    router.post(route('payroll.process', run.id))
  }
}

async function approveRun (run) {
  if (await confirmAction({ title: 'Approve payroll run?', text: `Approve ${run.control_no}?`, confirmText: 'Approve' })) {
    router.post(route('payroll.approve', run.id))
  }
}

async function releaseRun (run) {
  if (await confirmAction({ title: 'Release payroll run?', text: `Release ${run.control_no}? This will mark payslips as released and lock DTR records.`, confirmText: 'Release' })) {
    router.post(route('payroll.release', run.id))
  }
}
</script>
