<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import { BarcodeFormat, BrowserMultiFormatReader } from '@zxing/browser'

const props = defineProps({
  gateLocations: { type: Object, default: () => ({}) },
  device: { type: Object, default: null },
  canPairDevice: { type: Boolean, default: false },
  operator: { type: Object, default: null },
  guards: { type: Array, default: () => [] },
})

const video = ref(null)
const scannerState = ref('stopped') // stopped | starting | scanning | submitting | result
const cameraError = ref('')
const online = ref(navigator.onLine)
const lastScan = ref(null)
const scanStatus = ref('')
const photoError = ref(false)
const clock = ref('')
const manualBarcode = ref('')
const activeOperator = ref(props.operator)
const selectedGuard = ref(null)
const pin = ref('')
const unlockError = ref('')
const unlocking = ref(false)

const pairForm = useForm({
  name: '',
  gate_location: Object.keys(props.gateLocations)[0] ?? '',
})

const reader = new BrowserMultiFormatReader(undefined, {
  delayBetweenScanAttempts: 120,
  delayBetweenScanSuccess: 1000,
})
reader.possibleFormats = [BarcodeFormat.CODE_128, BarcodeFormat.QR_CODE]

let scannerControls = null
let resultTimer = null
let heartbeatTimer = null
let clockTimer = null
let audioContext = null
let lastDetectedBarcode = ''
let lastDetectedAt = 0

function updateClock() {
  clock.value = new Date().toLocaleTimeString('en-PH', {
    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true,
  })
}

function pairDevice() {
  pairForm.post(route('student-attendance.devices.pair'), { preserveScroll: true })
}

async function startScanner() {
  if (!props.device || !activeOperator.value || scannerState.value === 'starting' || scannerState.value === 'scanning') return

  cameraError.value = ''
  scannerState.value = 'starting'

  try {
    await axios.get(route('student-attendance.kiosk.status'))
    const BrowserAudioContext = window.AudioContext || window.webkitAudioContext
    if (BrowserAudioContext) audioContext ??= new BrowserAudioContext()

    scannerControls = await reader.decodeFromConstraints({
      audio: false,
      video: {
        facingMode: { ideal: 'environment' },
        width: { ideal: 1920 },
        height: { ideal: 1080 },
      },
    }, video.value, (result) => {
      if (!result || scannerState.value !== 'scanning') return
      const barcode = result.getText()?.trim()
      if (!barcode) return

      const detectedAt = Date.now()
      if (barcode === lastDetectedBarcode && detectedAt - lastDetectedAt < 5000) return
      lastDetectedBarcode = barcode
      lastDetectedAt = detectedAt
      submitScan(barcode)
    })

    scannerState.value = 'scanning'
  } catch (error) {
    stopScanner()
    if (error.response?.status === 401 || error.response?.status === 403 || error.response?.status === 419) {
      expireScannerAccess('Your scanner session expired. Select your name and enter your PIN again.')
      return
    }

    if (error?.name === 'NotAllowedError') {
      cameraError.value = 'Camera access was denied. Allow camera access for this site in Safari settings.'
    } else if (error?.name === 'NotFoundError') {
      cameraError.value = 'No camera was found on this iPad.'
    } else {
      cameraError.value = 'The camera could not start. Check the connection and camera permission, then try again.'
    }
  }
}

function stopScanner() {
  scannerControls?.stop()
  scannerControls = null
  if (video.value?.srcObject) {
    video.value.srcObject.getTracks().forEach(track => track.stop())
    video.value.srcObject = null
  }
  scannerState.value = 'stopped'
}

function expireScannerAccess(message) {
  stopScanner()
  activeOperator.value = null
  selectedGuard.value = null
  pin.value = ''
  unlockError.value = message
  scannerState.value = 'stopped'
}

function selectGuard(guard) {
  selectedGuard.value = guard
  pin.value = ''
  unlockError.value = ''
}

function pressPin(value) {
  if (!selectedGuard.value || pin.value.length >= 6) return
  pin.value += String(value)
  unlockError.value = ''
}

