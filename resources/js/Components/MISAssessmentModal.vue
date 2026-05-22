<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'

const props = defineProps({
  show:         { type: Boolean, default: false },
  request:      { type: Object, default: null },
  ictEquipment: { type: Array, default: () => [] },
  hasPin:       { type: Boolean, default: false },
  signatureUri: { type: String, default: null },
})

const emit = defineEmits(['close', 'saved'])

// ── Form ─────────────────────────────────────────────────────────────────────
const form = useForm({
  mis_assessment:           '',
  recommendation:           '',
  expected_completion_date: '',
  action_taken:             '',
  completed_at:             '',
  ict_equipment_id:         '',
  pin:                      null,
})

// Populate form when modal opens with a new request
watch(() => props.show, (val) => {
  if (val && props.request) {
    form.mis_assessment           = props.request.mis_assessment           ?? ''
    form.recommendation           = props.request.recommendation           ?? ''
    form.expected_completion_date = props.request.expected_completion_date ?? ''
    form.action_taken             = props.request.action_taken             ?? ''
    form.completed_at             = props.request.completed_at             ?? ''
    form.ict_equipment_id         = props.request.ict_equipment_id         ?? ''
    form.pin                      = null
    eqSearch.value  = ''
    eqDropOpen.value = false
  }
  if (!val) {
    form.reset()
  }
})

// ── Equipment searchable dropdown ─────────────────────────────────────────────
const eqSearch      = ref('')
const eqDropOpen    = ref(false)
const eqSearchInput = ref(null)

const filteredEquipment = computed(() => {
  const q = eqSearch.value.toLowerCase().trim()
  if (!q) return props.ictEquipment
  return props.ictEquipment.filter(eq => {
    const str = [eq.room?.name, eq.owner?.name, eq.description, eq.serial_no]
      .filter(Boolean).join(' ').toLowerCase()
    return str.includes(q)
  })
})

const selectedEquipmentLabel = computed(() => {
  if (!form.ict_equipment_id) return ''
  const eq = props.ictEquipment.find(e => e.id == form.ict_equipment_id)
  if (!eq) return ''
  return [eq.room?.name, eq.owner?.name, eq.description, `(${eq.serial_no})`].filter(Boolean).join(' - ')
})

function openEqDrop() {
  eqSearch.value   = ''
  eqDropOpen.value = true
  nextTick(() => eqSearchInput.value?.focus())
}

function closeEqDrop() {
  setTimeout(() => { eqDropOpen.value = false }, 150)
}

function selectEquipment(eq) {
  form.ict_equipment_id = eq.id ?? ''
  eqSearch.value        = ''
  eqDropOpen.value      = false
}

// ── Digital signature PIN ────────────────────────────────────────────────────
const sigShow    = ref(false)
const sigLoading = ref(false)

function handleFormSubmit() {
  sigShow.value = true
}

function handleSigConfirm(pin) {
  sigShow.value = false
  form.pin = pin
  submitAssessment()
}

function handleSigCancel() {
  sigShow.value = false
}

// ── Submit ────────────────────────────────────────────────────────────────────
function submitAssessment() {
  Swal.fire({
    title: 'Saving assessment...',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => { Swal.showLoading() },
  })

  form.put(`/job-requests/${props.request.id}/update`, {
    preserveScroll: true,
    onSuccess: async () => {
      Swal.close()
      emit('close')
      await Swal.fire('Saved', 'MIS Assessment saved successfully.', 'success')
      emit('saved')
    },
    onError: async (errors) => {
      Swal.close()
      const messages = Object.values(errors).flat().join('\n')
      await Swal.fire('Error', messages || 'Failed to save assessment. Please try again.', 'error')
    },
    onFinish: () => {
      if (!form.processing) Swal.close()
    },
  })
}

function close() {
  if (form.processing) return
  emit('close')
}

const isHardware = computed(() =>
  props.request?.category?.toLowerCase().includes('hardware')
)
</script>

