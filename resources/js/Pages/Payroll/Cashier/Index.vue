<script setup>
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'
import { PlusIcon, EyeIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ batches: Object })

const goToPage = (url) => { if (url) router.get(url) }

const monthName = (m) => ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][m] ?? m
const fmt = (n) => Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2 })
</script>

<template>
  <Head title="Payroll Upload — Cashier" />
  <AdminLayout title="Payroll Upload — Cashier">
    <div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Payroll Upload</h1>
          <p class="text-sm text-slate-500 mt-0.5">Upload Excel payroll workbooks and send per-employee payslips</p>
        </div>
        <a :href="route('payroll.cashier.upload')"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          <PlusIcon class="w-4 h-4" /> Upload Payroll
        </a>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Payroll No.</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Period</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Gross</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Net</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Uploaded by</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="b in batches.data" :key="b.id" class="hover:bg-slate-50/60">
              <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ b.payroll_no }}</td>
              <td class="px-4 py-3 text-slate-700">{{ monthName(b.month) }} {{ b.year }}</td>
              <td class="px-4 py-3 text-slate-700">₱ {{ fmt(b.totals_gross) }}</td>
              <td class="px-4 py-3 text-slate-700 font-medium">₱ {{ fmt(b.totals_net) }}</td>
              <td class="px-4 py-3">
                <span :class="[badgeBase, statusBadgeClass(b.status)]">{{ b.status }}</span>
              </td>
              <td class="px-4 py-3 text-slate-600 text-xs">{{ b.uploader?.name ?? '—' }}</td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center gap-1.5 justify-center">
                  <a :href="route('payroll.cashier.preview', b.id)"
                     class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Preview">
                    <EyeIcon class="w-4 h-4" />
                  </a>
                  <a :href="route('payroll.cashier.upload-bonus', b.id)"
                     class="p-1.5 rounded-lg hover:bg-indigo-50 text-slate-500 hover:text-indigo-700 transition-colors" title="Upload Bonus/SALA">
                    <ArrowUpTrayIcon class="w-4 h-4" />
                  </a>
                </div>
              </td>
            </tr>
            <tr v-if="!batches.data?.length">
              <td colspan="7" class="py-16 text-center text-slate-400 text-sm">No payroll batches yet.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="batches.last_page > 1" class="flex items-center justify-between mt-4 text-sm text-slate-600">
        <span>Page {{ batches.current_page }} of {{ batches.last_page }}</span>
        <div class="flex gap-2">
          <button @click="goToPage(batches.prev_page_url)" :disabled="!batches.prev_page_url"
                  class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 disabled:opacity-40">Prev</button>
          <button @click="goToPage(batches.next_page_url)" :disabled="!batches.next_page_url"
                  class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 disabled:opacity-40">Next</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
