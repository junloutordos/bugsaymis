<template>
  <Head :title="`Client Satisfaction Survey — ${office.name}`" />

  <div class="min-h-screen bg-gradient-to-b from-indigo-50 via-white to-white py-6 px-4">
    <div class="max-w-2xl mx-auto">

      <!-- Header -->
      <div class="text-center mb-6">
        <img src="/images/pshslogo.png" alt="PSHS-CRC" class="h-16 w-16 mx-auto mb-2 object-contain" />
        <p class="text-[11px] font-semibold text-indigo-600 uppercase tracking-widest">PSHS – Caraga Region Campus</p>
        <h1 class="text-xl font-bold text-slate-800 mt-1">Client Satisfaction Survey</h1>
        <p class="text-xs text-slate-500 mt-1">PSHS-00-F-QMS-24</p>
        <div class="inline-flex items-center gap-1.5 mt-3 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-medium">
          🔒 100% Anonymous — no login required
        </div>
      </div>

      <!-- Card -->
      <div class="bg-white rounded-2xl shadow-lg border border-slate-100">
        <div class="px-5 py-4 border-b border-slate-100 bg-indigo-50 rounded-t-2xl">
          <p class="text-xs text-slate-500">You are giving feedback for:</p>
          <p class="text-base font-bold text-indigo-800">{{ office.name }}</p>
        </div>

        <form @submit.prevent="submit" class="px-5 py-5 space-y-6">

          <p class="text-xs text-slate-600 leading-relaxed">
            This Client Satisfaction Measurement (CSM) tracks the customer experience of Philippine Science High School.
            Your feedback on your recently concluded transaction will help us provide better services. Your response is
            completely anonymous and cannot be traced back to you.
          </p>

          <!-- ── Section 1: Client Info ── -->
          <div class="space-y-3">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide border-b border-slate-100 pb-1">Client Information</h3>

            <div>
              <p class="text-xs font-medium text-slate-600 mb-1.5">Client type:</p>
              <div class="flex flex-wrap gap-4">
                <label v-for="opt in clientTypes" :key="opt.value" class="flex items-center gap-1.5 text-sm text-slate-700 cursor-pointer">
                  <input type="radio" v-model="form.client_type" :value="opt.value" class="text-indigo-600" />
                  {{ opt.label }}
                </label>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <p class="text-xs font-medium text-slate-600 mb-1.5">Sex:</p>
                <div class="flex gap-3">
                  <label class="flex items-center gap-1.5 text-sm text-slate-700 cursor-pointer">
                    <input type="radio" v-model="form.sex" value="male" class="text-indigo-600" /> Male
                  </label>
                  <label class="flex items-center gap-1.5 text-sm text-slate-700 cursor-pointer">
                    <input type="radio" v-model="form.sex" value="female" class="text-indigo-600" /> Female
                  </label>
                </div>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Age:</label>
                <input v-model.number="form.age" type="number" min="1" max="120" placeholder="Optional"
                  class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Region of Residence:</label>
                <input v-model="form.region_of_residence" type="text"
                  class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date of Transaction:</label>
                <input v-model="form.date_of_transaction" type="date" :max="today"
                  class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Office where service was availed:</label>
                <input :value="office.name" type="text" readonly
                  class="w-full rounded-lg border border-slate-100 bg-slate-50 px-2 py-1.5 text-sm text-slate-500 cursor-not-allowed" />
              </div>
            </div>
          </div>

          <!-- ── Section 2: Service Availed ── -->
          <div class="space-y-2">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide border-b border-slate-100 pb-1">Service Availed <span class="text-slate-400 font-normal normal-case">(please check)</span></h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5">
              <label v-for="svc in generalServiceOptions" :key="svc.value" class="flex items-start gap-2 text-sm text-slate-700 cursor-pointer">
                <input type="checkbox" v-model="form.service_availed" :value="svc.value" class="mt-0.5 text-indigo-600 rounded" />
                <span>{{ svc.label }}</span>
              </label>
              <div class="flex items-start gap-2 sm:col-span-2">
                <input type="checkbox" v-model="form.service_availed" value="others" class="mt-0.5 text-indigo-600 rounded" />
                <span class="text-sm text-slate-700 flex-1">Others:
                  <input v-model="form.service_availed_other" type="text" placeholder="Please specify"
                    class="ml-1 w-40 sm:w-56 rounded-md border border-slate-200 px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                </span>
              </div>
            </div>
          </div>

          <!-- ── Section 3: CC Questions ── -->
          <div class="space-y-3">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide border-b border-slate-100 pb-1">Citizen's Charter (CC)</h3>

            <div>
              <p class="text-xs font-medium text-slate-700 mb-1.5">CC1: Which of the following best describes your awareness of a CC?</p>
              <div class="space-y-1">
                <label v-for="opt in cc1Options" :key="opt.value" class="flex items-start gap-2 text-sm text-slate-700 cursor-pointer">
                  <input type="radio" v-model="form.cc1" :value="opt.value" class="mt-0.5 text-indigo-600" />
                  <span>{{ opt.label }}</span>
                </label>
              </div>
            </div>

            <template v-if="form.cc1 <= 3">
              <div>
                <p class="text-xs font-medium text-slate-700 mb-1.5">CC2: If aware of CC, would you say the CC of this office was…?</p>
                <div class="flex flex-wrap gap-4">
                  <label v-for="opt in cc2Options" :key="opt.value" class="flex items-center gap-1.5 text-sm text-slate-700 cursor-pointer">
                    <input type="radio" v-model="form.cc2" :value="opt.value" class="text-indigo-600" />
                    {{ opt.label }}
                  </label>
                </div>
              </div>
              <div>
                <p class="text-xs font-medium text-slate-700 mb-1.5">CC3: How much did the CC help you in your transaction?</p>
                <div class="flex flex-wrap gap-4">
                  <label v-for="opt in cc3Options" :key="opt.value" class="flex items-center gap-1.5 text-sm text-slate-700 cursor-pointer">
                    <input type="radio" v-model="form.cc3" :value="opt.value" class="text-indigo-600" />
                    {{ opt.label }}
                  </label>
                </div>
              </div>
            </template>
          </div>

          <!-- ── Section 4: SQD ── -->
          <div class="space-y-2">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide border-b border-slate-100 pb-1">Service Quality Dimension (SQD)</h3>
            <p class="text-xs text-slate-500">For SQD 0–8, please tap the answer that best corresponds to your experience.</p>

            <div class="space-y-4">
              <div v-for="item in sqdItems" :key="item.key" class="border border-slate-100 rounded-lg p-3">
                <p class="text-xs text-slate-700 leading-relaxed mb-2">
                  <span class="font-semibold">{{ item.key.toUpperCase() }}:</span> {{ item.label }}
                </p>
                <div class="grid grid-cols-3 sm:grid-cols-6 gap-1.5">
                  <label v-for="col in sqdColumns" :key="col.value"
                    class="flex flex-col items-center justify-center gap-1 text-center rounded-md border px-1 py-2 cursor-pointer text-[10px] leading-tight"
                    :class="form[item.key] === col.value ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-semibold' : 'border-slate-200 text-slate-600'">
                    <input type="radio" :name="item.key" v-model="form[item.key]" :value="col.value" class="sr-only" />
                    {{ col.label }}
                  </label>
                </div>
              </div>
            </div>
            <p v-if="sqdError" class="text-xs text-red-500 mt-1">{{ sqdError }}</p>
          </div>

          <!-- ── Section 5: Suggestions ── -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">
              Suggestions on how we can further improve our services <span class="text-slate-400">(optional)</span>:
            </label>
            <textarea v-model="form.suggestions" rows="3"
              class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none" />
          </div>

          <!-- Submit -->
          <div class="pt-2 border-t border-slate-100">
            <button type="submit" :disabled="submitting"
              class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg font-medium transition-colors">
              {{ submitting ? 'Submitting…' : 'Submit Survey' }}
            </button>
          </div>

        </form>
      </div>

      <p class="text-center text-[11px] text-slate-400 mt-5">
        Philippine Science High School – Caraga Region Campus &middot; Client Satisfaction Measurement
      </p>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { useCsmQuestions } from '@/Composables/useCsmQuestions'

