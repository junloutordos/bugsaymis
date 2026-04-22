<template>
  <Head title="Payroll Runs" />
  <AdminLayout title="Payroll">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Payroll Runs</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage semi-monthly payroll periods.</p>
        </div>
        <Link
          :href="route('payroll.create')"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors shadow-sm"
        >
          <PlusIcon class="h-4 w-4" />
          New Payroll Run
        </Link>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.error }}
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
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
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!runs.data?.length">
                <td colspan="9" class="px-4 py-12 text-center text-slate-400 text-sm">No payroll runs yet. Create the first one.</td>
              </tr>
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
                  <span :class="statusBadgeClass(run.status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium">
                    {{ run.status.replace('_', ' ') }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <Link :href="route('payroll.show', run.id)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</Link>
                    <button
                      v-if="run.status === 'draft' || run.status === 'for_review'"
                      @click="processRun(run)"
                      class="text-xs text-amber-600 hover:text-amber-800 font-medium"
                    >Process</button>
                    <button
                      v-if="run.status === 'for_review'"
                      @click="approveRun(run)"
                      class="text-xs text-emerald-600 hover:text-emerald-800 font-medium"
                    >Approve</button>
                    <button
                      v-if="run.status === 'approved'"
                      @click="releaseRun(run)"
                      class="text-xs text-blue-600 hover:text-blue-800 font-medium"
                    >Release</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ runs.current_page }} of {{ runs.last_page }}</span>
          <div class="flex gap-2">
            <button @click="goToPage(runs.prev_page_url)" :disabled="!runs.prev_page_url" class="px-3 py-1.5 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50">Prev</button>
            <button @click="goToPage(runs.next_page_url)" :disabled="!runs.next_page_url" class="px-3 py-1.5 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50">Next</button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { statusBadgeClass } from '@/Composables/useStatusBadge.js'
import { PlusIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'

defineProps({ runs: Object })

const MONTHS = ['','January','February','March','April','May','June','July','August','September','October','November','December']
const monthName = (m) => MONTHS[m] ?? m

function fmt (val) {
  if (!val && val !== 0) return '—'
  return '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function goToPage (url) {
  if (url) router.visit(url, { preserveState: true })
}

function processRun (run) {
  if (confirm(`Process payroll run ${run.control_no}? This will queue computation for all active employees.`)) {
    router.post(route('payroll.process', run.id))
  }
}

function approveRun (run) {
  if (confirm(`Approve payroll run ${run.control_no}?`)) {
    router.post(route('payroll.approve', run.id))
  }
}

function releaseRun (run) {
  if (confirm(`Release payroll run ${run.control_no}? This will mark payslips as released and lock DTR records.`)) {
    router.post(route('payroll.release', run.id))
  }
}
</script>
