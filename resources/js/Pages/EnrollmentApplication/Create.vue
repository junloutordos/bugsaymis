<script setup>
import { ref, computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { CheckCircleIcon, ChevronRightIcon, ChevronLeftIcon } from '@heroicons/vue/24/outline'
import AddressPicker from '@/Components/AddressPicker.vue'

const props = defineProps({
  schoolYear: Object,
})

const STEPS = [
  { key: 'grade',    label: 'Grade & Year' },
  { key: 'personal', label: 'Personal Info' },
  { key: 'academic', label: 'Academic' },
  { key: 'family',   label: 'Family' },
  { key: 'contact',  label: 'Contact' },
  { key: 'review',   label: 'Review' },
]

const step = ref(0)
const stepErrors = ref({})

const form = useForm({
  grade_level:             7,
  lastname:                '',
  firstname:               '',
  middlename:              '',
  birthday:                '',
  sex:                     '',
  birth_place:             '',
  lrn:                     '',
  previous_school:         '',
  previous_school_address: '',
  grade_level_completed:   'Grade 6',
  school_year_completed:   '',
  father_name:             '',
  father_occupation:       '',
  father_contact:          '',
  father_email:            '',
  mother_name:             '',
  mother_occupation:       '',
  mother_contact:          '',
  mother_email:            '',
  guardian_name:           '',
  guardian_relationship:   '',
  guardian_contact:        '',
  guardian_email:          '',
  address:              '',
  address_house:        '',
  address_street:       '',
  address_subdivision:  '',
  address_barangay:     '',
  address_city:         '',
  address_province:     '',
  address_region:       '',
  address_zip:          '',
  contact_no:              '',
  email:                   '',
})

const addressModel = computed({
  get: () => ({
    house:       form.address_house,
    street:      form.address_street,
    subdivision: form.address_subdivision,
    barangay:    form.address_barangay,
    city:        form.address_city,
    province:    form.address_province,
    region:      form.address_region,
    zip:         form.address_zip,
  }),
  set: (val) => {
    form.address_house       = val.house        ?? ''
    form.address_street      = val.street       ?? ''
    form.address_subdivision = val.subdivision  ?? ''
    form.address_barangay    = val.barangay     ?? ''
    form.address_city        = val.city         ?? ''
    form.address_province    = val.province     ?? ''
    form.address_region      = val.region       ?? ''
    form.address_zip         = val.zip          ?? ''
    form.address = [
      val.house, val.street, val.subdivision,
      val.barangay ? 'Brgy. ' + val.barangay : '',
      val.city, val.province,
    ].filter(Boolean).join(', ')
  },
})

// Fields required per step (step 0 is always valid — grade has a default)
const stepFields = {
  1: ['lastname', 'firstname', 'middlename', 'birthday', 'sex', 'birth_place', 'lrn'],
  2: ['previous_school', 'previous_school_address', 'grade_level_completed', 'school_year_completed'],
  3: [
    'father_name', 'father_occupation', 'father_contact', 'father_email',
    'mother_name', 'mother_occupation', 'mother_contact', 'mother_email',
    'guardian_name', 'guardian_relationship', 'guardian_contact', 'guardian_email',
  ],
  4: ['address_city', 'address_region', 'contact_no', 'email'],
}

const addressFieldMessages = {
  address_region: 'Please select a region.',
  address_city:   'Please select a city/municipality.',
}

function validateStep(s) {
  const fields = stepFields[s] ?? []
  const errors = {}
  for (const f of fields) {
    const val = String(form[f] ?? '').trim()
    if (!val) errors[f] = addressFieldMessages[f] ?? 'This field is required. Enter N/A if not applicable.'
    else if (f === 'birthday' && isNaN(Date.parse(val))) errors[f] = 'Please enter a valid date.'
  }
  stepErrors.value = errors
  return Object.keys(errors).length === 0
}

function next() {
  if (validateStep(step.value) && step.value < STEPS.length - 1) step.value++
}

function prev() {
  stepErrors.value = {}
  if (step.value > 0) step.value--
}

function submit() {
  form.post(route('enrollment-application.store'))
}

const inputCls = 'rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full'
const labelCls = 'block text-xs font-medium text-slate-600 mb-1'

function err(field) {
  return stepErrors.value[field] || form.errors[field]
}
</script>

<template>
  <Head title="Enrollment Application — PSHS-CRC" />

  <div class="min-h-screen bg-gradient-to-br from-indigo-50 to-blue-50 py-10 px-4">
    <div class="max-w-2xl mx-auto">

      <!-- Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-indigo-600 mb-4">
          <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 3.741-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-slate-800">Enrollment Application</h1>
        <p class="text-slate-500 text-sm mt-1">Philippine Science High School – Caraga Region Campus</p>
        <p v-if="schoolYear" class="text-indigo-600 text-sm font-medium mt-1">School Year {{ schoolYear.name }}</p>
      </div>

      <!-- Stepper -->
      <div class="flex items-center justify-between mb-8 px-2">
        <template v-for="(s, i) in STEPS" :key="s.key">
          <div class="flex flex-col items-center">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition"
                 :class="i < step ? 'bg-indigo-600 text-white' : i === step ? 'bg-indigo-600 text-white ring-4 ring-indigo-100' : 'bg-slate-200 text-slate-500'">
              <CheckCircleIcon v-if="i < step" class="w-5 h-5" />
              <span v-else>{{ i + 1 }}</span>
            </div>
            <span class="text-xs mt-1 hidden sm:block"
                  :class="i === step ? 'text-indigo-600 font-medium' : 'text-slate-400'">
              {{ s.label }}
            </span>
          </div>
          <div v-if="i < STEPS.length - 1" class="flex-1 h-px mx-1"
               :class="i < step ? 'bg-indigo-400' : 'bg-slate-200'" />
        </template>
      </div>

      <!-- Card -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 md:p-8">

        <!-- N/A notice (shown on all steps except grade and review) -->
        <div v-if="step > 0 && step < 5"
             class="mb-5 rounded-lg bg-warning-50 border border-warning-100 px-4 py-2 text-xs text-warning-700">
          <strong>All fields are required.</strong> Enter <strong>N/A</strong> for any field that does not apply to you.
        </div>

        <!-- Step 0: Grade & Year -->
        <div v-show="step === 0">
          <h2 class="text-base font-semibold text-slate-800 mb-5">Grade Level & School Year</h2>
          <div class="mb-4">
            <label :class="labelCls">Grade Level Applying For <span class="text-red-500">*</span></label>
            <select v-model="form.grade_level" :class="inputCls">
              <option :value="7">Grade 7 (New Student)</option>
              <option :value="8">Grade 8 (Transferee)</option>
              <option :value="9">Grade 9 (Transferee)</option>
              <option :value="10">Grade 10 (Transferee)</option>
              <option :value="11">Grade 11 (Transferee)</option>
              <option :value="12">Grade 12 (Transferee)</option>
            </select>
          </div>
          <div v-if="schoolYear" class="rounded-lg bg-indigo-50 border border-indigo-100 px-4 py-3 text-sm text-indigo-700">
            Applying for: <strong>School Year {{ schoolYear.name }}</strong>
          </div>
        </div>

        <!-- Step 1: Personal Info -->
        <div v-show="step === 1">
          <h2 class="text-base font-semibold text-slate-800 mb-5">Personal Information</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2 grid grid-cols-2 sm:grid-cols-3 gap-4">
              <div>
                <label :class="labelCls">Last Name <span class="text-red-500">*</span></label>
                <input v-model="form.lastname" :class="inputCls" placeholder="Dela Cruz" />
                <p v-if="err('lastname')" class="text-xs text-red-500 mt-1">{{ err('lastname') }}</p>
              </div>
              <div>
                <label :class="labelCls">First Name <span class="text-red-500">*</span></label>
                <input v-model="form.firstname" :class="inputCls" placeholder="Juan" />
                <p v-if="err('firstname')" class="text-xs text-red-500 mt-1">{{ err('firstname') }}</p>
              </div>
              <div>
                <label :class="labelCls">Middle Name <span class="text-red-500">*</span></label>
                <input v-model="form.middlename" :class="inputCls" placeholder="Santos or N/A" />
                <p v-if="err('middlename')" class="text-xs text-red-500 mt-1">{{ err('middlename') }}</p>
              </div>
            </div>
            <div>
              <label :class="labelCls">Birthday <span class="text-red-500">*</span></label>
              <input v-model="form.birthday" type="date" :class="inputCls" />
              <p v-if="err('birthday')" class="text-xs text-red-500 mt-1">{{ err('birthday') }}</p>
            </div>
            <div>
              <label :class="labelCls">Sex <span class="text-red-500">*</span></label>
              <select v-model="form.sex" :class="inputCls">
                <option value="">— Select —</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
              <p v-if="err('sex')" class="text-xs text-red-500 mt-1">{{ err('sex') }}</p>
            </div>
            <div class="sm:col-span-2">
              <label :class="labelCls">Place of Birth <span class="text-red-500">*</span></label>
              <input v-model="form.birth_place" :class="inputCls" placeholder="City/Municipality, Province" />
              <p v-if="err('birth_place')" class="text-xs text-red-500 mt-1">{{ err('birth_place') }}</p>
            </div>
            <div class="sm:col-span-2">
              <label :class="labelCls">LRN (Learner Reference Number) <span class="text-red-500">*</span></label>
              <input v-model="form.lrn" :class="inputCls" placeholder="12-digit LRN or N/A" maxlength="30" />
              <p v-if="err('lrn')" class="text-xs text-red-500 mt-1">{{ err('lrn') }}</p>
            </div>
          </div>
        </div>

        <!-- Step 2: Academic -->
        <div v-show="step === 2">
          <h2 class="text-base font-semibold text-slate-800 mb-5">Previous School Information</h2>
          <div class="grid grid-cols-1 gap-4">
            <div>
              <label :class="labelCls">Previous School Name <span class="text-red-500">*</span></label>
              <input v-model="form.previous_school" :class="inputCls" placeholder="School name" />
              <p v-if="err('previous_school')" class="text-xs text-red-500 mt-1">{{ err('previous_school') }}</p>
            </div>
            <div>
              <label :class="labelCls">School Address <span class="text-red-500">*</span></label>
              <input v-model="form.previous_school_address" :class="inputCls" placeholder="City/Municipality or N/A" />
              <p v-if="err('previous_school_address')" class="text-xs text-red-500 mt-1">{{ err('previous_school_address') }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label :class="labelCls">Grade Level Completed <span class="text-red-500">*</span></label>
                <input v-model="form.grade_level_completed" :class="inputCls" placeholder="Grade 6" />
                <p v-if="err('grade_level_completed')" class="text-xs text-red-500 mt-1">{{ err('grade_level_completed') }}</p>
              </div>
              <div>
                <label :class="labelCls">School Year Completed <span class="text-red-500">*</span></label>
                <input v-model="form.school_year_completed" :class="inputCls" placeholder="2025-2026" />
                <p v-if="err('school_year_completed')" class="text-xs text-red-500 mt-1">{{ err('school_year_completed') }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 3: Family -->
        <div v-show="step === 3">
          <h2 class="text-base font-semibold text-slate-800 mb-5">Parent / Guardian Information</h2>
          <div class="space-y-5">
            <div>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Father</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <input v-model="form.father_name" :class="inputCls" placeholder="Full name or N/A *" />
                  <p v-if="err('father_name')" class="text-xs text-red-500 mt-1">{{ err('father_name') }}</p>
                </div>
                <div>
                  <input v-model="form.father_occupation" :class="inputCls" placeholder="Occupation or N/A *" />
                  <p v-if="err('father_occupation')" class="text-xs text-red-500 mt-1">{{ err('father_occupation') }}</p>
                </div>
                <div>
                  <input v-model="form.father_contact" :class="inputCls" placeholder="Contact number or N/A *" />
                  <p v-if="err('father_contact')" class="text-xs text-red-500 mt-1">{{ err('father_contact') }}</p>
                </div>
                <div>
                  <input v-model="form.father_email" :class="inputCls" placeholder="Email address or N/A *" />
                  <p v-if="err('father_email')" class="text-xs text-red-500 mt-1">{{ err('father_email') }}</p>
                </div>
              </div>
            </div>
            <div>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Mother</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <input v-model="form.mother_name" :class="inputCls" placeholder="Full name or N/A *" />
                  <p v-if="err('mother_name')" class="text-xs text-red-500 mt-1">{{ err('mother_name') }}</p>
                </div>
                <div>
                  <input v-model="form.mother_occupation" :class="inputCls" placeholder="Occupation or N/A *" />
                  <p v-if="err('mother_occupation')" class="text-xs text-red-500 mt-1">{{ err('mother_occupation') }}</p>
                </div>
                <div>
                  <input v-model="form.mother_contact" :class="inputCls" placeholder="Contact number or N/A *" />
                  <p v-if="err('mother_contact')" class="text-xs text-red-500 mt-1">{{ err('mother_contact') }}</p>
                </div>
                <div>
                  <input v-model="form.mother_email" :class="inputCls" placeholder="Email address or N/A *" />
                  <p v-if="err('mother_email')" class="text-xs text-red-500 mt-1">{{ err('mother_email') }}</p>
                </div>
              </div>
            </div>
            <div>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Guardian <span class="font-normal text-slate-400">(if different from parents)</span></p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <input v-model="form.guardian_name" :class="inputCls" placeholder="Full name or N/A *" />
                  <p v-if="err('guardian_name')" class="text-xs text-red-500 mt-1">{{ err('guardian_name') }}</p>
                </div>
                <div>
                  <input v-model="form.guardian_relationship" :class="inputCls" placeholder="Relationship or N/A *" />
                  <p v-if="err('guardian_relationship')" class="text-xs text-red-500 mt-1">{{ err('guardian_relationship') }}</p>
                </div>
                <div>
                  <input v-model="form.guardian_contact" :class="inputCls" placeholder="Contact number or N/A *" />
                  <p v-if="err('guardian_contact')" class="text-xs text-red-500 mt-1">{{ err('guardian_contact') }}</p>
                </div>
                <div>
                  <input v-model="form.guardian_email" :class="inputCls" placeholder="Email address or N/A *" />
                  <p v-if="err('guardian_email')" class="text-xs text-red-500 mt-1">{{ err('guardian_email') }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 4: Contact -->
        <div v-show="step === 4">
          <h2 class="text-base font-semibold text-slate-800 mb-5">Contact & Address</h2>
          <div class="space-y-4">
            <div>
              <label :class="labelCls">Home Address <span class="text-red-500">*</span></label>
              <AddressPicker v-model="addressModel" />
              <p v-if="err('address_region')" class="text-xs text-red-500 mt-1">{{ err('address_region') }}</p>
              <p v-if="err('address_city')" class="text-xs text-red-500 mt-1">{{ err('address_city') }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label :class="labelCls">Student Contact No. <span class="text-red-500">*</span></label>
                <input v-model="form.contact_no" :class="inputCls" placeholder="09XXXXXXXXX or N/A" />
                <p v-if="err('contact_no')" class="text-xs text-red-500 mt-1">{{ err('contact_no') }}</p>
              </div>
              <div>
                <label :class="labelCls">Email Address <span class="text-red-500">*</span> <span class="font-normal text-slate-400">(for notifications)</span></label>
                <input v-model="form.email" :class="inputCls" placeholder="email@example.com or N/A" />
                <p v-if="err('email')" class="text-xs text-red-500 mt-1">{{ err('email') }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 5: Review -->
        <div v-show="step === 5">
          <h2 class="text-base font-semibold text-slate-800 mb-5">Review & Submit</h2>
          <div class="space-y-4 text-sm">
            <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Grade Level</p>
              <p class="font-medium">Grade {{ form.grade_level }} — SY {{ schoolYear?.name }}</p>
            </div>
            <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Student</p>
              <p class="font-medium">{{ [form.lastname, form.firstname, form.middlename].filter(Boolean).join(', ') }}</p>
              <p class="text-slate-500">{{ form.birthday }} · {{ form.sex }} · LRN: {{ form.lrn || '—' }}</p>
            </div>
            <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Contact</p>
              <p>{{ form.email || '—' }} · {{ form.contact_no || '—' }}</p>
            </div>
            <div class="rounded-lg border border-warning-100 bg-warning-50 px-4 py-3 text-xs text-warning-700">
              By submitting, I certify that all information provided is true and correct.
              I understand that providing false information may result in disqualification.
            </div>
          </div>
        </div>

        <!-- Navigation -->
        <div class="flex items-center justify-between mt-8 pt-4 border-t border-slate-100">
          <button v-if="step > 0" @click="prev"
                  class="flex items-center gap-1 px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">
            <ChevronLeftIcon class="w-4 h-4" /> Back
          </button>
          <div v-else />

          <button v-if="step < STEPS.length - 1" @click="next"
                  class="flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-medium">
            Next <ChevronRightIcon class="w-4 h-4" />
          </button>

          <button v-else @click="submit" :disabled="form.processing"
                  class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">
            <CheckCircleIcon class="w-4 h-4" />
            {{ form.processing ? 'Submitting…' : 'Submit Application' }}
          </button>
        </div>

      </div>

      <p class="text-center text-xs text-slate-400 mt-6">
        Philippine Science High School – Caraga Region Campus &bull; MIS Portal
      </p>
    </div>
  </div>
</template>
