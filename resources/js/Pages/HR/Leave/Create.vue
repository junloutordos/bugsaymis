<template>
  <Head title="File Leave Application" />
  <AdminLayout title="File Leave Application">
    <div class="max-w-2xl mx-auto space-y-5">

      <AppPageHeader title="File Leave Application" subtitle="Submit a new leave request for approval." />

      <!-- Credits summary -->
      <div v-if="credits.length" class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3">
        <p class="text-xs font-semibold text-blue-600 uppercase tracking-wide mb-2">Leave Credit Balance ({{ currentYear }})</p>
        <div class="flex flex-wrap gap-3">
          <div v-for="c in credits" :key="c.id" class="text-xs">
            <span class="font-medium text-slate-700">{{ c.leave_type?.name }}:</span>
            <span class="ml-1 font-bold" :class="Number(c.balance ?? c.earned) > 0 ? 'text-success-600' : 'text-danger-500'">
              {{ c.balance ?? (Number(c.earned) - Number(c.used ?? 0)) }} days
            </span>
          </div>
        </div>
      </div>

      <!-- Form -->
      <AppCard>
        <div class="space-y-5">

          <!-- 6.A Leave Type -->
          <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide mb-2">
              6.A Type of Leave to be Availed of <span class="text-red-500">*</span>
            </label>
            <p v-if="form.errors.leave_type_id" class="text-red-500 text-xs mb-2">{{ form.errors.leave_type_id }}</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5">
              <label
                v-for="lt in officialLeaveTypes"
                :key="lt.code"
                class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer"
                :class="{ 'opacity-40 cursor-not-allowed': !typeByCode[lt.code] }"
              >
                <input
                  type="radio"
                  :value="lt.code"
                  v-model="selectedCode"
                  :disabled="!typeByCode[lt.code]"
                  @change="selectLeaveType(lt.code)"
                  class="accent-indigo-600"
                />
                {{ lt.label }}
              </label>

              <!-- Other purpose row -->
              <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                <input
                  type="radio"
                  value="__other__"
                  v-model="selectedCode"
                  @change="onSelectOtherPurpose"
                  class="accent-indigo-600"
                />
                <span>Other purpose:</span>
              </label>
            </div>

            <!-- Other purpose sub-options -->
            <div v-if="selectedCode === '__other__'" class="mt-2 ml-6 space-y-1.5 border-l-2 border-slate-100 pl-4">
              <label
                v-for="opt in otherPurposeOptions"
                :key="opt.code"
                class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer"
                :class="{ 'opacity-40 cursor-not-allowed': !typeByCode[opt.code] }"
              >
                <input
                  type="radio"
                  :value="opt.code"
                  v-model="otherPurposeCode"
                  :disabled="!typeByCode[opt.code]"
                  @change="selectOtherPurpose(opt.code)"
                  class="accent-indigo-600"
                />
                {{ opt.label }}
              </label>
            </div>
          </div>

          <!-- 6.B Leave Details (conditional) -->
          <div v-if="showLeaveDetails">
            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide mb-2">
              6.B Details of Leave
            </label>

            <div v-if="leaveDetailOptions.length" class="space-y-1.5">
              <label
                v-for="opt in leaveDetailOptions"
                :key="opt.value"
                class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer"
              >
                <input
                  type="radio"
                  :value="opt.value"
                  v-model="form.leave_details"
                  class="accent-indigo-600"
                />
                {{ opt.label }}
              </label>
            </div>

            <AppInput
              v-if="showSpecify"
              v-model="form.leave_details_specify"
              placeholder="Please specify…"
              class="mt-2"
            />
          </div>

          <!-- Date Selection (multi-date, non-consecutive) -->
          <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide mb-2">
              Leave Date(s) <span class="text-red-500">*</span>
            </label>
            <p class="text-xs text-slate-400 mb-2">You can add multiple non-consecutive dates.</p>
            <p v-if="form.errors.dates" class="text-red-500 text-xs mb-2">{{ form.errors.dates }}</p>

            <!-- Date adder -->
            <div class="flex gap-2 items-center">
              <div class="flex-1">
                <AppInput
                  v-model="dateInput"
                  type="date"
                  @keydown.enter.prevent="addDate"
                />
              </div>
              <AppButton type="button" @click="addDate">Add Date</AppButton>
            </div>

            <!-- Selected dates chips -->
            <div v-if="selectedDates.length" class="mt-3 flex flex-wrap gap-2">
              <div
                v-for="d in sortedDates"
                :key="d"
                class="inline-flex items-center gap-1.5 bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs font-medium px-2.5 py-1 rounded-full"
              >
                <span>{{ formatDate(d) }}</span>
                <button
                  type="button"
                  @click="removeDate(d)"
                  class="text-indigo-400 hover:text-indigo-700 leading-none"
                  title="Remove"
                ><XMarkIcon class="h-4 w-4 shrink-0" /></button>
              </div>
            </div>

            <!-- Working days preview -->
            <div v-if="selectedDates.length" class="mt-2 bg-slate-50 rounded-lg px-4 py-2 text-sm text-slate-600">
              Selected: <strong class="text-slate-800">{{ selectedDates.length }}</strong> date(s) &mdash;
              Estimated working days: <strong class="text-slate-800">{{ computedDays }}</strong>
            </div>
          </div>

          <!-- Reason -->
          <AppTextarea v-model="form.reason" label="Reason / Remarks" placeholder="Optional remarks…" :rows="3" />

          <!-- Supporting Document -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Supporting Document <span class="font-normal text-slate-400">(PDF/JPG/PNG, max 5MB)</span></label>
            <input @change="e => form.supporting_document = e.target.files[0]"
                   type="file" accept=".pdf,.jpg,.jpeg,.png"
                   class="w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border file:border-slate-200 file:text-xs file:font-medium file:bg-slate-50 hover:file:bg-slate-100" />
            <p v-if="form.errors.supporting_document" class="text-red-500 text-xs mt-1">{{ form.errors.supporting_document }}</p>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 justify-end pt-2">
            <AppButton as="link" variant="secondary" :href="route('hr.leave.index')">Cancel</AppButton>
            <AppButton type="button" @click="openPinModal" :disabled="form.processing" :loading="form.processing">
              {{ form.processing ? 'Filing…' : 'File Application' }}
            </AppButton>
          </div>

        </div>
      </AppCard>
    </div>
    <DigitalSignaturePin
      :show="showSubmitPin"
      :hasPin="hasPin"
      :signatureUri="signatureUri"
      :loading="pinLoading"
      confirmLabel="Sign & File"
      @confirm="handlePinConfirm"
      @cancel="handlePinCancel"
    />
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppButton from '@/Components/AppButton.vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  leaveTypes:   Array,
  credits:      Array,
  hasPin:       { type: Boolean, default: false },
  signatureUri: { type: String, default: null },
})