function erasePin() {
  pin.value = pin.value.slice(0, -1)
}

async function unlockScanner() {
  if (!selectedGuard.value || pin.value.length !== 6 || unlocking.value) return
  unlocking.value = true
  unlockError.value = ''

  try {
    const { data } = await axios.post(route('student-attendance.kiosk.unlock'), {
      operator_id: selectedGuard.value.id,
      pin: pin.value,
    })
    activeOperator.value = data.operator
    selectedGuard.value = null
    pin.value = ''
    scannerState.value = 'stopped'
  } catch (error) {
    if (error.response?.status === 429) {
      const seconds = error.response.data?.retry_after ?? 900
      unlockError.value = `Too many incorrect attempts. Try again in ${Math.ceil(seconds / 60)} minute(s).`
    } else {
      const remaining = error.response?.data?.attempts_remaining
      unlockError.value = remaining === undefined
        ? 'Scanner access could not be verified.'
        : `Incorrect PIN. ${remaining} attempt(s) remaining.`
    }
    pin.value = ''
  } finally {
    unlocking.value = false
  }
}

async function endShift() {
  stopScanner()
  try {
    await axios.post(route('student-attendance.kiosk.lock'))
  } finally {
    activeOperator.value = null
    selectedGuard.value = null
    pin.value = ''
    lastScan.value = null
    scanStatus.value = ''
    unlockError.value = 'Shift ended. Select the next guard to continue.'
  }
}

async function submitScan(barcode, captureMethod = 'camera') {
  if (!props.device || !['scanning'].includes(scannerState.value)) return

  scannerState.value = 'submitting'
  clearTimeout(resultTimer)

  try {
    const { data } = await axios.post(route('student-attendance.scan'), {
      barcode,
      scan_uuid: crypto.randomUUID(),
      device_scan_time: new Date().toISOString(),
      capture_method: captureMethod,
    })

    lastScan.value = data.data
    scanStatus.value = data.status
    scannerState.value = 'result'
    playTone(data.status, data.data?.type)
  } catch (error) {
    if (error.response?.status === 401 || error.response?.status === 403 || error.response?.status === 419) {
      expireScannerAccess('Scanner access expired. Select your name and enter your PIN again.')
      return
    }

    lastScan.value = {
      student_name: error.response?.status === 404 ? 'Unknown ID' : 'Scan Not Recorded',
      barcode,
      type: null,
    }
    scanStatus.value = 'error'
    scannerState.value = 'result'
    playTone('error')
  }

  if (scannerState.value === 'result') {
    resultTimer = setTimeout(() => {
      lastScan.value = null
      scanStatus.value = ''
      scannerState.value = online.value ? 'scanning' : 'stopped'
    }, 2500)
  }
}

function submitManual() {
  const barcode = manualBarcode.value.trim()
  if (!barcode || scannerState.value !== 'scanning') return
  manualBarcode.value = ''
  submitScan(barcode, 'manual')
}

function playTone(status, type = null) {
  if (!audioContext) return
  const oscillator = audioContext.createOscillator()
  const gain = audioContext.createGain()
  oscillator.frequency.value = status === 'error' ? 190 : status === 'duplicate' ? 330 : type === 'out' ? 520 : 760
  gain.gain.setValueAtTime(0.12, audioContext.currentTime)
  gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.18)
  oscillator.connect(gain).connect(audioContext.destination)
  oscillator.start()
  oscillator.stop(audioContext.currentTime + 0.2)
}

async function verifySession() {
  if (!props.device || !activeOperator.value || document.hidden) return
  try {
    await axios.get(route('student-attendance.kiosk.status'))
  } catch (error) {
    if ([401, 403, 419].includes(error.response?.status)) {
      expireScannerAccess('Scanner access expired or this iPad was revoked. Enter your PIN again to continue.')
    }
  }
}

function handleVisibility() {
  if (document.hidden) {
    stopScanner()
  } else if (activeOperator.value) {
    verifySession()
  }
}

