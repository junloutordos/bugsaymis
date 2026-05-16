<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'

const TYPES = [
  { value: 'monthly_salary', label: 'Monthly Salary' },
  { value: 'hazard_pay',     label: 'Hazard Pay' },
  { value: 'sala',           label: 'Subsistence Allowance (SALA)' },
  { value: 'longevity_pay',  label: 'Longevity Pay' },
  { value: 'other',          label: 'Other Allowance' },
]

const selectedTypes       = ref(['monthly_salary'])
const customLabel         = ref('')
const periodStart         = ref('')
const periodEnd           = ref('')
const firstHalfCreditDate = ref('')
const secondHalfCreditDate= ref('')
const creditDate          = ref('')
const payrollNo           = ref('')
const filename            = ref('')
const base64              = ref('')
const loading             = ref(false)

const isMonthly = computed(() => selectedTypes.value.includes('monthly_salary'))

const autoLabel = computed(() => {
  if (!selectedTypes.value.length) return ''
  const names = {
    monthly_salary: 'Monthly Salary',
    hazard_pay:     'Hazard Pay',
    sala:           'Subsistence Allowance',
    longevity_pay:  'Longevity Pay',
    other:          customLabel.value || 'Other Allowance',
  }
  return selectedTypes.value.map(t => names[t] || t).join(' + ')
})

function templateHref() {
  const params = new URLSearchParams()
  selectedTypes.value.forEach(t => params.append('type[]', t))
  return route('payroll.cashier.csv-template') + '?' + params.toString()
}

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

async function submit() {
  if (!selectedTypes.value.length) {
    Swal.fire({ icon: 'warning', title: 'Select at least one disbursement type.' })
    return
  }
  if (!base64.value) {
    Swal.fire({ icon: 'warning', title: 'Select a CSV file first.' })
    return
  }
  if (!periodStart.value || !periodEnd.value) {
    Swal.fire({ icon: 'warning', title: 'Period Start and Period End are required.' })
    return
  }
  if (isMonthly.value && !firstHalfCreditDate.value) {
    Swal.fire({ icon: 'warning', title: '1st Half Credit Date is required for Monthly Salary.' })
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
    disbursement_type:        selectedTypes.value,
    label:                    autoLabel.value || null,
    period_start:             periodStart.value,
    period_end:               periodEnd.value,
    first_half_credit_date:   isMonthly.value ? (firstHalfCreditDate.value || null) : null,
    second_half_credit_date:  isMonthly.value ? (secondHalfCreditDate.value || null) : null,
    credit_date:              !isMonthly.value ? (creditDate.value || null) : null,
    payroll_no:               payrollNo.value || null,
  }, {
    onError: (errs) => {
      loading.value = false
      const msg = Object.values(errs).flat().join('\n') || 'An unexpected error occurred.'
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
            <p class="text-sm text-slate-500 mt-1">Select disbursement type(s), fill dates, then upload the CSV.</p>
          </div>
          <a :href="templateHref()" :class="['inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors whitespace-nowrap',
             selectedTypes.length ? 'text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100' : 'text-slate-400 bg-slate-50 pointer-events-none']">
            <ArrowDownTrayIcon class="w-3.5 h-3.5" /> CSV Template
          </a>
        </div>

        <!-- Disbursement Types — checkboxes -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-2">
            Disbursement Type <span class="text-red-500">*</span>
            <span class="text-slate-400 font-normal ml-1">(select all that apply)</span>
          </label>
          <div class="space-y-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
            <label v-for="t in TYPES" :key="t.value"
                   class="flex items-center gap-2.5 cursor-pointer group">
              <input type="checkbox" :value="t.value" v-model="selectedTypes"
                     class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" />
              <span class="text-sm text-slate-700 group-hover:text-slate-900">{{ t.label }}</span>
            </label>
          </div>
          <!-- Custom label for "other" -->
          <div v-if="selectedTypes.includes('other')" class="mt-2">
            <input v-model="customLabel" type="text" placeholder='Describe the allowance, e.g. "Year-End Bonus"'
                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <!-- Auto-label preview -->
          <p v-if="selectedTypes.length" class="mt-1.5 text-xs text-slate-500">
            Label: <span class="font-medium text-slate-700">{{ autoLabel }}</span>
          </p>
        </div>

        <!-- Period -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Period Start <span class="text-red-500">*</span></label>
            <input v-model="periodStart" type="date"
                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Period End <span class="text-red-500">*</span></label>
            <input v-model="periodEnd" type="date"
                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>

        <!-- Monthly Salary: 1st half required, 2nd half optional -->
        <template v-if="isMonthly">
          <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 space-y-3">
            <p class="text-xs font-semibold text-indigo-700">ATM Credit Dates</p>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">
                  1st Half Credit Date <span class="text-red-500">*</span>
                  <span class="text-slate-400 font-normal">(on/before 15th)</span>
                </label>
                <input v-model="firstHalfCreditDate" type="date"
                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">
                  2nd Half Credit Date
                  <span class="text-slate-400 font-normal">(optional — set later)</span>
                </label>
                <input v-model="secondHalfCreditDate" type="date"
                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>
            <p class="text-xs text-indigo-600">
              If you leave the 2nd Half date blank, you can set it later from the Preview page to trigger those notifications.
            </p>
          </div>
        </template>

        <!-- Non-monthly: single credit date -->
        <div v-else-if="selectedTypes.length">
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

        <!-- Payroll No -->
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
          <button @click="submit" :disabled="loading || !base64 || !selectedTypes.length"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
            {{ loading ? 'Parsing…' : 'Parse & Preview' }}
          </button>
        </div>
      </div>

      <!-- Info note -->
      <div v-if="isMonthly && !secondHalfCreditDate"
           class="mt-4 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-xs text-blue-800">
        <strong>Monthly Salary:</strong> Employees will receive a <strong>1st half notification</strong> now.
        When the 2nd half is ready, return to the batch preview and set the 2nd half credit date to send those notifications.
      </div>
      <div v-else-if="isMonthly && secondHalfCreditDate"
           class="mt-4 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-xs text-blue-800">
        <strong>Monthly Salary:</strong> Employees will receive <strong>two notifications</strong> — 1st half on {{ firstHalfCreditDate }} and 2nd half on {{ secondHalfCreditDate }}.
      </div>
    </div>
  </AdminLayout>
</template>