const currentYear = new Date().getFullYear()

// Official CSC form 6.A leave types in order
const officialLeaveTypes = [
  { code: 'VL',      label: 'Vacation Leave' },
  { code: 'FL',      label: 'Mandatory/Forced Leave' },
  { code: 'SL',      label: 'Sick Leave' },
  { code: 'ML',      label: 'Maternity Leave' },
  { code: 'PL',      label: 'Paternity Leave' },
  { code: 'SPL',     label: 'Special Privilege Leave' },
  { code: 'SL-SOLO', label: 'Solo Parent Leave' },
  { code: 'STUDY',   label: 'Study Leave' },
  { code: 'SL-VAWC', label: '10-Day VAWC Leave' },
  { code: 'RP',      label: 'Rehabilitation Privilege' },
  { code: 'SLBW',    label: 'Special Leave Benefits for Women' },
  { code: 'SECL',    label: 'Special Emergency (Calamity) Leave' },
  { code: 'AL',      label: 'Adoption Leave' },
  { code: 'WL',      label: 'Wellness Leave' },
]

const otherPurposeOptions = [
  { code: 'MONO', label: 'Monetization of Leave Credits' },
  { code: 'TL',   label: 'Terminal Leave' },
  { code: 'CTO',  label: 'Compensatory Time Off' },
]

// code → leaveType object map
const typeByCode = computed(() => {
  const map = {}
  for (const t of props.leaveTypes) map[t.code] = t
  return map
})

