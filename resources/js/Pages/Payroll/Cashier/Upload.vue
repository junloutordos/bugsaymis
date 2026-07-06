<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppInput from '@/Components/AppInput.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { ArrowDownTrayIcon, DocumentArrowUpIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'

const TYPES = [
  { value: 'monthly_salary', label: 'Monthly Salary' },
  { value: 'hazard_pay',     label: 'Hazard Pay' },
  { value: 'sala',           label: 'Subsistence Allowance (SALA)' },
  { value: 'longevity_pay',  label: 'Longevity Pay' },
  { value: 'year_end_bonus',     label: 'Year-End Bonus and Cash Gift' },
  { value: 'clothing_allowance', label: 'Clothing Allowance' },
  { value: 'midyear_bonus',      label: 'Midyear Bonus' },
  { value: 'cna',                label: 'CNA Incentive' },
  { value: 'other',              label: 'Other Allowance' },
  { value: 'cash_advance',       label: 'Cash Advance' },
  { value: 'reimbursement',      label: 'Reimbursement' },
]

const NEEDS_PURPOSE = ['cash_advance', 'reimbursement']
const needsPurpose = (type) => NEEDS_PURPOSE.includes(type)

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December']

const selectedTypes = ref(['monthly_salary'])
const periodStart   = ref('')
const periodEnd     = ref('')
const loading       = ref(false)

const typeState = ref({})
const hasMonthly = computed(() => selectedTypes.value.includes('monthly_salary'))

function blankEntry() {
  return { periodMonth: '', periodYear: new Date().getFullYear(), filename: '', base64: '' }
}

function initTypeState(type) {
  if (!typeState.value[type]) {
    if (type === 'monthly_salary') {
      typeState.value[type] = {
        customLabel:          '',
        firstHalfCreditDate:  '',
        secondHalfCreditDate: '',
        payrollNo:            '',
        filename:             '',
        base64:               '',
      }
    } else {
      typeState.value[type] = {
        customLabel: '',
        creditDate:  '',
        payrollNo:   '',
        purpose:     '',
        entries:     [blankEntry()],
      }
    }
  }
}

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

async function onEntryFileChange(e, type, ei) {
  const file = e.target.files[0]
  if (!file) return
  typeState.value[type].entries[ei].filename = file.name
  typeState.value[type].entries[ei].base64   = await readFile(file)
}

function addEntry(type) {
  typeState.value[type].entries.push(blankEntry())
}

