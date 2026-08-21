<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { ExclamationTriangleIcon, XMarkIcon, HeartIcon, ShieldExclamationIcon, FireIcon, HandRaisedIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  triggerRoute: { type: String, required: true },
})

const page = usePage()
const holdSeconds = computed(() => page.props.sosConfig?.holdConfirmSeconds ?? 3)
const countdownTotal = computed(() => page.props.sosConfig?.countdownSeconds ?? 8)

const CATEGORIES = [
  { value: 'medical', label: 'Medical', icon: HeartIcon },
  { value: 'security', label: 'Security', icon: ShieldExclamationIcon },
  { value: 'fire_disaster', label: 'Fire / Disaster', icon: FireIcon },
  { value: 'general', label: 'General', icon: HandRaisedIcon },
]

const pickerOpen = ref(false)
const selectedCategory = ref(null)
const holding = ref(false)
const holdProgress = ref(0)
const countdownActive = ref(false)
const countdownSeconds = ref(0)
const blockedMessage = ref(null)
const sending = ref(false)

let holdTimer = null
let countdownTimer = null
let silentPressTimer = null

function openPicker() {
  blockedMessage.value = null
  pickerOpen.value = true
}

function closePicker() {
  pickerOpen.value = false
  selectedCategory.value = null
  cancelHold()
}

function selectCategory(value) {
  selectedCategory.value = value
}

function startHold() {
  if (!selectedCategory.value) return
  holding.value = true
  holdProgress.value = 0
  const stepMs = 50
  const totalSteps = (holdSeconds.value * 1000) / stepMs
  let step = 0
  holdTimer = setInterval(() => {
    step++
    holdProgress.value = Math.min(100, (step / totalSteps) * 100)
    if (step >= totalSteps) {
      clearInterval(holdTimer)
      holding.value = false
      startCountdown(selectedCategory.value, false)
    }
  }, stepMs)
}

function cancelHold() {
  if (holdTimer) clearInterval(holdTimer)
  holding.value = false
  holdProgress.value = 0
}

function startCountdown(alertType, isSilent) {
  countdownActive.value = true
  countdownSeconds.value = countdownTotal.value
  countdownTimer = setInterval(() => {
    countdownSeconds.value--
    if (countdownSeconds.value <= 0) {
      clearInterval(countdownTimer)
      countdownActive.value = false
      dispatch(alertType, isSilent)
    }
  }, 1000)
}

function cancelCountdown() {
  if (countdownTimer) clearInterval(countdownTimer)
  countdownActive.value = false
  closePicker()
}

async function dispatch(alertType, isSilent) {
  sending.value = true
  const coords = await captureLocation()

  try {
    const { data } = await axios.post(route(props.triggerRoute), {
      alert_type: alertType,
      is_silent: isSilent,
      lat: coords?.lat ?? null,
      lng: coords?.lng ?? null,
      accuracy: coords?.accuracy ?? null,
    })

    if (!isSilent) {
      pickerOpen.value = false
      selectedCategory.value = null
    }
  } catch (e) {
    if (e.response?.status === 422 && e.response?.data?.blocked) {
      blockedMessage.value = e.response.data
    }
  } finally {
    sending.value = false
  }
}

function captureLocation() {
  return new Promise((resolve) => {
    if (!('geolocation' in navigator)) return resolve(null)
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy }),
      () => resolve(null),
      { enableHighAccuracy: true, timeout: 5000 },
    )
  })
}

// ── Silent/duress: long-press the icon itself, no picker, no visible UI ──
function startSilentPress() {
  silentPressTimer = setTimeout(() => {
    dispatch('security', true)
  }, holdSeconds.value * 1000)
}

function cancelSilentPress() {
  if (silentPressTimer) clearTimeout(silentPressTimer)
}

onBeforeUnmount(() => {
  cancelHold()
  if (countdownTimer) clearInterval(countdownTimer)
  cancelSilentPress()
})
</script>

<template>
  <button
    type="button"
    class="fixed bottom-5 right-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-red-600 text-white shadow-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-400"
    aria-label="SOS Emergency"
    @click="openPicker"
    @pointerdown="startSilentPress"
    @pointerup="cancelSilentPress"
    @pointerleave="cancelSilentPress"
  >
    <ExclamationTriangleIcon class="h-6 w-6" />
  </button>

  <div v-if="pickerOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
    <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Emergency SOS</h2>
        <button type="button" class="text-slate-400 hover:text-slate-600" @click="closePicker">
          <XMarkIcon class="h-5 w-5" />
        </button>
      </div>

      <template v-if="blockedMessage">
        <p class="mb-3 text-sm text-red-700">{{ blockedMessage.message }}</p>
        <p class="text-sm text-slate-600">Emergency hotline: <strong>{{ blockedMessage.emergency_hotline }}</strong></p>
        <button type="button" class="mt-4 w-full rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700" @click="closePicker">Close</button>
      </template>

      <template v-else-if="!countdownActive">
        <p class="mb-3 text-sm text-slate-600">What kind of emergency is this?</p>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="cat in CATEGORIES" :key="cat.value" type="button"
            class="flex flex-col items-center gap-1 rounded-xl border px-3 py-3 text-sm font-medium transition-colors"
            :class="selectedCategory === cat.value ? 'border-red-600 bg-red-50 text-red-700' : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
            @click="selectCategory(cat.value)"
          >
            <component :is="cat.icon" class="h-5 w-5" />
            {{ cat.label }}
          </button>
        </div>

        <button
          v-if="selectedCategory"
          type="button"
          class="relative mt-5 w-full overflow-hidden rounded-xl bg-red-600 px-4 py-3 text-sm font-semibold text-white"
          @pointerdown="startHold" @pointerup="cancelHold" @pointerleave="cancelHold"
        >
          <span class="absolute inset-y-0 left-0 bg-red-800" :style="{ width: holdProgress + '%' }"></span>
          <span class="relative">{{ holding ? 'Keep holding…' : 'Hold to confirm' }}</span>
        </button>
      </template>

      <template v-else>
        <p class="mb-2 text-sm text-slate-600">Sending SOS alert in</p>
        <p class="mb-4 text-4xl font-bold text-red-600">{{ countdownSeconds }}s</p>
        <button type="button" class="w-full rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700" @click="cancelCountdown">Cancel</button>
      </template>
    </div>
  </div>
</template>
