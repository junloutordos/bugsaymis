<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import TapResultCard from '@/Components/TapResultCard.vue'

const props = defineProps({
  qrToken: String,
  classroom: Object,
})

// 'locating' -> 'confirming' -> 'success' | 'gate_failed' | 'location_denied'
const stage = ref('locating')
const errorMessage = ref('')
const result = ref(null)

function locateAndConfirm() {
  stage.value = 'locating'

  navigator.geolocation.getCurrentPosition(
    (position) => confirm(position),
    () => { stage.value = 'location_denied' },
    { enableHighAccuracy: true, timeout: 8000 },
  )
}

async function confirm(position) {
  stage.value = 'confirming'

  try {
    const { data } = await axios.post(route('class-tap-qr.confirm', props.qrToken), {
      latitude: position.coords.latitude,
      longitude: position.coords.longitude,
      accuracy: position.coords.accuracy,
    })

    result.value = data
    stage.value = 'success'
  } catch (e) {
    errorMessage.value = e.response?.data?.message || "We couldn't confirm your location. Please try again."
    stage.value = 'gate_failed'
  }
}

onMounted(locateAndConfirm)
</script>

<template>
  <Head title="Class Tap-In" />

  <div class="min-h-screen bg-slate-50 flex flex-col items-center justify-center px-4 py-8">
    <div class="w-full max-w-sm">

      <!-- School branding -->
      <div class="text-center mb-8">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-widest">Atlas</p>
        <p class="text-sm text-slate-500 mt-1">Class Attendance</p>
      </div>

      <!-- Success: reuse the same result card the NFC path shows -->
      <TapResultCard
        v-if="stage === 'success'"
        :tap-status="result.tapStatus"
        :tapped-at="result.tappedAt"
        :late-minutes="result.lateMinutes"
        :classroom="result.classroom"
        :schedule="result.schedule"
        :teacher-name="result.teacherName"
      />

      <!-- Locating / confirming -->
      <div v-else-if="stage === 'locating' || stage === 'confirming'" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 text-center">
        <div class="mx-auto w-20 h-20 rounded-full flex items-center justify-center bg-slate-100 mb-4">
          <span class="animate-pulse text-2xl">📍</span>
        </div>
        <h1 class="text-xl font-bold text-slate-700 mb-2">
          {{ stage === 'locating' ? 'Confirming your location…' : 'Recording attendance…' }}
        </h1>
        <p class="text-sm text-slate-500">
          {{ classroom ? `${classroom.name} (${classroom.code})` : 'Please allow location access to continue.' }}
        </p>
      </div>

      <!-- Location permission denied -->
      <div v-else-if="stage === 'location_denied'" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 text-center">
        <div class="mx-auto w-20 h-20 rounded-full flex items-center justify-center bg-amber-100 mb-4">
          <span class="text-3xl text-amber-600">!</span>
        </div>
        <h1 class="text-xl font-bold text-amber-700 mb-2">Location Access Needed</h1>
        <p class="text-sm text-slate-600 mb-5">
          We need to confirm you're on campus before recording attendance this way.
          Allow location access and try again, or tap your phone directly on the NFC card in the room instead.
        </p>
        <button
          type="button"
          class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
          @click="locateAndConfirm"
        >
          Try Again
        </button>
      </div>

      <!-- Gate rejected (outside campus / too coarse) -->
      <div v-else-if="stage === 'gate_failed'" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 text-center">
        <div class="mx-auto w-20 h-20 rounded-full flex items-center justify-center bg-red-100 mb-4">
          <span class="text-3xl text-red-600">!</span>
        </div>
        <h1 class="text-xl font-bold text-red-700 mb-2">Couldn't Confirm Attendance</h1>
        <p class="text-sm text-slate-600 mb-5">{{ errorMessage }}</p>
        <button
          type="button"
          class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
          @click="locateAndConfirm"
        >
          Try Again
        </button>
      </div>

      <!-- Footer note -->
      <p class="text-center text-xs text-slate-400 mt-6">
        You may close this tab.
      </p>

    </div>
  </div>
</template>
