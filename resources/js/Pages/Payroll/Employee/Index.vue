<script setup>
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { DocumentArrowDownIcon, EyeIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ payslips: Object })

const monthName = (m) => ['','January','February','March','April','May','June','July','August','September','October','November','December'][m] ?? m
const fmt = (n) => Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2 })
const goToPage = (url) => { if (url) router.get(url) }
</script>

<template>
  <Head title="My Payslips" />
  <AdminLayout title="My Payslips">
    <div>
      <div class="mb-6">
        <h1 class="text-xl font-semibold text-slate-800">My Payslips</h1>
        <p class="text-sm text-slate-500 mt-0.5">Your payslip history from PSHS-CRC</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="p in payslips.data" :key="p.id"
             class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 flex flex-col gap-3">
          <div>
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
              {{ monthName(p.month) }} {{ p.year }}
            </div>
            <div class="text-sm text-slate-500 mt-0.5">
              {{ p.batch?.period_start }} – {{ p.batch?.period_end }}
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2 text-sm">
            <div>
              <div class="text-xs text-slate-400">Basic Salary</div>
              <div class="font-medium text-slate-700">₱ {{ fmt(p.basic_salary) }}</div>
            </div>
            <div>
              <div class="text-xs text-slate-400">Net Pay</div>
              <div class="font-semibold text-emerald-700">₱ {{ fmt(p.net_pay) }}</div>
            </div>
          </div>

          <div v-if="p.bonus_uploaded_at" class="text-xs text-indigo-600 bg-indigo-50 rounded px-2 py-1">
            Bonus/SALA included
          </div>

          <div class="flex gap-2 pt-1 border-t border-slate-100">
            <a :href="route('payroll.my-payslips.show', p.id)"
               class="flex-1 inline-flex items-center justify-center gap-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
              <EyeIcon class="w-3.5 h-3.5" /> View
            </a>
            <a :href="route('payroll.my-payslips.pdf', p.id)" target="_blank"
               class="flex-1 inline-flex items-center justify-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
              <DocumentArrowDownIcon class="w-3.5 h-3.5" /> PDF
            </a>
          </div>
        </div>

        <div v-if="!payslips.data?.length" class="col-span-3 py-20 text-center text-slate-400 text-sm">
          No payslips yet.
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="payslips.last_page > 1" class="flex items-center justify-between mt-6 text-sm text-slate-600">
        <span>Page {{ payslips.current_page }} of {{ payslips.last_page }}</span>
        <div class="flex gap-2">
          <button @click="goToPage(payslips.prev_page_url)" :disabled="!payslips.prev_page_url"
                  class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 disabled:opacity-40">Prev</button>
          <button @click="goToPage(payslips.next_page_url)" :disabled="!payslips.next_page_url"
                  class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 disabled:opacity-40">Next</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
