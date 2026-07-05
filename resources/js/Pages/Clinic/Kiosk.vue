<template>
  <Head title="Clinic Kiosk" />
  <div class="min-h-screen bg-cover bg-center flex items-center justify-center" :style="bgStyle">
    <div class="absolute inset-0 bg-black opacity-30"></div>

    <div class="relative z-10 w-full max-w-4xl p-6">
      <div class="flex flex-col items-center mb-6">
        <img src="/images/pshslogo.png" alt="Logo" class="w-40 mb-4" />
        <h1 class="text-3xl font-bold text-white">Clinic Consultation</h1>
      </div>

      <div class="grid grid-cols-2 gap-6 mb-6">
        <button @click="setType('walk-in')" :class="type==='walk-in' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-800'" class="py-8 rounded-xl text-center shadow-lg text-lg">Walk-in Consultation</button>
        <button @click="setType('scheduled')" :class="type==='scheduled' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-800'" class="py-8 rounded-xl text-center shadow-lg text-lg">Scheduled Consultation</button>
      </div>

      <div class="bg-white/90 p-6 rounded-xl shadow-lg">
        <form @submit.prevent="submit">
                <div class="mb-4">
                  <label for="kiosk-pisay" class="block text-sm font-medium text-gray-700">PISAY ID</label>
                  <input id="kiosk-pisay" ref="pisayInput" v-model="form.pisay" @input="formatPisay" type="text" maxlength="13" class="mt-1 block w-full rounded border-gray-300" placeholder="13-XXXX-XXX" />
                </div>

          <div v-if="type==='scheduled'" class="mb-4">
            <label for="kiosk-schedule" class="block text-sm font-medium text-gray-700">Choose a schedule</label>
            <div class="mt-2">
              <div v-if="physicianSchedules.length === 0" class="text-sm text-gray-500">No physician schedules available.</div>
              <div v-else>
                <select id="kiosk-schedule" v-model="form.physician_schedule_id" class="mt-1 block w-full rounded border-gray-300 p-2">
                  <option value="" disabled>Select a schedule</option>
                  <option v-for="s in physicianSchedules" :key="s.id" :value="s.id">{{ formatDate(s.schedule_date) }} — {{ s.time_start }}{{ s.time_end ? ' - ' + s.time_end : '' }}</option>
                </select>
              </div>
            </div>
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Concern (select all that apply)</label>
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
              <label class="inline-flex items-center">
                <input type="checkbox" value="Not feeling well" v-model="form.concerns" class="mr-2" />
                <span>Not feeling well</span>
              </label>
              <label class="inline-flex items-center">
                <input type="checkbox" value="Stomachache" v-model="form.concerns" class="mr-2" />
                <span>Stomachache</span>
              </label>
              <label class="inline-flex items-center">
                <input type="checkbox" value="Headache" v-model="form.concerns" class="mr-2" />
                <span>Headache</span>
              </label>
              <label class="inline-flex items-center">
                <input type="checkbox" value="Toothache" v-model="form.concerns" class="mr-2" />
                <span>Toothache</span>
              </label>
              <label class="inline-flex items-center">
                <input type="checkbox" value="Injury" v-model="form.concerns" class="mr-2" />
                <span>Injury</span>
              </label>
              <label class="inline-flex items-center">
                <input type="checkbox" value="Others" v-model="form.concerns" class="mr-2" />
                <span>Others</span>
              </label>
            </div>

            <div v-if="form.concerns.includes('Others')" class="mt-3">
              <label for="kiosk-other-concern" class="block text-sm font-medium text-gray-700">Please specify</label>
              <input id="kiosk-other-concern" v-model="form.other_concern" type="text" class="mt-1 block w-full rounded border-gray-300" placeholder="Describe other concern" />
            </div>
            <div v-if="form.concerns.includes('Injury')" class="mt-3">
              <label for="kiosk-injury-type" class="block text-sm font-medium text-gray-700">Type of injury</label>
              <input id="kiosk-injury-type" v-model="form.injury_type" type="text" class="mt-1 block w-full rounded border-gray-300" placeholder="Describe injury (e.g. cut, sprain)" />
            </div>
          </div>

          <div class="flex justify-center">
            <button type="submit" :disabled="form.processing" class="px-8 py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-lg disabled:opacity-60 inline-flex items-center justify-center">
              <span v-if="form.processing" class="inline-flex items-center">
                <svg class="animate-spin mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                Submitting...
              </span>
              <span v-else>Submit</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm, Head } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({ schedules: Object, physicianSchedules: Array })
