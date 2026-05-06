<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 backdrop-blur-sm overflow-y-auto py-6 px-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
          <div>
            <h2 class="text-base font-bold text-slate-800">Client Satisfaction Survey</h2>
            <p class="text-xs text-slate-500 mt-0.5">PSHS-00-F-QMS-24 — Internal Clients</p>
          </div>
          <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
        </div>

        <form @submit.prevent="submit" class="px-6 py-5 space-y-6">

          <!-- Intro -->
          <p class="text-xs text-slate-600 leading-relaxed">
            This Client Satisfaction Measurement (CSM) tracks the customer experience of Philippine Science High School.
            Your feedback on your recently concluded transaction will help us provide better services.
          </p>

          <!-- ── Section 1: Client Info ── -->
          <div class="space-y-3">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide border-b border-slate-100 pb-1">Client Information</h3>

            <!-- Client type -->
            <div>
              <p class="text-xs font-medium text-slate-600 mb-1.5">Client type:</p>
              <div class="flex flex-wrap gap-4">
                <label v-for="opt in clientTypes" :key="opt.value" class="flex items-center gap-1.5 text-sm text-slate-700 cursor-pointer">
                  <input type="radio" v-model="form.client_type" :value="opt.value" class="text-indigo-600" />
                  {{ opt.label }}
                </label>
              </div>
            </div>

            <!-- Sex / Age / Region -->
            <div class="grid grid-cols-3 gap-3">
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

            <!-- Date / Office -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date of Transaction:</label>
                <input v-model="form.date_of_transaction" type="date" readonly
                  class="w-full rounded-lg border border-slate-100 bg-slate-50 px-2 py-1.5 text-sm text-slate-500 cursor-not-allowed" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Office where service was availed:</label>
                <input :value="officeAvailed" type="text" readonly
                  class="w-full rounded-lg border border-slate-100 bg-slate-50 px-2 py-1.5 text-sm text-slate-500 cursor-not-allowed" />
              </div>
            </div>
          </div>

          <!-- ── Section 2: Service Availed ── -->
          <div class="space-y-2">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide border-b border-slate-100 pb-1">Service Availed <span class="text-slate-400 font-normal normal-case">(please check)</span></h3>
            <div class="grid grid-cols-2 gap-x-4 gap-y-1.5">
              <label v-for="svc in serviceOptions" :key="svc.value" class="flex items-start gap-2 text-sm text-slate-700 cursor-pointer">
                <input type="checkbox" v-model="form.service_availed" :value="svc.value" class="mt-0.5 text-indigo-600 rounded" />
                <span>{{ svc.label }}</span>
              </label>
              <!-- Others with dynamic text -->
              <div class="flex items-start gap-2">
                <input type="checkbox" v-model="form.service_availed" value="others" class="mt-0.5 text-indigo-600 rounded" />
                <span class="text-sm text-slate-700">Others: <span class="font-medium text-indigo-700">{{ serviceOtherLabel }}</span></span>
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

          <!-- ── Section 4: SQD Table ── -->
          <div class="space-y-2">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide border-b border-slate-100 pb-1">Service Quality Dimension (SQD)</h3>
            <p class="text-xs text-slate-500">For SQD 0–8, please check the column that best corresponds to your answer.</p>

            <div class="overflow-x-auto">
              <table class="w-full text-xs border-collapse">
                <thead>
                  <tr class="bg-slate-50">
                    <th class="text-left px-3 py-2 font-medium text-slate-600 w-1/2">Service Quality Dimension (SQD)</th>
                    <th v-for="col in sqdColumns" :key="col.value" class="px-2 py-2 text-center font-medium text-slate-600 whitespace-nowrap">
                      {{ col.label }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in sqdItems" :key="item.key" :class="['border-t border-slate-100', item.idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/50']">
                    <td class="px-3 py-2 text-slate-700 leading-relaxed">
                      <span class="font-semibold">{{ item.key.toUpperCase() }}:</span> {{ item.label }}
                    </td>
                    <td v-for="col in sqdColumns" :key="col.value" class="px-2 py-2 text-center">
                      <input type="radio" :name="item.key" v-model="form[item.key]" :value="col.value"
                        class="text-indigo-600 cursor-pointer" />
                    </td>
                  </tr>
                </tbody>
              </table>
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

          <!-- Actions -->
          <div class="flex gap-3 justify-end pt-2 border-t border-slate-100">
            <button type="button" @click="$emit('close')"
              class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">
              Cancel
            </button>
            <button type="submit" :disabled="submitting"
              class="inline-flex items-center gap-2 px-5 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg font-medium transition-colors">
              {{ submitting ? 'Submitting…' : 'Submit Survey' }}
            </button>
          </div>

        </form>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({
  show:             { type: Boolean, default: false },
  respondableType:  { type: String, required: true },   // e.g. 'it-job-request'
  respondableId:    { type: Number, required: true },
  transactionDate:  { type: String, required: true },   // YYYY-MM-DD
  officeAvailed:    { type: String, required: true },
  serviceKey:       { type: String, default: 'others' }, // 'facility' | 'others'
  serviceOtherLabel:{ type: String, default: '' },       // dynamic label e.g. "IT Job Request"
})

const emit = defineEmits(['close', 'submitted'])

const page      = usePage()
const authUser  = page.props.auth?.user
const submitting = ref(false)
const sqdError   = ref('')

// ── Static options ────────────────────────────────────────────────────────────

const clientTypes = [
  { value: 'citizen',    label: 'Citizen' },
  { value: 'business',   label: 'Business' },
  { value: 'government', label: 'Government (Employee or another agency)' },
]

const serviceOptions = [
  { value: 'school_facilities',   label: 'Availment of school facilities' },
  { value: 'school_credentials',  label: 'Processing of requests for school credentials (Students of the current school year)' },
  { value: 'personnel_documents', label: 'Processing of requests for personnel documents' },
]

const cc1Options = [
  { value: 1, label: '1. I know what a CC is and I saw this office\'s CC.' },
  { value: 2, label: '2. I know what a CC but I did NOT see this office\'s CC.' },
  { value: 3, label: '3. I learned of the CC only when I saw this office\'s CC.' },
  { value: 4, label: '4. I do not know what a CC is and I did not see one in this office. (Answer "NA" on CC2 and CC3)' },
]
const cc2Options = [
  { value: 1, label: '1. Easy to see' },
  { value: 2, label: '2. Somewhat easy to see' },
  { value: 3, label: '3. Difficult to see' },
  { value: 4, label: '4. Not visible at all' },
  { value: 5, label: '5. N/A' },
]
const cc3Options = [
  { value: 1, label: '1. Helped very much' },
  { value: 2, label: '2. Somewhat helped' },
  { value: 3, label: '3. Did not help' },
  { value: 4, label: '4. N/A' },
]

const sqdColumns = [
  { value: 1, label: 'Strongly\nDisagree' },
  { value: 2, label: 'Disagree' },
  { value: 3, label: 'Neither\nAgree nor\nDisagree' },
  { value: 4, label: 'Agree' },
  { value: 5, label: 'Strongly\nAgree' },
  { value: 6, label: 'N/A\nNot\nApplicable' },
]

const sqdItems = [
  { idx: 0, key: 'sqd0', label: 'I am satisfied with the services that I availed.' },
  { idx: 1, key: 'sqd1', label: 'I spent reasonable amount of time for my transaction.' },
  { idx: 2, key: 'sqd2', label: 'The office followed the transaction\'s requirements and steps based on the information provided.' },
  { idx: 3, key: 'sqd3', label: 'The steps (including payment) I needed to do for my transaction were easy and simple.' },
  { idx: 4, key: 'sqd4', label: 'I easily found information about my transaction from the office\'s website.' },
  { idx: 5, key: 'sqd5', label: 'I paid a reasonable amount of fees for my transaction. (If service was free, mark the "N/A" column)' },
  { idx: 6, key: 'sqd6', label: 'I am confident my transaction was secure.' },
  { idx: 7, key: 'sqd7', label: 'The office\'s support was available, and (if asked questions) support was quick to respond.' },
  { idx: 8, key: 'sqd8', label: 'I got what I needed from the government office, or (if denied) denial of request was sufficiently explained to me.' },
]

// ── Form state ─────────────────────────────────────────────────────────────

const buildInitialService = () => {
  if (props.serviceKey === 'facility') return ['school_facilities']
  return ['others']
}

const form = reactive({
  client_type:           'government',
  sex:                   authUser?.sex ?? null,
  age:                   null,
  region_of_residence:   'Caraga',
  date_of_transaction:   props.transactionDate,
  office_availed:        props.officeAvailed,
  service_availed:       buildInitialService(),
  service_availed_other: props.serviceOtherLabel,
  cc1: 1, cc2: 1, cc3: 1,
  sqd0: null, sqd1: null, sqd2: null,
  sqd3: null, sqd4: null, sqd5: null,
  sqd6: null, sqd7: null, sqd8: null,
  suggestions: '',
})

// Reset when modal opens
watch(() => props.show, (val) => {
  if (val) {
    form.sex                   = authUser?.sex ?? null
    form.date_of_transaction   = props.transactionDate
    form.service_availed       = buildInitialService()
    form.service_availed_other = props.serviceOtherLabel
    form.sqd0 = form.sqd1 = form.sqd2 = form.sqd3 = null
    form.sqd4 = form.sqd5 = form.sqd6 = form.sqd7 = form.sqd8 = null
    form.cc1 = 1; form.cc2 = 1; form.cc3 = 1
    form.suggestions = ''
    sqdError.value = ''
  }
})

// ── Submit ────────────────────────────────────────────────────────────────

const submit = async () => {
  sqdError.value = ''

  // Validate all SQD answered
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

  router.post(route('csm.store'), {
    respondable_type:      props.respondableType,
    respondable_id:        props.respondableId,
    client_type:           form.client_type,
    sex:                   form.sex,
    age:                   form.age,
    region_of_residence:   form.region_of_residence,
    date_of_transaction:   form.date_of_transaction,
    office_availed:        form.officeAvailed ?? props.officeAvailed,
    service_availed:       form.service_availed,
    service_availed_other: form.service_availed_other,
    cc1:  form.cc1,
    cc2:  form.cc1 <= 3 ? form.cc2 : null,
    cc3:  form.cc1 <= 3 ? form.cc3 : null,
    sqd0: form.sqd0, sqd1: form.sqd1, sqd2: form.sqd2,
    sqd3: form.sqd3, sqd4: form.sqd4, sqd5: form.sqd5,
    sqd6: form.sqd6, sqd7: form.sqd7, sqd8: form.sqd8,
    suggestions: form.suggestions,
  }, {
    onSuccess: () => {
      emit('submitted')
      emit('close')
      Swal.fire('Thank You!', 'Your Client Satisfaction Survey has been submitted.', 'success')
    },
    onError: (errors) => {
      const msg = Object.values(errors).flat().join('\n') || 'Please check all required fields.'
      Swal.fire('Error', msg, 'error')
    },
    onFinish: () => { submitting.value = false },
  })
}
</script>
