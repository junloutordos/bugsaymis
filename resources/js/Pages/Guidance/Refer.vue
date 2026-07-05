<template>
  <Head title="Refer to Guidance" />
  <AdminLayout title="Refer to Guidance">
    <div class="max-w-2xl mx-auto space-y-5">

      <AppPageHeader title="Refer a Student to the Guidance Office" subtitle="Search for a student and fill in the referral details below." />

      <!-- Success banner -->
      <div v-if="successMessage" class="bg-success-50 border border-success-100 text-success-700 rounded-xl px-4 py-3 text-sm">
        {{ successMessage }}
      </div>

      <AppCard>
        <div class="space-y-5">

        <!-- Student Search -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Student <span class="text-red-500">*</span></label>
          <div class="relative">
            <input
              v-model="studentSearchQuery"
              type="text"
              placeholder="Type student name or ID…"
              autocomplete="off"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-red-400': errors.student }"
            />
            <span v-if="searchingStudents" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">Searching…</span>
            <!-- Dropdown -->
            <div v-if="studentSearchResults.length"
                 class="absolute z-20 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-lg max-h-52 overflow-y-auto">
              <button
                v-for="s in studentSearchResults"
                :key="s.id"
                type="button"
                @click="selectStudent(s)"
                class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors"
              >
                {{ s.name }}<span v-if="s.pisay" class="ml-2 text-xs text-slate-400">({{ s.pisay }})</span>
              </button>
            </div>
          </div>
          <p v-if="selectedStudent" class="mt-1 text-xs text-emerald-600 font-medium">
            Selected: {{ selectedStudent.name }}
          </p>
          <p v-if="errors.student" class="text-red-500 text-xs mt-1">{{ errors.student }}</p>
        </div>

        <!-- Referral Category -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-2">Referral Category <span class="text-red-500">*</span></label>
          <div class="flex flex-wrap gap-4">
            <label v-for="cat in referralCategoryOptions" :key="cat" class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
              <input type="checkbox" :value="cat" v-model="form.referral_category"
                     class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
              {{ cat }}
            </label>
          </div>
          <p v-if="errors.referral_category" class="text-red-500 text-xs mt-1">{{ errors.referral_category }}</p>
        </div>

        <!-- Brief Description -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Brief Description of the Concern <span class="text-red-500">*</span></label>
          <textarea
            v-model="form.brief_description"
            rows="4"
            placeholder="Describe the situation or concern…"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"
            :class="{ 'border-red-400': errors.brief_description }"
          ></textarea>
          <p v-if="errors.brief_description" class="text-red-500 text-xs mt-1">{{ errors.brief_description }}</p>
        </div>

        <!-- Behavior Spotted -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-2">Behavior Spotted <span class="text-red-500">*</span></label>
          <div class="space-y-1.5">
            <label v-for="behavior in behaviorOptions" :key="behavior"
                   class="flex items-start gap-2 text-sm text-slate-700 cursor-pointer">
              <input type="checkbox" :value="behavior" v-model="form.behavior_spotted"
                     class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 shrink-0" />
              <span>{{ behavior }}</span>
            </label>
          </div>
          <div v-if="showBehaviorOtherInput" class="mt-2">
            <input
              v-model="form.behavior_other"
              type="text"
              placeholder="Please specify…"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-red-400': errors.behavior_other }"
            />
            <p v-if="errors.behavior_other" class="text-red-500 text-xs mt-1">{{ errors.behavior_other }}</p>
          </div>
          <p v-if="errors.behavior_spotted" class="text-red-500 text-xs mt-1">{{ errors.behavior_spotted }}</p>
        </div>

        <!-- Actions -->
        <div class="flex gap-3 justify-end pt-2 border-t border-slate-100">
          <AppButton
            type="button"
            variant="secondary"
            @click="resetForm"
          >
            Clear
          </AppButton>
          <AppButton
            type="button"
            @click="submitReferral"
            :loading="loading"
          >
            {{ loading ? 'Submitting…' : 'Submit Referral' }}
          </AppButton>
        </div>

        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import Swal from 'sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'

// ── Constants ────────────────────────────────────────────────────────────────

const referralCategoryOptions = ['Academic', 'Behavior', 'Personal / Social']