const props = defineProps({
  office: { type: Object, required: true }, // { id, name, token }
})

const { clientTypes, generalServiceOptions, cc1Options, cc2Options, cc3Options, sqdColumns, sqdItems } = useCsmQuestions()

const submitting = ref(false)
const sqdError = ref('')
const today = new Date().toISOString().slice(0, 10)

const form = reactive({
  client_type: 'citizen',
  sex: null,
  age: null,
  region_of_residence: 'Caraga',
  date_of_transaction: today,
  service_availed: [],
  service_availed_other: '',
  cc1: 1, cc2: 1, cc3: 1,
  sqd0: null, sqd1: null, sqd2: null,
  sqd3: null, sqd4: null, sqd5: null,
  sqd6: null, sqd7: null, sqd8: null,
  suggestions: '',
})

const submit = async () => {
  sqdError.value = ''

  const sqdKeys = ['sqd0','sqd1','sqd2','sqd3','sqd4','sqd5','sqd6','sqd7','sqd8']
  if (sqdKeys.some(k => form[k] === null)) {
    sqdError.value = 'Please answer all Service Quality Dimension items (SQD0–SQD8).'
    return
  }

  if (form.service_availed.length === 0) {
    await Swal.fire('Required', 'Please check at least one service availed.', 'warning')
    return
  }

  submitting.value = true

  router.post(route('csm.survey.store', props.office.token), {
    client_type: form.client_type,
    sex: form.sex,
    age: form.age,
    region_of_residence: form.region_of_residence,
    date_of_transaction: form.date_of_transaction,
    service_availed: form.service_availed,
    service_availed_other: form.service_availed_other,
    cc1: form.cc1,
    cc2: form.cc1 <= 3 ? form.cc2 : null,
    cc3: form.cc1 <= 3 ? form.cc3 : null,
    sqd0: form.sqd0, sqd1: form.sqd1, sqd2: form.sqd2,
    sqd3: form.sqd3, sqd4: form.sqd4, sqd5: form.sqd5,
    sqd6: form.sqd6, sqd7: form.sqd7, sqd8: form.sqd8,
    suggestions: form.suggestions,
  }, {
    onSuccess: () => {
      Swal.fire('Thank You!', 'Your Client Satisfaction Survey has been submitted anonymously.', 'success')
      // Reset form for the next walk-in client using the same device/kiosk.
      form.client_type = 'citizen'
      form.sex = null
      form.age = null
      form.service_availed = []
      form.service_availed_other = ''
      form.cc1 = form.cc2 = form.cc3 = 1
      sqdKeys.forEach(k => { form[k] = null })
      form.suggestions = ''
    },
    onError: (errors) => {
      const msg = Object.values(errors).flat().join('\n') || 'Please check all required fields.'
      Swal.fire('Error', msg, 'error')
    },
    onFinish: () => { submitting.value = false },
  })
}
</script>
