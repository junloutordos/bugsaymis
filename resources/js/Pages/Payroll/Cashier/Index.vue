<script setup>
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, EyeIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ periods: Array })

const fmt = (n) => Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2 })

const MONTH = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']

const statusClass = (s) => ({
  previewed: 'bg-slate-100 text-slate-600',
  sending:   'bg-amber-100 text-amber-700',
  completed: 'bg-emerald-100 text-emerald-700',
  failed:    'bg-red-100 text-red-600',
}[s] ?? 'bg-slate-100 text-slate-500')
</script>

<template>
  <Head title="Payroll Upload — Cashier" />
  <AdminLayout title="Payroll Upload — Cashier">
    <div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Payroll Batches</h1>
          <p class="text-sm text-slate-500 mt-0.5">Uploads grouped by pay period. Each disbursement type shares one payslip per employee.</p>
        </div>
        <a :href="route('payroll.cashier.upload')"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          <PlusIcon class="w-4 h-4" /> Upload Payroll
        </a>
      </div>

      <div v-if="!periods.length" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center text-slate-400 text-sm">
        No payroll batches yet.
      </div>

      <!-- One card per pay period -->
      <div v-for="period in periods" :key="period.year + '-' + period.month"
           class="bg-white rounded-xl border border-slate-100 shadow-sm mb-4 overflow-hidden">

        <!-- Period header -->
        <div class="flex items-center justify-between px-5 py-3 bg-slate-50 border-b border-slate-100">
          <div>
            <span class="text-sm font-semibold text-slate-800">
              {{ MONTH[period.month] }} {{ period.year }}
            </span>
            <span class="ml-2 text-xs text-slate-400">
              {{ period.period_start }} – {{ period.period_end }}
            </span>
          </div>
          <span class="text-xs text-slate-400">
            {{ period.batches.length }} disbursement{{ period.batches.length !== 1 ? 's' : '' }}
          </span>
        </div>

        <!-- Batch rows within this period -->
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead>
            <tr class="bg-white">
              <th class="px-5 py-2 text-left text-xs font-medium text-slate-400 uppercase tracking-wide w-48">Type</th>
              <th class="px-5 py-2 text-left text-xs font-medium text-slate-400 uppercase tracking-wide">Payroll No.</th>
              <th class="px-5 py-2 text-right text-xs font-medium text-slate-400 uppercase tracking-wide">Gross</th>
              <th class="px-5 py-2 text-right text-xs font-medium text-slate-400 uppercase tracking-wide">Net</th>
              <th class="px-5 py-2 text-left text-xs font-medium text-slate-400 uppercase tracking-wide">Status</th>
              <th class="px-5 py-2 text-left text-xs font-medium text-slate-400 uppercase tracking-wide">Uploaded by</th>
              <th class="px-5 py-2 text-center text-xs font-medium text-slate-400 uppercase tracking-wide">Preview</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="b in period.batches" :key="b.id" class="hover:bg-slate-50/50">
              <td class="px-5 py-3 font-medium text-slate-700">
                {{ b.label || b.disbursement_type?.join(' + ') }}
              </td>
              <td class="px-5 py-3 font-mono text-xs text-slate-500">{{ b.payroll_no }}</td>
              <td class="px-5 py-3 text-right text-slate-600">₱ {{ fmt(b.totals_gross) }}</td>
              <td class="px-5 py-3 text-right font-medium text-slate-800">₱ {{ fmt(b.totals_net) }}</td>
              <td class="px-5 py-3">
                <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', statusClass(b.status)]">
                  {{ b.status }}
                </span>
              </td>
              <td class="px-5 py-3 text-xs text-slate-500">{{ b.uploader?.name ?? '—' }}</td>
              <td class="px-5 py-3 text-center">
                <a :href="route('payroll.cashier.preview', b.id)"
                   class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                  <EyeIcon class="w-3.5 h-3.5" /> View
                </a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </AdminLayout>
</template>