function handleOffline() {
  online.value = false
  if (scannerState.value === 'scanning') stopScanner()
  cameraError.value = 'The scanner is offline. No attendance will be recorded until the connection returns.'
}

function handleOnline() {
  online.value = true
  cameraError.value = ''
}

const displayDate = computed(() => new Date().toLocaleDateString('en-PH', {
  weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
}))

const badgeLabel = computed(() => {
  if (scanStatus.value === 'error') return 'NOT RECORDED'
  if (scanStatus.value === 'duplicate') return 'ALREADY SCANNED'
  return lastScan.value?.type === 'in' ? 'TIME IN' : 'TIME OUT'
})

const resultClass = computed(() => {
  if (scanStatus.value === 'error') return 'result-error'
  if (scanStatus.value === 'duplicate') return 'result-duplicate'
  return lastScan.value?.type === 'in' ? 'result-in' : 'result-out'
})

const scanTime = computed(() => lastScan.value?.scan_time
  ? new Date(lastScan.value.scan_time).toLocaleTimeString('en-PH', {
      hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true,
    })
  : '')

const initials = computed(() => (lastScan.value?.student_name ?? '?')
  .split(/[\s,]+/).filter(Boolean).map(word => word[0]).slice(0, 2).join('').toUpperCase())

watch(lastScan, () => { photoError.value = false })

onMounted(() => {
  updateClock()
  clockTimer = setInterval(updateClock, 1000)
  heartbeatTimer = setInterval(verifySession, 60000)
  document.addEventListener('visibilitychange', handleVisibility)
  window.addEventListener('offline', handleOffline)
  window.addEventListener('online', handleOnline)
})

onUnmounted(() => {
  stopScanner()
  clearTimeout(resultTimer)
  clearInterval(heartbeatTimer)
  clearInterval(clockTimer)
  audioContext?.close()
  document.removeEventListener('visibilitychange', handleVisibility)
  window.removeEventListener('offline', handleOffline)
  window.removeEventListener('online', handleOnline)
})
</script>