<template>
  <Teleport to="body">
    <div
      v-if="show"
      class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4"
      @mousedown.self="close"
    >
      <div class="bg-white w-full max-w-2xl rounded-2xl shadow-xl max-h-[90vh] overflow-y-auto">

        <!-- Header -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 class="text-base font-semibold text-slate-800">MIS Assessment</h2>
            <p v-if="request" class="text-xs text-slate-400 mt-0.5 font-mono">{{ request.itjr_no }} · {{ request.title }}</p>
          </div>
          <button
            class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
            @click="close"
          ><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleFormSubmit" class="px-6 py-5 space-y-4">

          <!-- Initial Assessment -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Initial Assessment</label>
            <textarea
              v-model="form.mis_assessment"
              rows="3"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
              placeholder="Describe the initial assessment…"
            ></textarea>
          </div>

          <!-- Expected Completion Date -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Expected Date of Completion</label>
            <input
              v-model="form.expected_completion_date"
              type="date"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
            />
          </div>

          <!-- Recommendation -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Recommendation</label>
            <textarea
              v-model="form.recommendation"
              rows="2"
              placeholder="e.g. Repair/Replace defective hardware component(s)"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
            ></textarea>
          </div>

          <!-- Action Taken -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Action Taken</label>
            <textarea
              v-model="form.action_taken"
              rows="3"
              placeholder="Describe what was done to resolve the request…"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
            ></textarea>
          </div>

          <!-- Date Completed -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Date Completed</label>
            <input
              v-model="form.completed_at"
              type="date"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
            />
          </div>

          <!-- ICT Equipment (Hardware only) -->
          <div v-if="isHardware" class="relative">
            <label class="block text-xs font-medium text-slate-600 mb-1">Tag ICT Equipment</label>

            <button
              type="button"
              @click="openEqDrop"
              class="w-full flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <span :class="form.ict_equipment_id ? 'text-slate-800' : 'text-slate-400'" class="truncate">
                {{ selectedEquipmentLabel || '-- Select Equipment --' }}
              </span>
              <svg class="w-4 h-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>

            <div v-if="eqDropOpen" class="absolute z-40 mt-1 w-full rounded-xl border border-slate-200 bg-white shadow-lg">
              <div class="p-2 border-b border-slate-100">
                <input
                  ref="eqSearchInput"
                  v-model="eqSearch"
                  type="text"
                  placeholder="Search by name, owner, serial no…"
                  @blur="closeEqDrop"
                  class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>
              <ul class="max-h-56 overflow-y-auto py-1">
                <li>
                  <button type="button" @mousedown.prevent="selectEquipment({ id: '' })"
                    class="w-full text-left px-3 py-2 text-sm text-slate-400 hover:bg-slate-50">
                    -- Select Equipment --
                  </button>
                </li>
                <li v-if="filteredEquipment.length === 0" class="px-3 py-3 text-center text-sm text-slate-400">
                  No equipment found
                </li>
                <li v-for="eq in filteredEquipment" :key="eq.id">
                  <button
                    type="button"
                    @mousedown.prevent="selectEquipment(eq)"
                    class="w-full text-left px-3 py-2 text-sm transition-colors"
                    :class="form.ict_equipment_id == eq.id
                      ? 'bg-indigo-50 text-indigo-700 font-medium'
                      : 'text-slate-700 hover:bg-indigo-50 hover:text-indigo-700'"
                  >
                    <span class="font-medium">{{ eq.room?.name ?? '—' }}</span>
                    <span class="text-slate-500"> · {{ eq.owner?.name ?? '—' }}</span>
                    <span> · {{ eq.description }}</span>
                    <span class="text-slate-400 text-xs"> ({{ eq.serial_no }})</span>
                  </button>
                </li>
              </ul>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              @click="close"
              :disabled="form.processing"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="form.processing"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60 disabled:cursor-not-allowed min-w-[80px]"
            >
              <svg v-if="form.processing" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              Save
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>

  <!-- Digital Signature PIN -->
  <DigitalSignaturePin
    :show="sigShow"
    :has-pin="hasPin"
    :signature-uri="signatureUri"
    :loading="sigLoading"
    confirm-label="Sign & Save"
    @confirm="handleSigConfirm"
    @cancel="handleSigCancel"
  />
</template>
