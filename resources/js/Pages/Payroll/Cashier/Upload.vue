<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'

const mainFile      = ref(null)
const mainFilename  = ref('')
const mainBase64    = ref('')
const bonusFile     = ref(null)
const bonusFilename = ref('')
const bonusBase64   = ref('')
const sheetName     = ref('')
const loading       = ref(false)

function readFile(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload  = (e) => resolve(e.target.result)
    reader.onerror = reject
    reader.readAsDataURL(file)
  })
}

async function onMainChange(e) {
  const file = e.target.files[0]
  if (!file) return
  mainFilename.value = file.name
  mainBase64.value   = await readFile(file)
}

async function onBonusChange(e) {
  const file = e.target.files[0]
  if (!file) return
  bonusFilename.value = file.name
  bonusBase64.value   = await readFile(file)
}

async function submit() {
  if (!mainBase64.value) {
    Swal.fire({ icon: 'warning', title: 'Select the main payroll file first.' })
    return
  }
  loading.value = true

  router.post(route('payroll.cashier.upload.store'), {
    main_file_base64:  mainBase64.value,
    main_filename:     mainFilename.value,
    bonus_file_base64: bonusBase64.value || null,
    bonus_filename:    bonusFilename.value || null,
    sheet_name:        sheetName.value || null,
  }, {
    onError: (errs) => {
      loading.value = false
      Swal.fire({ icon: 'error', title: 'Parse failed', text: Object.values(errs).flat().join('\n') })
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
        <div>
          <h2 class="text-base font-semibold text-slate-800">Upload Payroll Excel</h2>
          <p class="text-sm text-slate-500 mt-1">
            Main file is required. Bonus/SALA file is optional — it can be uploaded later via the batch list.
          </p>
        </div>

        <!-- Main file -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">
            Main Payroll File <span class="text-red-500">*</span>
            <span class="text-slate-400 font-normal">(NEW ACU-SALARIES & PERA PAYROLL …xlsx)</span>
          </label>
          <input type="file" accept=".xlsx,.xls" @change="onMainChange"
                 class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 file:mr-3 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-xs file:font-medium" />
          <p v-if="mainFilename" class="mt-1 text-xs text-slate-500">{{ mainFilename }}</p>
        </div>

        <!-- Sheet name (optional) -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">
            Sheet Name <span class="text-slate-400 font-normal">(leave blank to auto-detect latest month)</span>
          </label>
          <input v-model="sheetName" type="text" placeholder="e.g. MAY or MAY (2)"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>

        <!-- Bonus file (optional) -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">
            Bonus / SALA File <span class="text-slate-400 font-normal">(optional)</span>
          </label>
          <input type="file" accept=".xlsx,.xls" @change="onBonusChange"
                 class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 file:mr-3 file:rounded file:border-0 file:bg-emerald-50 file:text-emerald-700 file:text-xs file:font-medium" />
          <p v-if="bonusFilename" class="mt-1 text-xs text-slate-500">{{ bonusFilename }}</p>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
          <a :href="route('payroll.cashier.index')"
             class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            Cancel
          </a>
          <button @click="submit" :disabled="loading || !mainBase64"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
            {{ loading ? 'Parsing…' : 'Parse & Preview' }}
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
