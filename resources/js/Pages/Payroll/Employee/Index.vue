<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { DocumentArrowDownIcon, EyeIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ payslips: Object })

const monthName = (m) => ['','January','February','March','April','May','June','July','August','September','October','November','December'][m] ?? m
const fmt = (n) => Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2 })
</script>

<template>
  <Head title="My Payslips" />
  <AdminLayout title="My Payslips">
    <div class="space-y-5">

      <AppPageHeader title="My Payslips" subtitle="Your payslip history from PSHS-CRC" />

      <EmptyState v-if="!payslips.data?.length" title="No payslips yet" />

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
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
              <div class="font-semibold text-success-700">₱ {{ fmt(p.net_pay) }}</div>
            </div>
          </div>

          <AppBadge v-if="p.bonus_uploaded_at" color="indigo">Bonus/SALA included</AppBadge>

          <div class="flex gap-2 pt-1 border-t border-slate-100">
            <AppButton as="link" :href="route('payroll.my-payslips.show', p.id)" variant="secondary" size="sm" block>
              <EyeIcon class="w-3.5 h-3.5" /> View
            </AppButton>
            <AppButton as="a" :href="route('payroll.my-payslips.pdf', p.id)" target="_blank" size="sm" block>
              <DocumentArrowDownIcon class="w-3.5 h-3.5" /> PDF
            </AppButton>
          </div>
        </div>
      </div>

      <PaginationControl
        v-if="payslips.data?.length"
        :links="payslips.links"
        :current-page="payslips.current_page"
        :total-pages="payslips.last_page"
        :total="payslips.total"
      />

    </div>
  </AdminLayout>
</template>
