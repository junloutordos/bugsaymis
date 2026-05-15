<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { DocumentArrowDownIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ item: Object, batch: Object })

const fmt = (n) => Number(n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })
const show = (n) => (Number(n ?? 0) !== 0)

const earnings = [
  ['Basic Salary',                    'basic_salary'],
  ['Salary Increase',                 'salary_increase'],
  ['Additional Compensation',         'additional_compensation'],
  ['PERA',                            'pera'],
  ['Others (Bonus/Incentives/CA)',    'others_bonuses'],
  ['Subsistence and Laundry',         'sala'],
  ['Hazard Pay',                      'hazard_pay'],
  ['Longevity Pay',                   'longevity_pay'],
]

const deductions = [
  ['LAWOP/Undertime/Tardiness',    'lawop'],
  ['BIR Withholding Tax',          'bir_tax'],
  ['GSIS Contributions',           'gsis_contribution'],
  ['PhilHealth Contribution',      'philhealth_contribution'],
  ['HDMF (Pag-ibig) Contribution', 'hdmf_contribution'],
  ['GSIS Salary Loan',             'gsis_salary_loan'],
  ['GSIS Policy Loan',             'gsis_policy_loan'],
  ['GSIS Consolidated Loan',       'gsis_consolidated_loan'],
  ['GSIS Emergency Loan',          'gsis_emergency_loan'],
  ['GSIS MPL',                     'gsis_mpl'],
  ['GSIS GFAL',                    'gsis_gfal'],
  ['GSIS CPL',                     'gsis_cpl'],
  ['GSIS MPL Lite',                'gsis_mpl_lite'],
  ['HDMF Salary Loan',             'hdmf_salary_loan'],
  ['HDMF Calamity Loan',           'hdmf_calamity'],
  ['HDMF Housing Loan',            'hdmf_housing'],
  ['HDMF Multi-Purpose',           'hdmf_multipurpose'],
  ['HDMF MP2',                     'hdmf_mp2'],
  ['Landbank Loan',                'landbank_loan'],
  ['Credit Union/Cooperative',     'credit_union'],
]
</script>

<template>
  <Head title="Payslip" />
  <AdminLayout title="Payslip">
    <div class="max-w-2xl mx-auto">
      <!-- Actions -->
      <div class="flex items-center justify-between mb-4">
        <a :href="route('payroll.my-payslips.index')"
           class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-800 transition-colors">
          <ArrowLeftIcon class="w-4 h-4" /> Back to Payslips
        </a>
        <a :href="route('payroll.my-payslips.pdf', item.id)" target="_blank"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          <DocumentArrowDownIcon class="w-4 h-4" /> Download PDF
        </a>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <!-- Header -->
        <div class="bg-slate-800 text-white px-6 py-4 text-center">
          <div class="text-xs opacity-70">Republic of the Philippines · Department of Science and Technology</div>
          <div class="font-bold mt-0.5">Philippine Science High School — Caraga Region Campus</div>
          <div class="text-xs opacity-70">Brgy. Ampayon, Butuan City</div>
          <div class="text-sm font-semibold mt-2 tracking-wider uppercase">Payroll Payment Slip</div>
        </div>

        <!-- Employee info -->
        <div class="px-6 py-4 border-b border-slate-100 grid grid-cols-2 gap-3 text-sm">
          <div><span class="text-xs text-slate-500">Employee Name</span><p class="font-medium text-slate-800 mt-0.5">{{ item.employee?.name ?? item.employee_name_raw }}</p></div>
          <div><span class="text-xs text-slate-500">Employee No.</span><p class="text-slate-700 mt-0.5">{{ item.employee_no ?? '—' }}</p></div>
          <div><span class="text-xs text-slate-500">Position</span><p class="text-slate-700 mt-0.5">{{ item.position ?? '—' }}</p></div>
          <div><span class="text-xs text-slate-500">Office/Division</span><p class="text-slate-700 mt-0.5">{{ item.office_division ?? '—' }}</p></div>
          <div class="col-span-2"><span class="text-xs text-slate-500">Pay Period</span>
            <p class="text-slate-700 mt-0.5">{{ batch.period_start }} – {{ batch.period_end }}</p>
          </div>
        </div>

        <!-- Two columns -->
        <div class="grid grid-cols-2 divide-x divide-slate-100">
          <!-- Earnings -->
          <div>
            <div class="bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-600 uppercase tracking-wide border-b border-slate-100">Earnings</div>
            <div v-for="[label, key] in earnings" :key="key" class="flex justify-between px-4 py-1.5 text-sm border-b border-slate-50">
              <span class="text-slate-600">{{ label }}</span>
              <span :class="show(item[key]) ? 'text-slate-800' : 'text-slate-300'">{{ fmt(item[key]) }}</span>
            </div>
            <div class="flex justify-between px-4 py-2 bg-slate-50 font-semibold text-sm border-t border-slate-200">
              <span>Gross Earnings</span>
              <span>₱ {{ fmt(item.gross_earnings) }}</span>
            </div>
          </div>

          <!-- Deductions -->
          <div>
            <div class="bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-600 uppercase tracking-wide border-b border-slate-100">Deductions</div>
            <div v-for="[label, key] in deductions" :key="key" class="flex justify-between px-4 py-1.5 text-sm border-b border-slate-50">
              <span class="text-slate-600">{{ label }}</span>
              <span :class="show(item[key]) ? 'text-slate-800' : 'text-slate-300'">{{ fmt(item[key]) }}</span>
            </div>
            <div class="flex justify-between px-4 py-2 bg-slate-50 font-semibold text-sm border-t border-slate-200">
              <span>Total Deductions</span>
              <span>₱ {{ fmt(item.total_deductions) }}</span>
            </div>
          </div>
        </div>

        <!-- Net Pay -->
        <div class="px-6 py-4 border-t-2 border-slate-800 bg-slate-50 flex items-center justify-between">
          <span class="text-base font-bold text-slate-800">NET PAY</span>
          <span class="text-2xl font-bold text-slate-900">₱ {{ fmt(item.net_pay) }}</span>
        </div>

        <!-- Halves -->
        <div class="px-6 py-3 border-t border-slate-100 grid grid-cols-2 gap-4 text-sm text-slate-700">
          <div>Amount Due (1st half): <strong>₱ {{ fmt(item.first_half_amount) }}</strong></div>
          <div>Amount Due (2nd half): <strong>₱ {{ fmt(item.second_half_amount) }}</strong></div>
        </div>
        <div class="px-6 pb-4 text-sm text-slate-600">Received thru: <strong>ATM</strong></div>
      </div>
    </div>
  </AdminLayout>
</template>
