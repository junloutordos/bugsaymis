<script setup>
import { ref, computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { CheckCircleIcon, ChevronRightIcon, ChevronLeftIcon } from '@heroicons/vue/24/outline'

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
  address:                 '',
  contact_no:              '',
  email:                   '',
})

function next() { if (step.value < STEPS.length - 1) step.value++ }
function prev() { if (step.value > 0) step.value-- }

function submit() {
  form.post(route('enrollment-application.store'))
}

const inputCls = 'rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full'
const labelCls = 'block text-xs font-medium text-slate-600 mb-1'
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

        <!-- Step 0: Grade & Year -->
        <div v-show="step === 0">
          <h2 class="text-base font-semibold text-slate-800 mb-5">Grade Level & School Year</h2>
          <div class="mb-4">
            <label :class="labelCls">Grade Level Applying For</label>
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
                <p v-if="form.errors.lastname" class="text-xs text-red-500 mt-1">{{ form.errors.lastname }}</p>
              </div>
              <div>
                <label :class="labelCls">First Name <span class="text-red-500">*</span></label>
                <input v-model="form.firstname" :class="inputCls" placeholder="Juan" />
                <p v-if="form.errors.firstname" class="text-xs text-red-500 mt-1">{{ form.errors.firstname }}</p>
              </div>
              <div>
                <label :class="labelCls">Middle Name</label>
                <input v-model="form.middlename" :class="inputCls" placeholder="Santos" />
              </div>
            </div>
            <div>
              <label :class="labelCls">Birthday</label>
              <input v-model="form.birthday" type="date" :class="inputCls" />
            </div>
            <div>
              <label :class="labelCls">Sex</label>
              <select v-model="form.sex" :class="inputCls">
                <option value="">— Select —</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
            <div class="sm:col-span-2">
              <label :class="labelCls">Place of Birth</label>
              <input v-model="form.birth_place" :class="inputCls" placeholder="City/Municipality, Province" />
            </div>
            <div>
              <label :class="labelCls">LRN (Learner Reference Number)</label>
              <input v-model="form.lrn" :class="inputCls" placeholder="12-digit LRN" maxlength="12" />
            </div>
          </div>
        </div>

        <!-- Step 2: Academic -->
        <div v-show="step === 2">
          <h2 class="text-base font-semibold text-slate-800 mb-5">Previous School Information</h2>
          <div class="grid grid-cols-1 gap-4">
            <div>
              <label :class="labelCls">Previous School Name</label>
              <input v-model="form.previous_school" :class="inputCls" placeholder="School name" />
            </div>
            <div>
              <label :class="labelCls">School Address</label>
              <input v-model="form.previous_school_address" :class="inputCls" placeholder="City/Municipality" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label :class="labelCls">Grade Level Completed</label>
                <input v-model="form.grade_level_completed" :class="inputCls" placeholder="Grade 6" />
              </div>
              <div>
                <label :class="labelCls">School Year Completed</label>
                <input v-model="form.school_year_completed" :class="inputCls" placeholder="2025-2026" />
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
                <input v-model="form.father_name" :class="inputCls" placeholder="Full name" />
                <input v-model="form.father_occupation" :class="inputCls" placeholder="Occupation" />
                <input v-model="form.father_contact" :class="inputCls" placeholder="Contact number" />
                <input v-model="form.father_email" :class="inputCls" placeholder="Email address" type="email" />
              </div>
            </div>
            <div>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Mother</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <input v-model="form.mother_name" :class="inputCls" placeholder="Full name" />
                <input v-model="form.mother_occupation" :class="inputCls" placeholder="Occupation" />
                <input v-model="form.mother_contact" :class="inputCls" placeholder="Contact number" />
                <input v-model="form.mother_email" :class="inputCls" placeholder="Email address" type="email" />
              </div>
            </div>
            <div>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Guardian <span class="font-normal text-slate-400">(if different from parents)</span></p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <input v-model="form.guardian_name" :class="inputCls" placeholder="Full name" />
                <input v-model="form.guardian_relationship" :class="inputCls" placeholder="Relationship (e.g., Aunt)" />
                <input v-model="form.guardian_contact" :class="inputCls" placeholder="Contact number" />
                <input v-model="form.guardian_email" :class="inputCls" placeholder="Email address" type="email" />
              </div>
            </div>
          </div>
        </div>

        <!-- Step 4: Contact -->
        <div v-show="step === 4">
          <h2 class="text-base font-semibold text-slate-800 mb-5">Contact & Address</h2>
          <div class="space-y-4">
            <div>
              <label :class="labelCls">Home Address</label>
              <textarea v-model="form.address" :class="inputCls" rows="3" placeholder="House No., Street, Barangay, City/Municipality, Province" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label :class="labelCls">Student Contact No.</label>
                <input v-model="form.contact_no" :class="inputCls" placeholder="09XXXXXXXXX" />
              </div>
              <div>
                <label :class="labelCls">Email Address <span class="font-normal text-slate-400">(for notifications)</span></label>
                <input v-model="form.email" :class="inputCls" type="email" placeholder="email@example.com" />
                <p v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</p>
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
            <div class="rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-xs text-amber-800">
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
