<script setup>
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  vacancy:            { type: Object, required: true },
  required_documents: { type: Object, default: () => ({}) },
  campus:             { type: Object, default: () => ({}) },
})

const fmt = (iso) => iso
  ? new Date(iso).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
  : '—'

const daysLeft = computed(() => {
  if (!props.vacancy.closing_date) return null
  return Math.ceil((new Date(props.vacancy.closing_date) - Date.now()) / 86400000)
})

const item     = computed(() => props.vacancy.job_item ?? {})
const type     = computed(() => item.value.recruitment_type ?? {})
const criteria = computed(() => type.value.evaluation_criteria ?? [])

// Use job item requirements if defined, otherwise fall back to the hardcoded list
const docList = computed(() => {
  const reqs = item.value.requirements
  if (reqs && reqs.length) return reqs
  // Convert hardcoded map to array-like for display
  return Object.entries(props.required_documents).map(([key, label]) => ({ id: key, name: label, pivot: { is_mandatory: key !== 'ipcr' } }))
})

const PLANTILLA = ['Plantilla Teaching', 'Plantilla Non-Teaching']
const isPlantilla = computed(() => PLANTILLA.includes(type.value.name))

const levelLabel = { basic: 'Basic', intermediate: 'Intermediate', advanced: 'Advanced' }

// ── Apply modal state ──────────────────────────────────────────────────────
const showModal     = ref(false)
const submitting    = ref(false)
const submitSuccess = ref(false)
const submitErrors  = ref({})

const personalForm = ref({
  first_name: '', middle_name: '', last_name: '', suffix: '',
  birthdate: '', civil_status: 'single', address: '',
  email: '', contact_number: '',
  eligibility: '', prc_license_no: '', school: '', course: '',
  year_graduated: '', is_internal: false, remarks: '',
})

const docFiles = ref({})

const docRequired = { application_letter: true, pds: true, work_experience: true, transcript: true, eligibility: true, ipcr: false }

const openApply = () => {
  submitSuccess.value = false
  submitErrors.value  = {}
  // Reset personal form
  Object.keys(personalForm.value).forEach(k => {
    personalForm.value[k] = typeof personalForm.value[k] === 'boolean' ? false : ''
  })
  personalForm.value.civil_status = 'single'
  // Reset doc files
  docFiles.value = {}
  showModal.value = true
}

