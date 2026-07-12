<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { PlusIcon, EyeIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ periods: Array })

const fmt = (n) => Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2 })

const MONTH = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']

function statusBadgeColor (s) {
  const map = { previewed: 'slate', sending: 'amber', completed: 'green', failed: 'red' }
  return map[s] ?? 'slate'
}
</script>

<template>
  <Head title="Payroll Upload — Cashier" />
  <AdminLayout title="Payroll Upload — Cashier">
    <div class="space-y-5">

      <AppPageHeader title="Payroll Batches" subtitle="Uploads grouped by pay period. Each disbursement type shares one payslip per employee.">
        <template #actions>
          <AppButton as="link" :href="route('payroll.cashier.upload')">
            <PlusIcon class="h-4 w-4" />
            Upload Payroll
          </AppButton>
        </template>
      </AppPageHeader>

      <EmptyState v-if="!periods.length" title="No payroll batches yet" />

      <!-- One card per pay period -->
      <div v-for="period in periods" :key="period.year + '-' + period.month"
           class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden">

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
        <AppTable :card="false" :is-empty="!period.batches.length" :skeleton-cols="7">
          <template #head>
            <tr>
              <th class="px-5 py-2 text-left text-xs font-medium text-slate-400 uppercase tracking-wide w-48">Type</th>
              <th class="px-5 py-2 text-left text-xs font-medium text-slate-400 uppercase tracking-wide">Payroll No.</th>
              <th class="px-5 py-2 text-right text-xs font-medium text-slate-400 uppercase tracking-wide">Gross</th>
              <th class="px-5 py-2 text-right text-xs font-medium text-slate-400 uppercase tracking-wide">Net</th>
              <th class="px-5 py-2 text-left text-xs font-medium text-slate-400 uppercase tracking-wide">Status</th>
              <th class="px-5 py-2 text-left text-xs font-medium text-slate-400 uppercase tracking-wide">Uploaded by</th>
              <th class="px-5 py-2 text-center text-xs font-medium text-slate-400 uppercase tracking-wide">Preview</th>
            </tr>
          </template>

          <tr v-for="b in period.batches" :key="b.id" class="hover:bg-slate-50/50">
            <td class="px-5 py-3 font-medium text-slate-700">
              {{ b.label || b.disbursement_type?.join(' + ') }}
            </td>
            <td class="px-5 py-3 font-mono text-xs text-slate-500">{{ b.payroll_no }}</td>
            <td class="px-5 py-3 text-right text-slate-600">₱ {{ fmt(b.totals_gross) }}</td>
            <td class="px-5 py-3 text-right font-medium text-slate-800">₱ {{ fmt(b.totals_net) }}</td>
            <td class="px-5 py-3">
              <AppBadge :color="statusBadgeColor(b.status)">{{ b.status }}</AppBadge>
            </td>
            <td class="px-5 py-3 text-xs text-slate-500">{{ b.uploader?.name ?? '—' }}</td>
            <td class="px-5 py-3 text-center">
              <Link :href="route('payroll.cashier.preview', b.id)"
                    class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                <EyeIcon class="w-3.5 h-3.5" /> View
              </Link>
            </td>
          </tr>

          <template #mobileCard>
            <div v-for="b in period.batches" :key="b.id" class="p-4 space-y-2">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="font-medium text-slate-800">{{ b.label || b.disbursement_type?.join(' + ') }}</p>
                  <p class="font-mono text-xs text-slate-500">{{ b.payroll_no }}</p>
                </div>
                <AppBadge :color="statusBadgeColor(b.status)">{{ b.status }}</AppBadge>
              </div>
              <div class="flex justify-between text-xs text-slate-500">
                <span>₱ {{ fmt(b.totals_gross) }} gross</span>
                <span class="font-semibold text-slate-800">₱ {{ fmt(b.totals_net) }} net</span>
              </div>
              <div class="flex items-center justify-between pt-1">
                <span class="text-xs text-slate-400">{{ b.uploader?.name ?? '—' }}</span>
                <Link :href="route('payroll.cashier.preview', b.id)"
                      class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                  <EyeIcon class="w-3.5 h-3.5" /> View
                </Link>
              </div>
            </div>
          </template>

          <template #empty>
            <EmptyState title="No disbursements in this period" />
          </template>
        </AppTable>
      </div>

    </div>
  </AdminLayout>
</template>
