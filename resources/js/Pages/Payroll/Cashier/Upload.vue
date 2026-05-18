<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowDownTrayIcon, DocumentArrowUpIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'

const TYPES = [
  { value: 'monthly_salary', label: 'Monthly Salary' },
  { value: 'hazard_pay',     label: 'Hazard Pay' },
  { value: 'sala',           label: 'Subsistence Allowance (SALA)' },
  { value: 'longevity_pay',  label: 'Longevity Pay' },
  { value: 'year_end_bonus',     label: 'Year-End Bonus and Cash Gift' },
  { value: 'clothing_allowance', label: 'Clothing Allowance' },
  { value: 'other',              label: 'Other Allowance' },
]

const selectedTypes = ref(['monthly_salary'])
const periodStart   = ref('')
const periodEnd     = ref('')
const loading       = ref(false)

// Per-type upload state keyed by type value
const typeState = ref({})

function initTypeState(type) {
  if (!typeState.value[type]) {
    typeState.value[type] = {
      customLabel:          '',
      firstHalfCreditDate:  '',
      secondHalfCreditDate: '',
      creditDate:           '',
      payrollNo:            '',
      filename:             '',
      base64:               '',
    }
  }
}

// When a type is checked, init its state; when unchecked, remove it
watch(selectedTypes, (types) => {
  types.forEach(initTypeState)
  Object.keys(typeState.value).forEach((k) => {
    if (!types.includes(k)) delete typeState.value[k]
  })
}, { immediate: true })

const isMonthly = (type) => type === 'monthly_salary'

function templateHref(type) {
  return route('payroll.cashier.csv-template') + '?type[]=' + type
}

function readFile(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload  = (e) => resolve(e.target.result)
    reader.onerror = reject
    reader.readAsDataURL(file)
  })
}

async function onFileChange(e, type) {
  const file = e.target.files[0]
  if (!file) return
  typeState.value[type].filename = file.name
  typeState.value[type].base64   = await readFile(file)
}

function typeLabel(type) {
  const s = typeState.value[type]
  if (type === 'other') return s?.customLabel || 'Other Allowance'
  return TYPES.find(t => t.value === type)?.label || type
}

