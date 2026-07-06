<template>
  <Head title="Guidance Kiosk" />
  <div class="min-h-screen bg-cover bg-center flex items-center justify-center" :style="bgStyle">
    <div class="absolute inset-0 bg-black opacity-30"></div>

    <div class="relative z-10 w-full max-w-2xl p-6">
      <div class="flex flex-col items-center mb-6">
        <img src="/images/pshslogo.png" alt="Logo" class="w-40 mb-4" />
        <h1 class="text-2xl font-bold text-white">Guidance Consultation Appointment</h1>
      </div>

      <div class="bg-white/90 p-6 rounded-xl shadow-lg">
        <form @submit.prevent="submit">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">PISAY ID</label>
            <input ref="pisayInput" v-model="form.pisay" @input="formatPisay" type="text" maxlength="13" class="mt-1 block w-full rounded border-gray-300" placeholder="13-XXXX-XXX" />
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Preferred date & time (optional)</label>
            <input v-model="form.date_time_preferred" type="datetime-local" class="mt-1 block w-full rounded border-gray-300 p-2" />
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Concern (select one or more)</label>
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
              <label class="inline-flex items-center">
                <input type="checkbox" value="Academic" v-model="form.concerns" class="mr-2" />
                <span>Academic</span>
              </label>
              <label class="inline-flex items-center">
                <input type="checkbox" value="Behavior" v-model="form.concerns" class="mr-2" />
                <span>Behavior</span>
              </label>
              <label class="inline-flex items-center">
                <input type="checkbox" value="Personal / Social" v-model="form.concerns" class="mr-2" />
                <span>Personal / Social</span>
              </label>
            </div>
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Additional details (optional)</label>
            <textarea v-model="form.description" rows="3" class="mt-1 block w-full rounded border-gray-300" placeholder="More details for guidance staff (optional)"></textarea>
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Preferred Guidance Personnel (optional)</label>
            <select v-model="form.assigned_personnel" class="mt-1 block w-full rounded border-gray-300 p-2">
              <option value="">Select a guidance staff (optional)</option>
              <option v-for="u in guidanceUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>

          <div class="flex justify-center">
            <button type="submit" :disabled="form.processing" class="px-8 py-4 bg-success-600 hover:bg-success-700 text-white rounded-lg text-lg disabled:opacity-60 inline-flex items-center justify-center">
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

const props = defineProps({ guidanceUsers: Array })
const guidanceUsers = ref(props.guidanceUsers || [])

const bgStyle = computed(() => ({ backgroundImage: "url('/images/bg.jpg')" }))
const pisayInput = ref(null)

const form = useForm({
  pisay: '',
  date_time_preferred: '',
  concerns: [],
  description: '',
  assigned_personnel: '',
})

function formatPisay(e) {
  let raw = (e.target.value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
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
  // Client-side required fields: pisay, date_time_preferred, concerns
  if (!form.pisay || form.pisay.toString().trim() === '') {
    Swal.fire({ icon: 'error', text: 'Please enter your PISAY ID.' }).then(() => {
      if (pisayInput.value && typeof pisayInput.value.focus === 'function') pisayInput.value.focus()
    })
    return
  }

  // Validate PISAY format: either AA-AAAA-AAA or 9 alphanumeric characters
  const pisayRegex = /^([A-Za-z0-9]{2}-[A-Za-z0-9]{4}-[A-Za-z0-9]{3}|[A-Za-z0-9]{9})$/
  const pisayToCheck = (form.pisay || '').toString().replace(/\s+/g, '')
  if (!pisayRegex.test(pisayToCheck)) {
    Swal.fire({ icon: 'error', text: 'PISAY ID format is invalid. Use format: AA-AAAA-AAA.' }).then(() => {
      if (pisayInput.value && typeof pisayInput.value.focus === 'function') pisayInput.value.focus()
    })
    return
  }

  if (!form.date_time_preferred || form.date_time_preferred.toString().trim() === '') {
    Swal.fire({ icon: 'error', text: 'Please select your preferred date and time.' })
    return
  }

  if (!form.concerns || form.concerns.length === 0) {
    Swal.fire({ icon: 'error', text: 'Please select at least one concern.' })
    return
  }

  form.post(route('guidance.kiosk.store'), {
    onSuccess: () => {
      Swal.fire({ icon: 'success', text: 'Guidance consultation scheduled.' })
      form.reset()
      if (pisayInput.value && typeof pisayInput.value.focus === 'function') pisayInput.value.focus()
    },
    onError: (errors) => {
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
        Swal.fire({ icon: 'error', text: 'Failed to submit.' })
      }
    }
  })
}
</script>
