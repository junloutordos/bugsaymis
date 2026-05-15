<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps({ batch: Object })

const bonusFile     = ref(null)
const bonusFilename = ref('')
const bonusBase64   = ref('')
const loading       = ref(false)

function readFile(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload  = (e) => resolve(e.target.result)
    reader.onerror = reject
    reader.readAsDataURL(file)
  })
}

async function onBonusChange(e) {
  const file = e.target.files[0]
  if (!file) return
  bonusFilename.value = file.name
  bonusBase64.value   = await readFile(file)
}

async function submit() {
  if (!bonusBase64.value) {
    Swal.fire({ icon: 'warning', title: 'Select the bonus/SALA file first.' })
    return
  }
  loading.value = true

  router.post(route('payroll.cashier.upload-bonus.store', props.batch.id), {
    bonus_file_base64: bonusBase64.value,
    bonus_filename:    bonusFilename.value,
  }, {
    onError: (errs) => {
      loading.value = false
      Swal.fire({ icon: 'error', title: 'Parse failed', text: Object.values(errs).flat().join('\n') })
    },
    onSuccess: () => {
      Swal.fire({ icon: 'success', title: 'Bonus uploaded!', text: 'Bonus fields updated and emails queued.', timer: 1800, showConfirmButton: false })
    },
    onFinish: () => { loading.value = false },
  })
}
</script>

<template>
  <Head title="Upload Bonus/SALA" />
  <AdminLayout title="Upload Bonus/SALA">
    <div class="max-w-xl mx-auto">
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6 space-y-5">
        <div>
          <h2 class="text-base font-semibold text-slate-800">Upload Bonus / SALA File</h2>
          <p class="text-sm text-slate-500 mt-1">
            Batch: <strong>{{ batch.payroll_no }}</strong> &nbsp;·&nbsp; {{ batch.period_start }} – {{ batch.period_end }}
          </p>
          <p class="text-xs text-slate-400 mt-1">
            Uploads bonus/SALA figures into the existing payslips for this period and queues updated notification emails.
          </p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">
            Bonus / SALA File <span class="text-red-500">*</span>
            <span class="text-slate-400 font-normal">(.xlsx or .xls)</span>
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
          <button @click="submit" :disabled="loading || !bonusBase64"
                  class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
            {{ loading ? 'Uploading…' : 'Upload & Notify' }}
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