async function submit() {
  if (!selectedTypes.value.length) {
    Swal.fire({ icon: 'warning', title: 'Select at least one disbursement type.' })
    return
  }
  if (!periodStart.value || !periodEnd.value) {
    Swal.fire({ icon: 'warning', title: 'Period Start and Period End are required.' })
    return
  }

  // Validate each type card
  for (const type of selectedTypes.value) {
    const s = typeState.value[type]
    if (!s.base64) {
      Swal.fire({ icon: 'warning', title: `Please upload a CSV file for ${typeLabel(type)}.` })
      return
    }
    if (isMonthly(type) && !s.firstHalfCreditDate) {
      Swal.fire({ icon: 'warning', title: '1st Half Credit Date is required for Monthly Salary.' })
      return
    }
    if (!isMonthly(type) && !s.creditDate) {
      Swal.fire({ icon: 'warning', title: `ATM Credit Date is required for ${typeLabel(type)}.` })
      return
    }
  }

  loading.value = true

  // Build array of per-type payloads
  const types = selectedTypes.value.map((type) => {
    const s = typeState.value[type]
    return {
      type,
      label:                    typeLabel(type),
      base64:                   s.base64,
      filename:                 s.filename,
      first_half_credit_date:   isMonthly(type) ? (s.firstHalfCreditDate || null) : null,
      second_half_credit_date:  isMonthly(type) ? (s.secondHalfCreditDate || null) : null,
      credit_date:              !isMonthly(type) ? (s.creditDate || null) : null,
      payroll_no:               s.payrollNo || null,
    }
  })

  router.post(route('payroll.cashier.upload.store'), {
    period_start: periodStart.value,
    period_end:   periodEnd.value,
    types,
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
    <div class="max-w-2xl mx-auto space-y-5">

      <!-- Shared period -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6 space-y-5">
        <div>
          <h2 class="text-base font-semibold text-slate-800">Upload Payroll CSV</h2>
          <p class="text-sm text-slate-500 mt-1">Select disbursement type(s), fill the pay period, then upload a CSV per type.</p>
        </div>

        <!-- Disbursement Types -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-2">
            Disbursement Type <span class="text-red-500">*</span>
            <span class="text-slate-400 font-normal ml-1">(select all that apply)</span>
          </label>
          <div class="space-y-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
            <label v-for="t in TYPES" :key="t.value" class="flex items-center gap-2.5 cursor-pointer group">
              <input type="checkbox" :value="t.value" v-model="selectedTypes"
                     class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" />
              <span class="text-sm text-slate-700 group-hover:text-slate-900">{{ t.label }}</span>
            </label>
          </div>
        </div>

        <!-- Pay period -->
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
      </div>

      <!-- Per-type upload cards -->
      <template v-if="selectedTypes.length">
        <div v-for="type in selectedTypes" :key="type"
             class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 space-y-4">

          <!-- Card header -->
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <DocumentArrowUpIcon class="w-4 h-4 text-indigo-500" />
              <span class="text-sm font-semibold text-slate-800">{{ typeLabel(type) }}</span>
            </div>
            <a :href="templateHref(type)"
               class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg
                      text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 transition-colors whitespace-nowrap">
              <ArrowDownTrayIcon class="w-3.5 h-3.5" /> CSV Template
            </a>
          </div>

          <!-- Custom label for "other" -->
          <div v-if="type === 'other'">
            <label class="block text-xs font-medium text-slate-600 mb-1">Describe the allowance <span class="text-red-500">*</span></label>
            <input v-model="typeState[type].customLabel" type="text"
                   placeholder='e.g. "Year-End Bonus"'
                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>

          <!-- Monthly: 1st half required, 2nd half optional -->
          <template v-if="isMonthly(type)">
            <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 space-y-3">
              <p class="text-xs font-semibold text-indigo-700">ATM Credit Dates</p>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">
                    1st Half Credit Date <span class="text-red-500">*</span>
                    <span class="text-slate-400 font-normal">(on/before 15th)</span>
                  </label>
                  <input v-model="typeState[type].firstHalfCreditDate" type="date"
                         class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">
                    2nd Half Credit Date
                    <span class="text-slate-400 font-normal">(optional)</span>
                  </label>
                  <input v-model="typeState[type].secondHalfCreditDate" type="date"
                         class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
              </div>
              <p class="text-xs text-indigo-600">Leave 2nd Half blank to set it later from the Preview page.</p>
            </div>
          </template>

          <!-- Non-monthly: single credit date -->
          <div v-else>
            <label class="block text-xs font-medium text-slate-600 mb-1">
              ATM Credit Date <span class="text-red-500">*</span>
            </label>
            <input v-model="typeState[type].creditDate" type="date"
                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>

          <!-- CSV file -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">
              CSV File <span class="text-red-500">*</span>
              <span class="text-slate-400 font-normal">(.csv only)</span>
            </label>
            <input type="file" accept=".csv" @change="(e) => onFileChange(e, type)"
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800
                          file:mr-3 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-xs file:font-medium" />
            <p v-if="typeState[type].filename" class="mt-1 text-xs text-slate-500">{{ typeState[type].filename }}</p>
          </div>

          <!-- Payroll No (per type) -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">
              Payroll No. <span class="text-slate-400 font-normal">(optional)</span>
            </label>
            <input v-model="typeState[type].payrollNo" type="text" placeholder="e.g. 2026-05-001"
                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
      </template>

      <!-- No type selected -->
      <div v-else class="bg-slate-50 border border-dashed border-slate-200 rounded-xl p-8 text-center text-sm text-slate-400">
        Select a disbursement type above to begin.
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-2">
        <a :href="route('payroll.cashier.index')"
           class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
          Cancel
        </a>
        <button @click="submit"
                :disabled="loading || !selectedTypes.length || !periodStart || !periodEnd"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
          {{ loading ? 'Parsing…' : `Parse & Preview${selectedTypes.length > 1 ? ' (' + selectedTypes.length + ' batches)' : ''}` }}
        </button>
      </div>

    </div>
  </AdminLayout>
</template>
