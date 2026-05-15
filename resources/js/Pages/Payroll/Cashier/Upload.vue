<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'

const DISBURSEMENT_TYPES = [
  { value: 'monthly_salary', label: 'Monthly Salary' },
  { value: 'hazard_pay',     label: 'Hazard Pay' },
  { value: 'sala',           label: 'Subsistence Allowance (SALA)' },
  { value: 'longevity_pay',  label: 'Longevity Pay' },
  { value: 'other',          label: 'Other Allowance' },
]

const disbursementType    = ref('monthly_salary')
const label               = ref('')
const periodStart         = ref('')
const periodEnd           = ref('')
const firstHalfCreditDate = ref('')
const secondHalfCreditDate= ref('')
const creditDate          = ref('')
const payrollNo           = ref('')
const filename            = ref('')
const base64              = ref('')
const loading             = ref(false)

const isMonthly = computed(() => disbursementType.value === 'monthly_salary')

// Auto-fill label when type changes
watch(disbursementType, (val) => {
  const found = DISBURSEMENT_TYPES.find(t => t.value === val)
  if (val !== 'other') label.value = found?.label ?? ''
  else label.value = ''
})

function readFile(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload  = (e) => resolve(e.target.result)
    reader.onerror = reject
    reader.readAsDataURL(file)
  })
}

async function onFileChange(e) {
  const file = e.target.files[0]
  if (!file) return
  filename.value = file.name
  base64.value   = await readFile(file)
}

function templateHref() {
  return route('payroll.cashier.csv-template') + '?type=' + disbursementType.value
}

async function submit() {
  if (!base64.value) {
    Swal.fire({ icon: 'warning', title: 'Select a CSV file first.' })
    return
  }
  if (!periodStart.value || !periodEnd.value) {
    Swal.fire({ icon: 'warning', title: 'Period Start and Period End are required.' })
    return
  }
  if (isMonthly.value && (!firstHalfCreditDate.value || !secondHalfCreditDate.value)) {
    Swal.fire({ icon: 'warning', title: 'Both credit dates are required for Monthly Salary.' })
    return
  }
  if (!isMonthly.value && !creditDate.value) {
    Swal.fire({ icon: 'warning', title: 'Credit Date is required.' })
    return
  }

  loading.value = true

  router.post(route('payroll.cashier.upload.store'), {
    main_file_base64:         base64.value,
    main_filename:            filename.value,
    disbursement_type:        disbursementType.value,
    label:                    label.value || null,
    period_start:             periodStart.value,
    period_end:               periodEnd.value,
    first_half_credit_date:   isMonthly.value ? firstHalfCreditDate.value : null,
    second_half_credit_date:  isMonthly.value ? secondHalfCreditDate.value : null,
    credit_date:              !isMonthly.value ? creditDate.value : null,
    payroll_no:               payrollNo.value || null,
  }, {
    onError: (errs) => {
      loading.value = false
      const msg = Object.values(errs).flat().join('\n') || 'An unexpected error occurred. Check the browser console or contact support.'
      Swal.fire({ icon: 'error', title: 'Parse failed', text: msg })
    },
    onFinish: () => { loading.value = false },
  })
}
</script>

<template>
  <Head title="Upload Payroll — Cashier" />
  <AdminLayout title="Upload Payroll">
    <div class="max-w-xl mx-auto">
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6 space-y-5">

        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 class="text-base font-semibold text-slate-800">Upload Payroll CSV</h2>
            <p class="text-sm text-slate-500 mt-1">
              Select the disbursement type, fill in dates, then upload the CSV.
            </p>
          </div>
          <a :href="templateHref()"
             class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg transition-colors whitespace-nowrap">
            <ArrowDownTrayIcon class="w-3.5 h-3.5" /> CSV Template
          </a>
        </div>

        <!-- Disbursement Type -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">
            Disbursement Type <span class="text-red-500">*</span>
          </label>
          <select v-model="disbursementType"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option v-for="t in DISBURSEMENT_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
        </div>

        <!-- Custom label (Other only) -->
        <div v-if="disbursementType === 'other'">
          <label class="block text-xs font-medium text-slate-600 mb-1">
            Allowance Label <span class="text-red-500">*</span>
          </label>
          <input v-model="label" type="text" placeholder="e.g. Year-End Bonus"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>

        <!-- Period -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">
              Period Start <span class="text-red-500">*</span>
            </label>
            <input v-model="periodStart" type="date"
                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">
              Period End <span class="text-red-500">*</span>
            </label>
            <input v-model="periodEnd" type="date"
                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>

        <!-- Monthly Salary: two credit dates -->
        <template v-if="isMonthly">
          <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 space-y-3">
            <p class="text-xs font-semibold text-indigo-700">ATM Credit Dates</p>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">
                  1st Half Credit Date <span class="text-red-500">*</span>
                  <span class="text-slate-400 font-normal">(on or before 15th)</span>
                </label>
                <input v-model="firstHalfCreditDate" type="date"
                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">
                  2nd Half Credit Date <span class="text-red-500">*</span>
                  <span class="text-slate-400 font-normal">(on or before 30th)</span>
                </label>
                <input v-model="secondHalfCreditDate" type="date"
                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>
          </div>
        </template>

        <!-- Other disbursements: single credit date -->
        <div v-else>
          <label class="block text-xs font-medium text-slate-600 mb-1">
            ATM Credit Date <span class="text-red-500">*</span>
          </label>
          <input v-model="creditDate" type="date"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>

        <!-- CSV file -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">
            CSV File <span class="text-red-500">*</span>
            <span class="text-slate-400 font-normal">(.csv only)</span>
          </label>
          <input type="file" accept=".csv" @change="onFileChange"
                 class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 file:mr-3 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-xs file:font-medium" />
          <p v-if="filename" class="mt-1 text-xs text-slate-500">{{ filename }}</p>
        </div>

        <!-- Payroll No (optional) -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">
            Payroll No. <span class="text-slate-400 font-normal">(optional — overrides auto-generated)</span>
          </label>
          <input v-model="payrollNo" type="text" placeholder="e.g. 2026-05-001"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
          <a :href="route('payroll.cashier.index')"
             class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            Cancel
          </a>
          <button @click="submit" :disabled="loading || !base64"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
            {{ loading ? 'Parsing…' : 'Parse & Preview' }}
          </button>
        </div>
      </div>

      <!-- Monthly salary note -->
      <div v-if="isMonthly" class="mt-4 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-xs text-blue-800">
        <strong>Monthly Salary:</strong> Employees will receive <strong>two separate notifications</strong> — one for the 1st half credit and one for the 2nd half credit. Both include the full payslip as attachment.
      </div>
      <div v-else class="mt-4 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-xs text-amber-800">
        <strong>{{ DISBURSEMENT_TYPES.find(t => t.value === disbursementType)?.label }}:</strong>
        Employees will receive one notification when their {{ label || 'allowance' }} is credited to their ATM account.
      </div>
    </div>
  </AdminLayout>
</template>