function removeEntry(type, ei) {
  typeState.value[type].entries.splice(ei, 1)
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
  if (hasMonthly.value && (!periodStart.value || !periodEnd.value)) {
    Swal.fire({ icon: 'warning', title: 'Period Start and End are required for Monthly Salary.' })
    return
  }

  // Validate per type
  for (const type of selectedTypes.value) {
    const s = typeState.value[type]
    if (isMonthly(type)) {
      if (!s.base64) {
        Swal.fire({ icon: 'warning', title: `Please upload a CSV file for ${typeLabel(type)}.` })
        return
      }
      if (!s.firstHalfCreditDate) {
        Swal.fire({ icon: 'warning', title: '1st Half Credit Date is required for Monthly Salary.' })
        return
      }
    } else {
      if (!s.creditDate) {
        Swal.fire({ icon: 'warning', title: `ATM Credit Date is required for ${typeLabel(type)}.` })
        return
      }
      if (needsPurpose(type) && !s.purpose) {
        Swal.fire({ icon: 'warning', title: `Purpose is required for ${typeLabel(type)}.` })
        return
      }
      for (const [ei, entry] of s.entries.entries()) {
        if (!entry.base64) {
          Swal.fire({ icon: 'warning', title: `Please upload a CSV for ${typeLabel(type)} (entry ${ei + 1}).` })
          return
        }
        if (!entry.periodMonth || !entry.periodYear) {
          Swal.fire({ icon: 'warning', title: `Please set month and year for ${typeLabel(type)} (entry ${ei + 1}).` })
          return
        }
      }
    }
  }

  loading.value = true

  const types = selectedTypes.value.map((type) => {
    const s = typeState.value[type]
    if (isMonthly(type)) {
      return {
        type,
        label:                   typeLabel(type),
        base64:                  s.base64,
        filename:                s.filename,
        first_half_credit_date:  s.firstHalfCreditDate || null,
        second_half_credit_date: s.secondHalfCreditDate || null,
        credit_date:             null,
        payroll_no:              s.payrollNo || null,
      }
    } else {
      return {
        type,
        label:       typeLabel(type),
        credit_date: s.creditDate || null,
        payroll_no:  s.payrollNo || null,
        purpose:     needsPurpose(type) ? (s.purpose || null) : null,
        entries:     s.entries.map(e => ({
          period_month: e.periodMonth || null,
          period_year:  e.periodYear  || null,
          base64:       e.base64,
          filename:     e.filename,
        })),
      }
    }
  })

  router.post(route('payroll.cashier.upload.store'), {
    period_start: periodStart.value || null,
    period_end:   periodEnd.value   || null,
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

      <!-- Shared header card -->
      <AppCard>
        <div class="space-y-5">
        <div>
          <h2 class="text-base font-semibold text-slate-800">Upload Payroll CSV</h2>
          <p class="text-sm text-slate-500 mt-1">Select disbursement type(s), then upload the CSV file(s). Non-monthly types support multiple months (accumulated release).</p>
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

        <!-- Pay period — only for monthly salary -->
        <div v-if="hasMonthly" class="grid grid-cols-2 gap-3">
          <AppInput v-model="periodStart" type="date" label="Period Start" required />
          <AppInput v-model="periodEnd" type="date" label="Period End" required />
        </div>
        </div>
      </AppCard>

      <!-- Per-type upload cards -->
      <template v-if="selectedTypes.length">
        <AppCard v-for="type in selectedTypes" :key="type">
        <div class="space-y-4">

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
          <AppInput v-if="type === 'other'" v-model="typeState[type].customLabel" type="text"
                    placeholder='e.g. "Year-End Bonus"' label="Describe the allowance" required />

          <!-- ── Monthly salary ── -->
          <template v-if="isMonthly(type)">
            <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 space-y-3">
              <p class="text-xs font-semibold text-indigo-700">ATM Credit Dates</p>
              <div class="grid grid-cols-2 gap-3">
                <AppInput v-model="typeState[type].firstHalfCreditDate" type="date" required
                          label="1st Half Credit Date (on/before 15th)" />
                <AppInput v-model="typeState[type].secondHalfCreditDate" type="date"
                          label="2nd Half Credit Date (optional)" />
              </div>
              <p class="text-xs text-indigo-600">Leave 2nd Half blank to set it later from the Preview page.</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">CSV File <span class="text-red-500">*</span></label>
              <input type="file" accept=".csv" @change="(e) => onFileChange(e, type)"
                     class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800
                            file:mr-3 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-xs file:font-medium" />
              <p v-if="typeState[type].filename" class="mt-1 text-xs text-slate-500">{{ typeState[type].filename }}</p>
            </div>

            <AppInput v-model="typeState[type].payrollNo" type="text" placeholder="e.g. 2026-05-001"
                      label="Payroll No. (optional)" />
          </template>

          <!-- ── Non-monthly: credit date + entries ── -->
          <template v-else>
            <!-- Shared ATM credit date for all entries in this release -->
            <div class="grid grid-cols-2 gap-3">
              <AppInput v-model="typeState[type].creditDate" type="date" label="ATM Credit Date" required />
              <AppInput v-model="typeState[type].payrollNo" type="text" placeholder="e.g. 2026-05-001"
                        label="Payroll No. (optional)" />
            </div>

            <!-- Purpose — required for Cash Advance and Reimbursement -->
            <AppInput v-if="needsPurpose(type)" v-model="typeState[type].purpose" type="text"
                      :placeholder="type === 'cash_advance' ? 'e.g. Official Travel to Manila' : 'e.g. Medical Reimbursement — Receipt #12345'"
                      label="Purpose" required />

            <!-- Per-month file entries -->
            <div class="space-y-3">
              <div v-for="(entry, ei) in typeState[type].entries" :key="ei"
                   class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-3">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-semibold text-slate-600">
                    {{ typeState[type].entries.length > 1 ? `Month ${ei + 1}` : 'CSV File' }}
                    <span v-if="typeState[type].entries.length > 1" class="text-indigo-500 font-normal ml-1">(separate batch)</span>
                  </span>
                  <button v-if="ei > 0" @click="removeEntry(type, ei)"
                          class="inline-flex items-center gap-1 text-xs text-red-500 hover:text-red-700 transition-colors">
                    <TrashIcon class="w-3.5 h-3.5" /> Remove
                  </button>
                </div>

                <!-- Month & Year pickers -->
                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Month <span class="text-red-500">*</span></label>
                    <select v-model="entry.periodMonth"
                            class="w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                      <option value="">— Select —</option>
                      <option v-for="(m, idx) in MONTHS" :key="idx + 1" :value="idx + 1">{{ m }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Year <span class="text-red-500">*</span></label>
                    <input v-model.number="entry.periodYear" type="number" min="2020" max="2040" placeholder="e.g. 2026"
                           class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  </div>
                </div>

                <!-- CSV file -->
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">CSV File <span class="text-red-500">*</span></label>
                  <input type="file" accept=".csv" @change="(e) => onEntryFileChange(e, type, ei)"
                         class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800
                                file:mr-3 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-xs file:font-medium" />
                  <p v-if="entry.filename" class="mt-1 text-xs text-slate-500">{{ entry.filename }}</p>
                </div>
              </div>
            </div>

            <!-- Add month button -->
            <button @click="addEntry(type)"
                    class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-2 rounded-lg
                           border border-dashed border-indigo-300 text-indigo-600 hover:bg-indigo-50 transition-colors w-full justify-center">
              <PlusIcon class="w-3.5 h-3.5" />
              Add Another Month (Accumulated Release)
            </button>
            <p v-if="typeState[type].entries.length > 1" class="text-xs text-amber-600 bg-amber-50 rounded-lg px-3 py-2">
              {{ typeState[type].entries.length }} months will be grouped as a release — one combined email per employee covering all months.
            </p>
          </template>

        </div>
        </AppCard>
      </template>

      <!-- No type selected -->
      <AppCard v-else>
        <EmptyState title="Select a disbursement type above to begin." />
      </AppCard>

      <!-- Actions -->
      <div class="flex justify-end gap-2">
        <AppButton as="link" variant="secondary" :href="route('payroll.cashier.index')">Cancel</AppButton>
        <AppButton :loading="loading" :disabled="loading || !selectedTypes.length" @click="submit">
          {{ loading ? 'Parsing…' : `Parse & Preview${selectedTypes.length > 1 ? ' (' + selectedTypes.length + ' types)' : ''}` }}
        </AppButton>
      </div>

    </div>
  </AdminLayout>
</template>
