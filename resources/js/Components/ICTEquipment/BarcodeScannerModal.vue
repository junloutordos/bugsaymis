<script setup>
import { nextTick, ref, watch } from 'vue'
import { BarcodeFormat, BrowserMultiFormatReader } from '@zxing/browser'
import { CameraIcon, CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  show: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'detected'])

const video = ref(null)
const state = ref('starting') // starting | scanning | detected | error
const errorMessage = ref('')
const detectedValue = ref('')

const reader = new BrowserMultiFormatReader(undefined, {
  delayBetweenScanAttempts: 80,
  delayBetweenScanSuccess: 500,
})
reader.possibleFormats = [
  BarcodeFormat.CODE_128,
  BarcodeFormat.CODE_39,
  BarcodeFormat.EAN_13,
  BarcodeFormat.EAN_8,
  BarcodeFormat.UPC_A,
  BarcodeFormat.UPC_E,
  BarcodeFormat.QR_CODE,
]

let scannerControls = null
let lastDetected = ''
let lastDetectedAt = 0

function cameraErrorMessage(error) {
  if (error?.name === 'NotAllowedError') return 'Camera access was denied. Allow camera access for this site in your browser settings, then try again.'
  if (error?.name === 'NotFoundError') return 'No camera was found on this device.'
  return 'The camera could not start. You can still type the serial number manually.'
}

async function startScanner() {
  state.value = 'starting'
  errorMessage.value = ''

  try {
    scannerControls = await reader.decodeFromConstraints({
      audio: false,
      video: {
        facingMode: { ideal: 'environment' },
        width: { ideal: 1280 },
        height: { ideal: 720 },
        focusMode: { ideal: 'continuous' },
      },
    }, video.value, (result) => {
      if (!result || state.value !== 'scanning') return
      const value = result.getText()?.trim()
      if (!value) return

      const now = Date.now()
      if (value === lastDetected && now - lastDetectedAt < 3000) return
      lastDetected = value
      lastDetectedAt = now

      state.value = 'detected'
      detectedValue.value = value
      stopStream()
      setTimeout(() => emit('detected', value), 650)
    })

    state.value = 'scanning'
  } catch (error) {
    state.value = 'error'
    errorMessage.value = cameraErrorMessage(error)
  }
}

function stopStream() {
  scannerControls?.stop()
  scannerControls = null
}

function close() {
  stopStream()
  emit('close')
}

watch(() => props.show, async (visible) => {
  if (visible) {
    detectedValue.value = ''
    lastDetected = ''
    await nextTick()
    startScanner()
  } else {
    stopStream()
  }
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-150"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="show" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm" @click="close" />

        <div class="relative z-10 w-full max-w-md rounded-2xl bg-slate-900 shadow-2xl ring-1 ring-white/10 overflow-hidden">
          <div class="flex items-center justify-between px-5 py-4">
            <div class="flex items-center gap-2 text-white">
              <CameraIcon class="w-5 h-5 text-indigo-400" />
              <h2 class="font-heading text-sm font-semibold">Scan Serial / Barcode</h2>
            </div>
            <button
              type="button"
              @click="close"
              class="rounded-lg p-1.5 text-slate-400 hover:bg-white/10 hover:text-white transition-colors"
              aria-label="Close scanner"
            >
              &times;
            </button>
          </div>

          <div class="relative mx-5 mb-5 aspect-[4/3] overflow-hidden rounded-xl bg-black">
            <video ref="video" class="h-full w-full object-cover" muted playsinline />

            <!-- Targeting frame + scan line (only while actively scanning) -->
            <div v-if="state === 'scanning'" class="pointer-events-none absolute inset-0 flex items-center justify-center">
              <div class="relative h-2/3 w-5/6">
                <span class="absolute -top-0.5 -left-0.5 h-6 w-6 border-t-2 border-l-2 border-indigo-400 rounded-tl-lg" />
                <span class="absolute -top-0.5 -right-0.5 h-6 w-6 border-t-2 border-r-2 border-indigo-400 rounded-tr-lg" />
                <span class="absolute -bottom-0.5 -left-0.5 h-6 w-6 border-b-2 border-l-2 border-indigo-400 rounded-bl-lg" />
                <span class="absolute -bottom-0.5 -right-0.5 h-6 w-6 border-b-2 border-r-2 border-indigo-400 rounded-br-lg" />
                <div class="scan-line absolute inset-x-0 h-0.5 bg-indigo-400 shadow-[0_0_8px_2px_rgba(129,140,248,0.8)]" />
              </div>
            </div>

            <!-- Starting overlay -->
            <div v-if="state === 'starting'" class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-black/40 text-slate-200">
              <svg class="h-6 w-6 animate-spin text-indigo-400" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
              </svg>
              <p class="text-xs">Starting camera&hellip;</p>
            </div>

            <!-- Detected success flash -->
            <Transition
              enter-active-class="transition ease-out duration-150"
              enter-from-class="opacity-0 scale-95"
              enter-to-class="opacity-100 scale-100"
            >
              <div v-if="state === 'detected'" class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-emerald-500/90 text-white">
                <CheckCircleIcon class="w-10 h-10" />
                <p class="text-sm font-semibold">Captured</p>
                <p class="max-w-[80%] truncate text-xs font-mono text-emerald-50">{{ detectedValue }}</p>
              </div>
            </Transition>

            <!-- Error overlay -->
            <div v-if="state === 'error'" class="absolute inset-0 flex flex-col items-center justify-center gap-2 px-6 text-center bg-black/60 text-slate-100">
              <ExclamationTriangleIcon class="w-8 h-8 text-amber-400" />
              <p class="text-xs">{{ errorMessage }}</p>
            </div>
          </div>

          <div class="flex items-center justify-between gap-3 border-t border-white/10 px-5 py-3">
            <p class="text-xs text-slate-400">Point the camera at the device's barcode or QR label.</p>
            <button
              type="button"
              @click="close"
              class="shrink-0 rounded-lg bg-white/10 px-3 py-1.5 text-xs font-medium text-white hover:bg-white/20 transition-colors"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.scan-line {
  animation: scan-line-move 1.8s ease-in-out infinite;
}
@keyframes scan-line-move {
  0% { top: 2%; }
  50% { top: 96%; }
  100% { top: 2%; }
}
@media (prefers-reduced-motion: reduce) {
  .scan-line { animation: none; top: 50%; }
}
</style>