const selectedCode     = ref('')
const otherPurposeCode = ref('')
const dateInput        = ref('')
const selectedDates    = ref([])   // plain ref — avoids reactive-proxy issues with FormData

const form = useForm({
  leave_type_id:         '',
  dates:                 [],
  leave_details:         '',
  leave_details_specify: '',
  reason:                '',
  supporting_document:   null,
  pin:                   null,
})

const showSubmitPin = ref(false)
const pinLoading = ref(false)

const openPinModal = () => { showSubmitPin.value = true }
const handlePinCancel = () => { showSubmitPin.value = false }
const handlePinConfirm = (pin) => {
  form.pin = pin || null
  showSubmitPin.value = false
  submit()
}

// ── Leave type selection ─────────────────────────────────────────────────────

const selectLeaveType = (code) => {
  otherPurposeCode.value     = ''
  form.leave_type_id         = typeByCode.value[code]?.id ?? ''
  form.leave_details         = ''
  form.leave_details_specify = ''
}

const onSelectOtherPurpose = () => {
  otherPurposeCode.value     = ''
  form.leave_type_id         = ''
  form.leave_details         = ''
  form.leave_details_specify = ''
}

const selectOtherPurpose = (code) => {
  form.leave_type_id         = typeByCode.value[code]?.id ?? ''
  form.leave_details         = ''
  form.leave_details_specify = ''
}

const activeCode = computed(() => {
  if (selectedCode.value === '__other__') return otherPurposeCode.value
  return selectedCode.value
})

// ── 6.B details ──────────────────────────────────────────────────────────────

const showLeaveDetails = computed(() =>
  ['VL', 'SL', 'SPL', 'STUDY', 'SLBW'].includes(activeCode.value)
)

const leaveDetailOptions = computed(() => {
  const code = activeCode.value
  if (code === 'VL') return [
    { value: 'within_philippines', label: 'Within the Philippines' },
    { value: 'abroad',             label: 'Abroad' },
  ]
  if (code === 'SL') return [
    { value: 'in_hospital', label: 'In Hospital (Confinement)' },
    { value: 'out_patient', label: 'Out Patient' },
  ]
  if (code === 'SPL') return [
    { value: 'graduation', label: 'Graduation/Board Exam' },
    { value: 'enrollment', label: 'Enrollment' },
    { value: 'wedding',    label: 'Wedding/Anniversary' },
    { value: 'burial',     label: 'Burial/Death Anniversary' },
    { value: 'accident',   label: 'Accident' },
    { value: 'other',      label: 'Other' },
  ]
  if (code === 'STUDY') return [
    { value: 'master_degree',    label: "Completion of Master's Degree" },
    { value: 'bar_board_review', label: 'BAR/Board Examination Review' },
  ]
  return []
})

const showSpecify = computed(() => {
  const code    = activeCode.value
  const details = form.leave_details
  if (code === 'SLBW') return true
  if (code === 'SPL' && details === 'other') return true
  if ((code === 'VL' || code === 'SL') && details !== '') return true
  return false
})

// ── Multi-date picker ─────────────────────────────────────────────────────────

const sortedDates = computed(() =>
  [...selectedDates.value].sort((a, b) => a.localeCompare(b))
)

const addDate = () => {
  const d = dateInput.value
  if (!d) return
  if (selectedDates.value.includes(d)) return  // no duplicates
  selectedDates.value.push(d)
  dateInput.value = ''
}

const removeDate = (d) => {
  const idx = selectedDates.value.indexOf(d)
  if (idx !== -1) selectedDates.value.splice(idx, 1)
}

const formatDate = (iso) => {
  const [y, m, day] = iso.split('-')
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
  return `${months[+m - 1]} ${+day}, ${y}`
}

// Count only weekdays in the selected dates
const computedDays = computed(() => {
  return selectedDates.value.filter(iso => {
    const dow = new Date(iso + 'T00:00:00').getDay()
    return dow !== 0 && dow !== 6
  }).length
})

// ── Submit ────────────────────────────────────────────────────────────────────

function submit() {
  // Copy into form as a plain array right before posting so Inertia's
  // objectToFormData receives a normal array, not a reactive proxy.
  form.dates = [...selectedDates.value]
  form.post(route('hr.leave.store'))
}
</script>