const behaviorOptions = [
  'Depressed or apathetic mood',
  'Expression of helplessness, hopelessness, or worthlessness',
  'Evidence of crying',
  'Verbal expressions or gestures of suicide',
  'Noticeable changes in mood and/or sudden outbursts',
  'Inappropriate or exaggerated emotional reactions to situations, including lack of emotional response to stressful events',
  'Excessive dependency on others or extreme withdrawal and isolation from others',
  'Excessive activity or talkativeness',
  'Unusual or noticeable changes in interaction patterns with friends or classmates',
  'New or continuous behavior that disrupts the class',
  'Noticeable changes in physical appearance (weight, dress, hygiene)',
  'Extremely poor academic performance or a drastic decline in grades',
  'Others (please specify)',
]

const OTHERS_LABEL = 'Others (please specify)'

// ── State ────────────────────────────────────────────────────────────────────

const studentSearchQuery   = ref('')
const studentSearchResults = ref([])
const selectedStudent      = ref(null)
const searchingStudents    = ref(false)
const loading              = ref(false)
const successMessage       = ref('')
const errors               = ref({})

let searchTimer = null

const form = ref({
  requestor_id:       null,
  referral_category:  [],
  behavior_spotted:   [],
  behavior_other:     '',
  brief_description:  '',
})

const showBehaviorOtherInput = computed(() =>
  form.value.behavior_spotted.includes(OTHERS_LABEL)
)

// ── Student search ────────────────────────────────────────────────────────────

async function fetchStudentSearch() {
  const term = studentSearchQuery.value.trim()
  if (term.length < 2) { studentSearchResults.value = []; return }

  try {
    searchingStudents.value = true
    const url = `${route('guidance.students.search')}?q=${encodeURIComponent(term)}`
    const res  = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    const data = await res.json().catch(() => ({}))
    studentSearchResults.value = res.ok ? (data.students || []) : []
  } catch {
    studentSearchResults.value = []
  } finally {
    searchingStudents.value = false
  }
}

watch(studentSearchQuery, (val) => {
  if (selectedStudent.value && val !== selectedStudent.value.name) {
    selectedStudent.value       = null
    form.value.requestor_id     = null
  }
  clearTimeout(searchTimer)
  searchTimer = setTimeout(fetchStudentSearch, 250)
})

watch(showBehaviorOtherInput, (visible) => {
  if (!visible) form.value.behavior_other = ''
})

function selectStudent(s) {
  selectedStudent.value        = s
  form.value.requestor_id      = s.id
  studentSearchQuery.value     = s.name
  studentSearchResults.value   = []
}

// ── Form ──────────────────────────────────────────────────────────────────────

function resetForm() {
  studentSearchQuery.value   = ''
  studentSearchResults.value = []
  selectedStudent.value      = null
  errors.value               = {}
  successMessage.value       = ''
  form.value = {
    requestor_id:      null,
    referral_category: [],
    behavior_spotted:  [],
    behavior_other:    '',
    brief_description: '',
  }
}

function validate() {
  const e = {}
  if (!form.value.requestor_id)                      e.student             = 'Please select a student.'
  if (!form.value.referral_category.length)          e.referral_category   = 'Please select at least one category.'
  if (!form.value.brief_description?.trim())         e.brief_description   = 'Please enter a brief description.'
  if (!form.value.behavior_spotted.length)           e.behavior_spotted    = 'Please select at least one behavior.'
  if (showBehaviorOtherInput.value && !form.value.behavior_other?.trim())
                                                     e.behavior_other      = 'Please specify the other behavior.'
  errors.value = e
  return Object.keys(e).length === 0
}

async function submitReferral() {
  successMessage.value = ''
  if (!validate() || loading.value) return

  loading.value = true
  try {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
    const res   = await fetch(route('guidance.referrals.store'), {
      method:      'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type':  'application/json',
        'Accept':        'application/json',
        'X-CSRF-TOKEN':  token,
      },
      body: JSON.stringify({
        requestor_id:      form.value.requestor_id,
        referral_category: form.value.referral_category,
        behavior_spotted:  form.value.behavior_spotted,
        behavior_other:    form.value.behavior_other,
        brief_description: form.value.brief_description,
      }),
    })

    const data = await res.json().catch(() => ({}))
    if (!res.ok) {
      Swal.fire({ icon: 'error', text: data.message || 'Failed to submit referral.' })
      return
    }

    successMessage.value = data.message || 'Referral submitted successfully.'
    resetForm()
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch {
    Swal.fire({ icon: 'error', text: 'Unexpected error. Please try again.' })
  } finally {
    loading.value = false
  }
}
</script>
