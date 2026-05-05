<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage, Link } from '@inertiajs/vue3'
import axios from 'axios'
import { storageUrl } from "@/Composables/useStorage.js"

const props = defineProps({
  vacancies:          { type: Object, required: true },
  types:              { type: Array,  default: () => [] },
  filters:            { type: Object, default: () => ({}) },
  required_documents: { type: Object, default: () => ({}) },
  campus:             { type: Object, default: () => ({}) },
})

const page = usePage()

// ── Filters ────────────────────────────────────────────────────────────────
const search = ref(props.filters.search  ?? '')
const typeId = ref(props.filters.type_id ?? '')

const doSearch = () => {
  router.get(route('recruitment.public.vacancies.index'), {
    search:  search.value  || undefined,
    type_id: typeId.value  || undefined,
  }, { preserveState: true, replace: true })
}

// ── Date helpers ───────────────────────────────────────────────────────────
const fmt = (iso) => iso
  ? new Date(iso).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
  : '—'

const daysLeft = (closing) => Math.ceil((new Date(closing) - Date.now()) / 86400000)

// ── Apply modal state ──────────────────────────────────────────────────────
const showApply  = ref(false)
const selected   = ref(null)
const submitting = ref(false)
const submitSuccess = ref(null)
const submitErrors  = ref({})

// Personal + contact + qual fields
const personalForm = ref({
  first_name: '', middle_name: '', last_name: '', suffix: '',
  birthdate: '', civil_status: 'single', address: '',
  email: '', contact_number: '',
  eligibility: '', prc_license_no: '', school: '', course: '',
  year_graduated: '', is_internal: false, remarks: '',
})

// Document files (keyed by document type)
const docFiles = ref({
  application_letter: null,
  pds:                null,
  work_experience:    null,
  transcript:         null,
  eligibility:        null,
  ipcr:               null,
})

const docRequired = { application_letter: true, pds: true, work_experience: true, transcript: true, eligibility: true, ipcr: false }

const openApply = (vacancy) => {
  selected.value = vacancy
  submitSuccess.value = null
  submitErrors.value  = {}
  Object.keys(personalForm.value).forEach(k => {
    personalForm.value[k] = typeof personalForm.value[k] === 'boolean' ? false : ''
  })
  personalForm.value.civil_status = 'single'
  Object.keys(docFiles.value).forEach(k => docFiles.value[k] = null)
  showApply.value = true
}

const submitApply = async () => {
  submitting.value = true
  submitErrors.value = {}

  const data = new FormData()
  Object.entries(personalForm.value).forEach(([k, v]) => data.append(k, v ?? ''))
  Object.entries(docFiles.value).forEach(([type, file]) => {
    if (file) data.append(`doc_${type}`, file)
  })
  data.append('_method', 'POST')

  try {
    await axios.post(route('recruitment.public.vacancies.apply', selected.value.id), data, {
      headers: { 'Content-Type': 'multipart/form-data', 'X-Inertia': 'false' },
    })
    submitSuccess.value = true
    submitting.value = false
  } catch (err) {
    submitting.value = false
    if (err.response?.status === 422) {
      submitErrors.value = err.response.data.errors ?? {}
    } else {
      submitErrors.value = { _general: ['An unexpected error occurred. Please try again.'] }
    }
  }
}

const successMsg = computed(() => page.props.flash?.success)

const fileLabel = (file) => file ? file.name : 'Choose file…'
</script>