<template>
  <Head title="Gate Camera Scanner" />

  <div class="kiosk-root">
    <header class="kiosk-header">
      <div class="flex items-center gap-3">
        <img src="/images/pshslogo.png" alt="PSHS" class="h-11 w-11 object-contain" />
        <div>
          <p class="font-bold text-white">PSHS–CRC Student Gate Attendance</p>
          <p class="text-xs text-slate-400">{{ displayDate }} · {{ clock }}</p>
        </div>
      </div>
      <div class="text-right">
        <p class="text-sm font-semibold text-white">{{ device?.gate_label ?? 'Unregistered iPad' }}</p>
        <p class="text-xs" :class="online ? 'text-emerald-400' : 'text-red-400'">
          {{ online ? '● Online' : '● Offline' }}<span v-if="device"> · {{ device.name }}</span>
        </p>
        <div v-if="activeOperator" class="mt-1 flex items-center justify-end gap-2">
          <span class="text-xs text-slate-300">Operator: {{ activeOperator.name }}</span>
          <Link
            v-if="activeOperator.is_administrator"
            :href="route('student-attendance.devices.finish-setup')"
            method="post"
            as="button"
            class="text-xs font-semibold text-amber-300 hover:text-amber-200"
          >Finish Setup</Link>
          <button v-else class="text-xs font-semibold text-red-300 hover:text-red-200" @click="endShift">End Shift</button>
        </div>
      </div>
    </header>

    <main v-if="!device" class="setup-panel">
      <div class="setup-card">
        <h1 class="text-2xl font-bold text-white">This iPad is not registered</h1>
        <p class="mt-2 text-sm text-slate-300">
          An Administrator must pair this iPad with a fixed gate before camera scanning can start.
        </p>

        <form v-if="canPairDevice" class="mt-6 space-y-4" @submit.prevent="pairDevice">
          <label class="block text-sm text-slate-200">
            Device name
            <input v-model="pairForm.name" required class="setup-input" placeholder="Main Gate iPad 1" />
          </label>
          <label class="block text-sm text-slate-200">
            Assigned gate
            <select v-model="pairForm.gate_location" required class="setup-input">
              <option v-for="(label, key) in gateLocations" :key="key" :value="key">{{ label }}</option>
            </select>
          </label>
          <p v-if="pairForm.hasErrors" class="text-sm text-red-300">Please correct the device information.</p>
          <button class="primary-button w-full" :disabled="pairForm.processing">
            {{ pairForm.processing ? 'Registering…' : 'Register This iPad' }}
          </button>
        </form>

        <p v-else class="mt-6 rounded-xl bg-amber-500/10 p-4 text-sm text-amber-200">
          Please ask an Administrator to sign in on this iPad and complete registration.
        </p>
        <a v-if="!canPairDevice" :href="route('student-attendance.kiosk.admin-setup')" class="primary-button mt-5 inline-block">Administrator Sign In</a>
      </div>
    </main>

    <main v-else-if="!activeOperator" class="setup-panel">
      <div class="guard-login-card">
        <div class="text-center">
          <h1 class="text-3xl font-bold text-white">Guard Sign In</h1>
          <p class="mt-2 text-slate-300">Select your name and enter your six-digit gate scanner PIN.</p>
        </div>

        <p v-if="unlockError" class="mt-5 rounded-xl bg-red-500/15 p-3 text-center text-sm text-red-200">{{ unlockError }}</p>

        <div v-if="!guards.length" class="mt-6 rounded-xl bg-amber-500/10 p-5 text-center text-amber-100">
          No Security Guard PINs are configured. Ask an Administrator to set up guard access.
          <a :href="route('student-attendance.kiosk.admin-setup')" class="mt-4 block font-semibold text-indigo-300">Administrator Sign In</a>
        </div>

        <template v-else>
          <div class="guard-grid">
            <button
              v-for="guard in guards"
              :key="guard.id"
              type="button"
              class="guard-button"
              :class="{ 'guard-button-selected': selectedGuard?.id === guard.id }"
              @click="selectGuard(guard)"
            >
              <span class="guard-avatar">{{ guard.name.charAt(0).toUpperCase() }}</span>
              <span>{{ guard.name }}</span>
            </button>
          </div>

          <div v-if="selectedGuard" class="pin-panel">
            <p class="text-center text-sm text-slate-300">PIN for <strong class="text-white">{{ selectedGuard.name }}</strong></p>
            <div class="pin-dots" aria-label="PIN length">
              <span v-for="position in 6" :key="position" :class="{ filled: position <= pin.length }" />
            </div>
            <div class="pin-keypad">
              <button v-for="number in 9" :key="number" type="button" @click="pressPin(number)">{{ number }}</button>
              <button type="button" class="key-secondary" @click="pin = ''">Clear</button>
              <button type="button" @click="pressPin(0)">0</button>
              <button type="button" class="key-secondary" aria-label="Delete last digit" @click="erasePin">⌫</button>
            </div>
            <button class="primary-button mt-4 w-full" :disabled="pin.length !== 6 || unlocking" @click="unlockScanner">
              {{ unlocking ? 'Checking…' : 'Unlock Scanner' }}
            </button>
          </div>
        </template>
      </div>
    </main>

    <main v-else class="scanner-stage">
      <video ref="video" class="camera-preview" playsinline muted />
      <div class="camera-shade" />

      <div v-if="scannerState === 'scanning'" class="scan-guide-wrap">
        <div class="scan-guide"><div class="scan-line" /></div>
        <p>Hold the ID barcode horizontally inside the frame</p>
      </div>

      <div v-if="scannerState === 'stopped' || scannerState === 'starting'" class="start-panel">
        <div class="start-card">
          <h1 class="text-3xl font-bold text-white">Camera Scanner</h1>
          <p class="mt-2 text-slate-300">Camera access starts only after you press the button.</p>
          <p v-if="cameraError" class="mt-4 rounded-xl bg-red-500/15 p-3 text-sm text-red-200">{{ cameraError }}</p>
          <button class="primary-button mt-6" :disabled="scannerState === 'starting' || !online" @click="startScanner">
            {{ scannerState === 'starting' ? 'Starting Camera…' : 'Start Camera Scanner' }}
          </button>
          <Link v-if="canPairDevice" :href="route('student-attendance.devices.index')" class="mt-4 block text-sm text-indigo-300 hover:text-indigo-200">
            Manage registered iPads
          </Link>
        </div>
      </div>

      <div v-if="scannerState === 'submitting'" class="processing-panel">
        <div class="spinner" />
        <p>Checking student…</p>
      </div>

      <div v-if="scannerState === 'result' && lastScan" class="result-panel" :class="resultClass">
        <div class="result-badge">{{ badgeLabel }}</div>
        <div class="student-card">
          <img v-if="lastScan.photo_url && !photoError" :src="lastScan.photo_url" :alt="lastScan.student_name" class="student-photo" @error="photoError = true" />
          <div v-else class="student-photo student-initials">{{ initials }}</div>
          <div>
            <p class="student-name">{{ lastScan.student_name }}</p>
            <p class="student-meta">ID: {{ lastScan.barcode }}</p>
            <p v-if="lastScan.year_level" class="student-meta">Batch {{ lastScan.year_level }}</p>
            <p v-if="scanTime" class="mt-3 text-lg font-semibold text-white">{{ scanTime }}</p>
          </div>
        </div>
      </div>

      <form v-if="scannerState === 'scanning'" class="manual-entry" @submit.prevent="submitManual">
        <input v-model="manualBarcode" maxlength="50" placeholder="Manual ID entry" aria-label="Manual student ID entry" />
        <button>Submit</button>
      </form>
    </main>
  </div>