const schedules = ref(props.schedules || {})
const physicianSchedules = ref(props.physicianSchedules || [])
const type = ref('walk-in')
const selectedDate = ref(null)
const selectedSlot = ref(null)
const pisayInput = ref(null)

const bgStyle = computed(() => ({ backgroundImage: "url('/images/bg.jpg')" }))

const form = useForm({
  pisay: '',
  consultation_type: 'walk-in',
  doctor_schedule_id: null,
  physician_schedule_id: null,
  concerns: [],
  other_concern: '',
  injury_type: '',
  concern: '',
})

function formatDate(d) {
  if (!d) return '—'
  try {
    const dt = new Date(d)
    return dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
  } catch (e) {
    return d
  }
}

function setType(t) {
  type.value = t
  form.consultation_type = t
  if (t === 'walk-in') {
    selectedDate.value = null
    selectedSlot.value = null
    form.doctor_schedule_id = null
    form.physician_schedule_id = null
  }
}

function selectDate(date) {
  selectedDate.value = date
  selectedSlot.value = null
}

function selectSlot(id) {
  selectedSlot.value = id
  form.doctor_schedule_id = id
}

function formatPisay(e) {
  let raw = (e.target.value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
  // limit to 9 chars (2-4-3)
  raw = raw.slice(0, 9);
  const part1 = raw.slice(0, 2);
  const part2 = raw.slice(2, 6);
  const part3 = raw.slice(6, 9);
  let formatted = part1;
  if (part2) formatted += '-' + part2;
  if (part3) formatted += '-' + part3;
  form.pisay = formatted;
}

function submit() {
  if (type.value === 'scheduled' && !form.doctor_schedule_id && !form.physician_schedule_id) {
    // eslint-disable-next-line no-undef
    Swal.fire({ icon: 'error', text: 'Please select a schedule.' })
    return
  }
  // assemble concern string from selected checkboxes
  const assembled = (form.concerns || []).map(c => {
    if (c === 'Others' && form.other_concern) return `Others: ${form.other_concern}`
    if (c === 'Injury' && form.injury_type) return `Injury: ${form.injury_type}`
    return c
  }).filter(Boolean).join(', ')
  form.concern = assembled

  form.post(route('clinic.kiosk.store'), {
    onSuccess: () => {
      Swal.fire({ icon: 'success', text: 'Clinic visit logged.' })
      form.reset()
      type.value = 'walk-in'
      selectedDate.value = null
      selectedSlot.value = null
      form.physician_schedule_id = null
      if (pisayInput.value && typeof pisayInput.value.focus === 'function') pisayInput.value.focus()
    },
    onError: (errors) => {
      // If PISAY validation failed, show a clear alert
      if (errors && errors.pisay) {
        const pisayMsg = Array.isArray(errors.pisay) ? errors.pisay.join(' ') : errors.pisay
        Swal.fire({ icon: 'error', title: 'PISAY ID not found', text: pisayMsg }).then(() => {
          if (pisayInput.value && typeof pisayInput.value.focus === 'function') pisayInput.value.focus()
        })
        return
      }

      const msg = Object.values(errors || {}).flat().join(' ')
      if (msg) {
        Swal.fire({ icon: 'error', text: msg })
      } else {
        Swal.fire({ icon: 'error', text: 'Failed to submit visit.' })
      }
    },
  })
}
</script>