<template>
  <Head title="Job Openings — CRCMIS" />

  <div class="min-h-screen relative">
    <!-- Background -->
    <div class="fixed inset-0 bg-cover bg-center -z-10"
         :style="{ backgroundImage: `url(${storageUrl('bg.jpg')})` }" />
    <div class="fixed inset-0 bg-black/55 -z-10" />

    <!-- Hero -->
    <div class="text-white py-14 px-4 text-center">
      <img v-if="campus.logo" :src="storageUrl(campus.logo)" alt="Campus Logo" class="mx-auto h-16 mb-4 drop-shadow" />
      <img v-else src="/images/pshslogo.png" alt="PSHS Logo" class="mx-auto h-16 mb-4 drop-shadow" />
      <h1 class="text-3xl font-bold tracking-tight drop-shadow">Career Opportunities</h1>
      <p class="mt-2 text-blue-100 text-sm drop-shadow">{{ campus.name ?? 'Philippine Science High School' }}</p>
      <div class="mt-4">
        <Link :href="route('recruitment.public.track')"
              class="inline-flex items-center gap-1 text-white text-sm bg-white/20 hover:bg-white/30 border border-white/30 px-4 py-2 rounded-full transition backdrop-blur-sm">
          🔍 Track My Application
        </Link>
      </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 pb-16">

      <!-- Flash -->
      <div v-if="successMsg" class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">
        {{ successMsg }}
      </div>

      <!-- Search / filter bar -->
      <div class="bg-white/95 backdrop-blur rounded-xl shadow-lg p-4 mb-4 flex flex-col sm:flex-row gap-3">
        <input v-model="search" @keyup.enter="doSearch"
               type="text" placeholder="Search position title…"
               class="flex-1 rounded-lg border-gray-300 shadow-sm text-sm" />
        <select v-model="typeId" @change="doSearch"
                class="rounded-lg border-gray-300 shadow-sm text-sm min-w-[200px]">
          <option value="">All Engagement Types</option>
          <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
        <button @click="doSearch"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">
          Search
        </button>
      </div>

      <!-- Vacancies list -->
      <div v-if="vacancies.data.length" class="bg-white/95 backdrop-blur rounded-xl shadow-lg overflow-hidden">

        <!-- Table header -->
        <div class="grid grid-cols-12 gap-2 px-5 py-3 bg-blue-700 text-white text-xs font-semibold uppercase tracking-wide">
          <div class="col-span-5">Position</div>
          <div class="col-span-2 hidden sm:block">Type</div>
          <div class="col-span-2 hidden md:block">Compensation</div>
          <div class="col-span-2 hidden sm:block">Deadline</div>
          <div class="col-span-3 sm:col-span-1 text-right">Action</div>
        </div>

        <!-- Rows -->
        <div v-for="(v, idx) in vacancies.data" :key="v.id"
             :class="idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'"
             class="grid grid-cols-12 gap-2 px-5 py-4 items-center border-b border-gray-100 hover:bg-blue-50 transition-colors">

          <!-- Position + office -->
          <div class="col-span-9 sm:col-span-5">
            <p class="font-semibold text-gray-900 text-sm leading-tight">{{ v.job_item?.position_title }}</p>
            <p v-if="v.job_item?.office?.name" class="text-xs text-gray-500 mt-0.5">{{ v.job_item.office.name }}</p>
            <p class="text-xs text-gray-400 mt-0.5 sm:hidden">{{ v.job_item?.recruitment_type?.name }}</p>
          </div>

          <!-- Type badge -->
          <div class="col-span-2 hidden sm:flex items-center">
            <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 leading-tight text-center">
              {{ v.job_item?.recruitment_type?.name ?? '—' }}
            </span>
          </div>

          <!-- Compensation -->
          <div class="col-span-2 hidden md:block text-xs text-gray-600">
            <template v-if="v.job_item?.salary_grade">
              <span class="font-medium text-gray-800">SG {{ v.job_item.salary_grade }}</span>
              <span v-if="v.job_item?.salary_step"> · Step {{ v.job_item.salary_step }}</span>
              <div v-if="v.job_item?.monthly_salary" class="text-green-700 font-semibold">
                ₱{{ Number(v.job_item.monthly_salary).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}/mo
              </div>
            </template>
            <template v-else-if="v.job_item?.daily_rate">
              <div class="text-green-700 font-semibold">
                ₱{{ Number(v.job_item.daily_rate).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}/day
              </div>
            </template>
            <span v-else class="text-gray-400">—</span>
          </div>

          <!-- Deadline -->
          <div class="col-span-2 hidden sm:block text-xs">
            <div class="text-gray-500">{{ fmt(v.posting_date) }}</div>
            <div :class="daysLeft(v.closing_date) <= 3 ? 'text-red-600 font-bold' : 'text-gray-700 font-medium'">
              {{ fmt(v.closing_date) }}
            </div>
            <div v-if="daysLeft(v.closing_date) <= 7"
                 :class="daysLeft(v.closing_date) <= 3 ? 'text-red-500' : 'text-orange-500'"
                 class="text-xs font-semibold">
              {{ daysLeft(v.closing_date) }}d left
            </div>
          </div>

          <!-- Actions -->
          <div class="col-span-3 sm:col-span-1 flex flex-col sm:flex-row gap-1 items-end sm:items-center justify-end">
            <button @click="openApply(v)"
                    class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold whitespace-nowrap">
              Apply
            </button>
            <Link :href="route('recruitment.public.vacancies.show', v.id)"
                  class="px-3 py-1.5 border border-gray-300 hover:bg-gray-100 text-gray-600 rounded-lg text-xs whitespace-nowrap">
              Details
            </Link>
          </div>
        </div>

        <!-- Footer row count -->
        <div class="px-5 py-3 text-xs text-gray-400 bg-gray-50 border-t border-gray-100">
          Showing {{ vacancies.from }}–{{ vacancies.to }} of {{ vacancies.total }} open position{{ vacancies.total !== 1 ? 's' : '' }}
          · Sorted by latest posting date
        </div>
      </div>

      <div v-else class="bg-white/90 backdrop-blur rounded-xl shadow-lg p-12 text-center">
        <p class="text-gray-500 text-sm">No open vacancies at this time. Please check back later.</p>
      </div>

      <!-- Pagination -->
      <div v-if="vacancies.last_page > 1" class="mt-4 flex justify-center gap-2">
        <a v-for="link in vacancies.links" :key="link.label"
           :href="link.url ?? '#'"
           v-html="link.label"
           :class="[
             'px-3 py-1.5 rounded-lg text-sm border backdrop-blur-sm',
             link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white/80 text-gray-700 border-gray-200 hover:bg-white',
             !link.url && 'opacity-40 pointer-events-none',
           ]"
           @click.prevent="link.url && router.get(link.url)" />
      </div>
    </div>
  </div>

  <!-- ── Apply Modal ──────────────────────────────────────────────────────── -->
  <div v-if="showApply" class="fixed inset-0 z-50 flex items-start justify-center bg-black/60 px-4 py-8 overflow-y-auto">
    <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl p-6 relative">
      <button @click="showApply = false"
              class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl">✖</button>

      <!-- Success state -->
      <div v-if="submitSuccess" class="text-center py-10">
        <div class="text-5xl mb-4">🎉</div>
        <h2 class="text-xl font-bold text-green-700 mb-2">Application Submitted!</h2>
        <p class="text-sm text-gray-600 mb-2">Your documents have been uploaded to our system.</p>
        <p class="text-sm text-gray-500">You will receive an email confirmation. Use your reference number to track your application status.</p>
        <button @click="showApply = false; submitSuccess = null"
                class="mt-6 px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold">
          Close
        </button>
      </div>

      <template v-else>
        <h2 class="text-xl font-bold text-gray-800 mb-0.5">Online Application</h2>
        <p class="text-sm text-gray-500 mb-5">
          <span class="font-semibold text-blue-700">{{ selected?.job_item?.position_title }}</span>
          · {{ selected?.job_item?.office?.name }}
        </p>

        <div v-if="submitErrors._general" class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
          {{ submitErrors._general[0] }}
        </div>

        <form @submit.prevent="submitApply" class="space-y-5">

          <!-- Personal -->
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

          <!-- Contact -->
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
              <textarea v-model="personalForm.remarks" rows="2"
                        placeholder="Optional…"
                        class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
            </div>
          </fieldset>

          <!-- Required documents -->
          <fieldset class="border border-blue-200 rounded-xl p-4 bg-blue-50">
            <legend class="text-xs font-semibold text-blue-700 uppercase tracking-wide px-1">
              📎 Required Documents <span class="text-blue-500 font-normal normal-case">(PDF/DOC, max 10MB each)</span>
            </legend>
            <p class="text-xs text-blue-600 mt-2 mb-3">Files will be securely uploaded to Google Drive. Consolidate all files in a single PDF per document type as much as possible.</p>
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

          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showApply = false"
                    class="px-5 py-2 border border-gray-300 rounded-lg text-gray-700 text-sm hover:bg-gray-50">
              Cancel
            </button>
            <button type="submit" :disabled="submitting"
                    class="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-lg text-sm font-semibold">
              {{ submitting ? 'Uploading & Submitting…' : 'Submit Application' }}
            </button>
          </div>

        </form>
      </template>
    </div>
  </div>
</template>