</template>

<style scoped>
.kiosk-root { min-height: 100vh; background: #020617; color: white; display: flex; flex-direction: column; overflow: hidden; }
.kiosk-header { min-height: 76px; padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; background: #0f172a; border-bottom: 1px solid rgba(255,255,255,.08); z-index: 20; }
.scanner-stage, .setup-panel { position: relative; flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; }
.setup-panel { padding: 1.5rem; background: radial-gradient(circle at top, #312e81, #020617 65%); }
.setup-card, .start-card { width: min(440px, 92vw); border: 1px solid rgba(255,255,255,.14); border-radius: 1.5rem; padding: 2rem; background: rgba(15,23,42,.92); box-shadow: 0 24px 80px rgba(0,0,0,.4); }
.guard-login-card { width: min(760px, 94vw); max-height: calc(100vh - 110px); overflow-y: auto; border: 1px solid rgba(255,255,255,.14); border-radius: 1.5rem; padding: 2rem; background: rgba(15,23,42,.94); box-shadow: 0 24px 80px rgba(0,0,0,.4); }
.guard-grid { margin-top: 1.5rem; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
.guard-button { display: flex; align-items: center; gap: .75rem; min-height: 64px; border: 2px solid #334155; border-radius: 1rem; padding: .75rem 1rem; background: #1e293b; text-align: left; font-weight: 700; transition: border-color .15s, background .15s; }
.guard-button-selected { border-color: #818cf8; background: #312e81; }
.guard-avatar { display: flex; width: 40px; height: 40px; flex: 0 0 40px; align-items: center; justify-content: center; border-radius: 999px; background: #4f46e5; font-size: 1.1rem; }
.pin-panel { width: min(340px, 100%); margin: 1.5rem auto 0; }
.pin-dots { display: flex; justify-content: center; gap: .75rem; margin: 1rem 0; }
.pin-dots span { width: 14px; height: 14px; border: 2px solid #64748b; border-radius: 999px; }
.pin-dots span.filled { border-color: #a5b4fc; background: #818cf8; }
.pin-keypad { display: grid; grid-template-columns: repeat(3, 1fr); gap: .6rem; }
.pin-keypad button { min-height: 58px; border-radius: .8rem; background: #334155; font-size: 1.35rem; font-weight: 800; }
.pin-keypad button:active { background: #4f46e5; }
.pin-keypad .key-secondary { font-size: .85rem; color: #cbd5e1; }
.setup-input { margin-top: .4rem; width: 100%; border-radius: .75rem; border: 1px solid #475569; background: #1e293b; color: white; padding: .75rem; }
.primary-button { border-radius: .75rem; background: #4f46e5; color: white; padding: .8rem 1.25rem; font-weight: 700; }
.primary-button:disabled { opacity: .5; cursor: not-allowed; }
.camera-preview { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; background: #020617; }
.camera-shade { position: absolute; inset: 0; pointer-events: none; background: linear-gradient(to bottom, rgba(2,6,23,.25), transparent 35%, transparent 65%, rgba(2,6,23,.5)); }
.start-panel, .processing-panel, .result-panel { position: absolute; inset: 0; z-index: 10; display: flex; align-items: center; justify-content: center; text-align: center; background: rgba(2,6,23,.68); backdrop-filter: blur(5px); }
.scan-guide-wrap { position: absolute; z-index: 5; display: flex; flex-direction: column; align-items: center; gap: 1rem; color: white; font-weight: 600; text-shadow: 0 2px 8px #000; }
.scan-guide { width: min(76vw, 700px); height: min(25vw, 190px); border: 4px solid rgba(255,255,255,.9); border-radius: 1.25rem; box-shadow: 0 0 0 9999px rgba(2,6,23,.32); overflow: hidden; }
.scan-line { width: 100%; height: 3px; background: #818cf8; box-shadow: 0 0 12px #6366f1; animation: scan 1.8s ease-in-out infinite; }
@keyframes scan { 0%,100% { transform: translateY(15px); } 50% { transform: translateY(160px); } }
.processing-panel { flex-direction: column; gap: 1rem; font-size: 1.25rem; font-weight: 700; }
.spinner { width: 48px; height: 48px; border: 4px solid rgba(255,255,255,.2); border-top-color: white; border-radius: 50%; animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.result-panel { flex-direction: column; gap: 1.5rem; }
.result-in { background: rgba(6,78,59,.88); }
.result-out { background: rgba(120,53,15,.9); }
.result-duplicate { background: rgba(30,41,59,.9); }
.result-error { background: rgba(127,29,29,.9); }
.result-badge { border: 3px solid currentColor; border-radius: 999px; padding: .5rem 2rem; font-size: clamp(1.4rem, 4vw, 2.5rem); font-weight: 900; letter-spacing: .12em; }
.student-card { display: flex; align-items: center; gap: 2rem; border: 1px solid rgba(255,255,255,.2); border-radius: 1.5rem; padding: 1.5rem 2rem; background: rgba(255,255,255,.1); }
.student-photo { width: clamp(150px, 25vw, 300px); height: clamp(150px, 25vw, 300px); border-radius: 50%; object-fit: cover; border: 4px solid rgba(255,255,255,.4); }
.student-initials { display: flex; align-items: center; justify-content: center; background: #312e81; font-size: clamp(3rem, 8vw, 7rem); font-weight: 800; }
.student-name { font-size: clamp(1.5rem, 4vw, 2.5rem); font-weight: 800; }
.student-meta { color: #e2e8f0; margin-top: .25rem; }
.manual-entry { position: absolute; z-index: 8; bottom: 1rem; right: 1rem; display: flex; border-radius: .75rem; overflow: hidden; box-shadow: 0 8px 30px rgba(0,0,0,.3); }
.manual-entry input { width: 180px; border: 0; padding: .65rem .8rem; color: #0f172a; }
.manual-entry button { background: #4f46e5; padding: .65rem 1rem; font-weight: 700; }
@media (max-width: 640px) {
  .kiosk-header { padding: .75rem; }
  .student-card { flex-direction: column; gap: 1rem; padding: 1rem; }
  .manual-entry { left: 1rem; right: 1rem; }
  .manual-entry input { flex: 1; width: auto; }
  .guard-login-card { padding: 1.25rem; }
  .guard-grid { grid-template-columns: 1fr; }
}
</style>