const submitApply = async () => {
  submitting.value = true
  submitErrors.value = {}

  const data = new FormData()
  Object.entries(personalForm.value).forEach(([k, v]) => data.append(k, v ?? ''))
  Object.entries(docFiles.value).forEach(([type, file]) => {
    if (file) data.append(`doc_${type}`, file)
  })

  try {
    await axios.post(route('recruitment.public.vacancies.apply', props.vacancy.id), data, {
      headers: { 'Content-Type': 'multipart/form-data', 'X-Inertia': 'false' },
    })
    submitSuccess.value = true
  } catch (err) {
    if (err.response?.status === 422) {
      submitErrors.value = err.response.data.errors ?? {}
    } else {
      submitErrors.value = { _general: ['An unexpected error occurred. Please try again.'] }
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Head :title="`${item.position_title ?? 'Job Opening'} — BugsayMIS`" />

  <div class="min-h-screen relative">
    <!-- Background -->
    <div class="fixed inset-0 bg-cover bg-center -z-10"
         :style="{ backgroundImage: 'url(/storage/bg.jpg)' }" />
    <div class="fixed inset-0 bg-black/55 -z-10" />

    <!-- Top bar -->
    <div class="bg-black/30 backdrop-blur-sm text-white px-4 py-3 flex items-center justify-between border-b border-white/10">
      <Link :href="route('recruitment.public.vacancies.index')"
            class="text-blue-200 hover:text-white text-sm transition">
        ← Back to Job Openings
      </Link>
      <Link :href="route('recruitment.public.track')"
            class="text-blue-200 hover:text-white text-sm transition">
        🔍 Track My Application
      </Link>
    </div>

    <div class="max-w-3xl mx-auto px-4 py-8 space-y-5">

      <!-- ── Header card ──────────────────────────────────────────────────── -->
      <div class="bg-white/95 backdrop-blur rounded-2xl shadow-xl p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="flex-1 min-w-0">
            <span class="inline-block text-xs font-semibold uppercase tracking-wide text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full mb-2">
              {{ type.name ?? '—' }}
            </span>
            <h1 class="text-2xl font-bold text-gray-900 leading-tight">{{ item.position_title }}</h1>
            <p v-if="item.office?.name" class="text-gray-500 text-sm mt-1">{{ item.office.name }}</p>
            <p v-if="item.plantilla_item_no" class="text-gray-400 text-xs mt-0.5">Item No. {{ item.plantilla_item_no }}</p>
          </div>
          <div class="text-right text-sm space-y-1 flex-shrink-0">
            <!-- Plantilla: SG + monthly salary -->
            <template v-if="isPlantilla && item.salary_grade">
              <div class="font-bold text-blue-800 text-lg">SG {{ item.salary_grade }}<span v-if="item.salary_step"> · Step {{ item.salary_step }}</span></div>
              <div v-if="item.monthly_salary" class="text-green-700 font-semibold text-sm">
                ₱{{ Number(item.monthly_salary).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}/mo.
              </div>
            </template>
            <!-- Non-plantilla: daily rate -->
            <template v-else-if="item.daily_rate">
              <div class="font-bold text-green-700 text-lg">
                ₱{{ Number(item.daily_rate).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}/day
              </div>
            </template>
            <div :class="daysLeft !== null && daysLeft <= 3 ? 'text-red-600 font-bold' : 'text-gray-500'" class="text-xs">
              Closes {{ fmt(vacancy.closing_date) }}
              <span v-if="daysLeft !== null && daysLeft <= 7" class="font-semibold"> — {{ daysLeft }}d left</span>
            </div>
            <div class="text-xs text-gray-400">Posted {{ fmt(vacancy.posting_date) }}</div>
          </div>
        </div>

        <!-- Apply button -->
        <button @click="openApply"
                class="mt-5 w-full py-3 bg-blue-600 hover:bg-blue-700 active:scale-[0.99] text-white rounded-xl font-semibold text-sm shadow transition">
          📩 Apply for this Position
        </button>
      </div>

      <!-- ── Qualification Standards ─────────────────────────────────────── -->
      <div v-if="item.education || item.experience || item.training || item.eligibility"
           class="bg-white/95 backdrop-blur rounded-2xl shadow-xl p-6 space-y-4">
        <h2 class="text-base font-semibold text-gray-800 border-b border-gray-100 pb-2">Qualification Standards</h2>

        <div v-if="item.education" class="space-y-0.5">
          <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Education</dt>
          <dd class="text-sm text-gray-700 whitespace-pre-line">{{ item.education }}</dd>
        </div>
        <div v-if="item.experience" class="space-y-0.5">
          <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Experience</dt>
          <dd class="text-sm text-gray-700 whitespace-pre-line">{{ item.experience }}</dd>
        </div>
        <div v-if="item.training" class="space-y-0.5">
          <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Training</dt>
          <dd class="text-sm text-gray-700 whitespace-pre-line">{{ item.training }}</dd>
        </div>
        <div v-if="item.eligibility" class="space-y-0.5">
          <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Eligibility</dt>
          <dd class="text-sm text-gray-700 whitespace-pre-line">{{ item.eligibility }}</dd>
        </div>
      </div>

      <!-- ── Duties & Responsibilities ──────────────────────────────────── -->
      <div v-if="item.duties_responsibilities"
           class="bg-white/95 backdrop-blur rounded-2xl shadow-xl p-6">
        <h2 class="text-base font-semibold text-gray-800 border-b border-gray-100 pb-2 mb-3">Duties &amp; Responsibilities</h2>
        <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ item.duties_responsibilities }}</p>
      </div>

      <!-- ── Competencies ────────────────────────────────────────────────── -->
      <div v-if="item.competencies && item.competencies.length"
           class="bg-white/95 backdrop-blur rounded-2xl shadow-xl p-6">
        <h2 class="text-base font-semibold text-gray-800 border-b border-gray-100 pb-2 mb-3">Competencies Required</h2>
        <div class="space-y-2">
          <div v-for="c in item.competencies" :key="c.name"
               class="flex items-center justify-between text-sm py-1.5 border-b border-gray-50 last:border-0">
            <span class="text-gray-700">{{ c.name }}</span>
            <span v-if="c.level"
                  class="text-xs font-semibold px-2 py-0.5 rounded-full"
                  :class="{
                    'bg-green-100 text-green-700':  c.level === 'basic',
                    'bg-blue-100 text-blue-700':    c.level === 'intermediate',
                    'bg-purple-100 text-purple-700': c.level === 'advanced',
                  }">
              {{ levelLabel[c.level] ?? c.level }}
            </span>
          </div>
        </div>
      </div>

      <!-- ── Requirements to Submit ─────────────────────────────────────── -->
      <div class="bg-white/95 backdrop-blur rounded-2xl shadow-xl p-6">
        <h2 class="text-base font-semibold text-gray-800 border-b border-gray-100 pb-2 mb-3">Requirements to Submit</h2>
        <ul class="space-y-2">
          <li v-for="req in docList" :key="req.id"
              class="flex items-start gap-2 text-sm text-gray-700">
            <span class="mt-0.5 flex-shrink-0"
                  :class="req.pivot?.is_mandatory ? 'text-red-500' : 'text-gray-400'">
              {{ req.pivot?.is_mandatory ? '●' : '○' }}
            </span>
            <span>
              {{ req.name }}
              <span v-if="!req.pivot?.is_mandatory" class="text-gray-400 text-xs ml-1">(optional)</span>
            </span>
          </li>
        </ul>
        <p class="mt-3 text-xs text-gray-400 italic">Consolidate all requirements in a single PDF per document type. Maximum 10MB per file.</p>
      </div>

      <!-- ── Selection Criteria ─────────────────────────────────────────── -->
      <div v-if="criteria.length"
           class="bg-white/95 backdrop-blur rounded-2xl shadow-xl p-6">
        <h2 class="text-base font-semibold text-gray-800 border-b border-gray-100 pb-2 mb-3">Selection Criteria</h2>
        <div class="space-y-2">
          <div v-for="c in criteria" :key="c.id"
               class="flex items-center justify-between text-sm">
            <span class="text-gray-700">{{ c.criteria_name }}</span>
            <span class="font-semibold text-blue-700">{{ c.weight_percentage }}%</span>
          </div>
        </div>
      </div>

    </div><!-- /max-w-3xl -->
  </div><!-- /min-h-screen -->

  <!-- ── Apply Modal ──────────────────────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="showModal"
         class="fixed inset-0 z-50 flex items-start justify-center bg-black/60 px-4 py-6 overflow-y-auto">
      <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl relative my-auto">

        <!-- Modal header -->
        <div class="flex items-start justify-between p-6 border-b border-gray-100">
          <div>
            <h2 class="text-lg font-bold text-gray-800">Online Application</h2>
            <p class="text-sm text-blue-700 font-medium mt-0.5">{{ item.position_title }}</p>
            <p v-if="item.office?.name" class="text-xs text-gray-500">{{ item.office.name }}</p>
          </div>
          <button @click="showModal = false"
                  class="text-gray-400 hover:text-gray-600 text-xl leading-none ml-4 mt-0.5">✖</button>
        </div>

        <!-- Success state -->
        <div v-if="submitSuccess" class="text-center py-12 px-6">
          <div class="text-5xl mb-4">🎉</div>
          <h3 class="text-xl font-bold text-green-700 mb-2">Application Submitted!</h3>
          <p class="text-sm text-gray-600 mb-1">Your documents have been uploaded to our system.</p>
          <p class="text-sm text-gray-500">You will receive an email confirmation with your reference number.</p>
          <div class="flex justify-center gap-3 mt-6">
            <Link :href="route('recruitment.public.track')"
                  class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold">
              🔍 Track My Application
            </Link>
            <button @click="showModal = false"
                    class="px-5 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">
              Close
            </button>
          </div>
        </div>

        <form v-else @submit.prevent="submitApply" class="p-6 space-y-5">

          <div v-if="submitErrors._general"
               class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
            {{ submitErrors._general[0] }}
          </div>

          <!-- Personal Information -->
          <fieldset class="border border-gray-200 rounded-xl p-4">
            <legend class="text-xs font-semibold text-gray-500 uppercase tracking-wide px-1">Personal Information</legend>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-2">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">First Name *</label>
                <input v-model="personalForm.first_name" required type="text"
                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
                <p v-if="submitErrors.first_name" class="text-red-500 text-xs mt-1">{{ submitErrors.first_name[0] }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Middle Name</label>
                <input v-model="personalForm.middle_name" type="text"
                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Last Name *</label>
                <input v-model="personalForm.last_name" required type="text"
                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
                <p v-if="submitErrors.last_name" class="text-red-500 text-xs mt-1">{{ submitErrors.last_name[0] }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Suffix</label>
                <input v-model="personalForm.suffix" type="text" placeholder="Jr., III…"
                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Birthdate *</label>
                <input v-model="personalForm.birthdate" required type="date"
                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
                <p v-if="submitErrors.birthdate" class="text-red-500 text-xs mt-1">{{ submitErrors.birthdate[0] }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Civil Status *</label>
                <select v-model="personalForm.civil_status" required
                        class="w-full rounded-lg border-gray-300 shadow-sm text-sm">
                  <option value="single">Single</option>
                  <option value="married">Married</option>
                  <option value="widowed">Widowed</option>
                  <option value="separated">Separated</option>
                </select>
              </div>
            </div>
            <div class="mt-3">
              <label class="block text-xs font-medium text-gray-600 mb-1">Complete Address *</label>
              <textarea v-model="personalForm.address" required rows="2"
                        class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
              <p v-if="submitErrors.address" class="text-red-500 text-xs mt-1">{{ submitErrors.address[0] }}</p>
            </div>
          </fieldset>

          <!-- Contact Details -->
          <fieldset class="border border-gray-200 rounded-xl p-4">
            <legend class="text-xs font-semibold text-gray-500 uppercase tracking-wide px-1">Contact Details</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Email *</label>
                <input v-model="personalForm.email" required type="email"
                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
                <p v-if="submitErrors.email" class="text-red-500 text-xs mt-1">{{ submitErrors.email[0] }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Contact Number *</label>
                <input v-model="personalForm.contact_number" required type="text"
                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
                <p v-if="submitErrors.contact_number" class="text-red-500 text-xs mt-1">{{ submitErrors.contact_number[0] }}</p>
              </div>
            </div>
          </fieldset>

          <!-- Qualifications -->
          <fieldset class="border border-gray-200 rounded-xl p-4">
            <legend class="text-xs font-semibold text-gray-500 uppercase tracking-wide px-1">Qualifications</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">School / University</label>
                <input v-model="personalForm.school" type="text" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Course / Degree</label>
                <input v-model="personalForm.course" type="text" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Year Graduated</label>
                <input v-model="personalForm.year_graduated" type="number" min="1950" :max="new Date().getFullYear()"
                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Eligibility</label>
                <input v-model="personalForm.eligibility" type="text" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">PRC License No.</label>
                <input v-model="personalForm.prc_license_no" type="text" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
              </div>
              <div class="flex items-end pb-1">
                <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-600">
                  <input v-model="personalForm.is_internal" type="checkbox"
                         class="rounded border-gray-300 text-blue-600 shadow-sm" />
                  I am a current employee
                </label>
              </div>
            </div>
            <div class="mt-3">
              <label class="block text-xs font-medium text-gray-600 mb-1">Cover Letter / Remarks</label>
              <textarea v-model="personalForm.remarks" rows="2" placeholder="Optional…"
                        class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
            </div>
          </fieldset>

          <!-- Required Documents -->
          <fieldset class="border border-blue-200 rounded-xl p-4 bg-blue-50">
            <legend class="text-xs font-semibold text-blue-700 uppercase tracking-wide px-1">
              📎 Required Documents
              <span class="text-blue-400 font-normal normal-case">(PDF/DOC, max 10MB each)</span>
            </legend>
            <p class="text-xs text-blue-600 mt-2 mb-3">Files will be securely uploaded to Google Drive. Consolidate all files in a single PDF per document type.</p>
            <div class="space-y-3">
              <div v-for="(label, key) in required_documents" :key="key">
                <label class="block text-xs font-medium text-gray-700 mb-1">
                  {{ label }}
                  <span v-if="docRequired[key]" class="text-red-500">*</span>
                  <span v-else class="text-gray-400">(optional)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="file"
                         :required="docRequired[key]"
                         :accept="key === 'eligibility' ? '.pdf,.doc,.docx,.jpg,.jpeg,.png' : '.pdf,.doc,.docx'"
                         class="hidden"
                         @change="(e) => docFiles[key] = e.target.files[0]" />
                  <span class="flex-1 px-3 py-1.5 rounded-lg border text-sm truncate"
                        :class="docFiles[key] ? 'border-green-400 bg-green-50 text-green-700' : 'border-gray-300 bg-white text-gray-400'">
                    {{ docFiles[key] ? docFiles[key].name : 'Choose file…' }}
                  </span>
                  <span class="px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs text-gray-600 hover:bg-gray-50 whitespace-nowrap">
                    Browse
                  </span>
                </label>
                <p v-if="submitErrors[`doc_${key}`]" class="text-red-500 text-xs mt-1">{{ submitErrors[`doc_${key}`][0] }}</p>
              </div>
            </div>
          </fieldset>

          <div class="flex justify-end gap-3 pt-1">
            <button type="button" @click="showModal = false"
                    class="px-5 py-2 border border-gray-300 rounded-lg text-gray-700 text-sm hover:bg-gray-50">
              Cancel
            </button>
            <button type="submit" :disabled="submitting"
                    class="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-xl text-sm font-semibold">
              {{ submitting ? 'Uploading & Submitting…' : 'Submit Application' }}
            </button>
          </div>

        </form>
      </div>
    </div>
  </Teleport>
</template>
