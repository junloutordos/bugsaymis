<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  device: { type: Object, required: true },
})

const online = ref(navigator.onLine)
const clock = ref('')
const state = ref('ready')
const result = ref(null)
const status = ref('')
const photoError = ref(false)

let barcodeBuffer = ''
let bufferTimer = null
let resultTimer = null
let clockTimer = null
let audioContext = null

function updateClock() {
  clock.value = new Date().toLocaleTimeString('en-PH', {
    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true,
  })
}

function handleKeydown(event) {
  if (!online.value || state.value !== 'ready' || event.ctrlKey || event.metaKey || event.altKey) return

  if (event.key === 'Enter' || event.key === 'Tab') {
    event.preventDefault()
    flushBuffer()
    return
  }

  if (event.key.length !== 1) return
  barcodeBuffer += event.key
  clearTimeout(bufferTimer)
  bufferTimer = setTimeout(flushBuffer, 100)
}

function flushBuffer() {
  clearTimeout(bufferTimer)
  const barcode = barcodeBuffer.trim()
  barcodeBuffer = ''
  if (barcode) submit(barcode)
}

async function submit(barcode) {
  if (state.value === 'submitting') return
  clearTimeout(resultTimer)
  state.value = 'submitting'

  try {
    const { data } = await axios.post(route('student-attendance.self-scan'), {
      barcode,
      scan_uuid: crypto.randomUUID(),
      device_scan_time: new Date().toISOString(),
    })
    result.value = data.data
    status.value = data.status
    state.value = 'result'
    playTone(data.status, data.data?.type)
  } catch (error) {
    result.value = {
      student_name: error.response?.status === 404 ? 'Unknown ID' : 'Attendance Not Recorded',
      barcode,
      type: null,
    }
    status.value = error.response?.status === 403 ? 'revoked' : 'error'
    state.value = 'result'
    playTone('error')
  }

  resultTimer = setTimeout(reset, 2500)
}

function reset() {
  result.value = null
  status.value = ''
  state.value = online.value ? 'ready' : 'offline'
}

function handleOffline() {
  online.value = false
  state.value = 'offline'
}

function handleOnline() {
  online.value = true
  reset()
}

function playTone(scanStatus, type = null) {
  const AudioContext = window.AudioContext || window.webkitAudioContext
  if (!AudioContext) return
  audioContext ??= new AudioContext()
  const oscillator = audioContext.createOscillator()
  const gain = audioContext.createGain()
  oscillator.frequency.value = scanStatus === 'error' ? 190 : scanStatus === 'duplicate' ? 330 : type === 'out' ? 520 : 760
  gain.gain.setValueAtTime(0.12, audioContext.currentTime)
  gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.18)
  oscillator.connect(gain).connect(audioContext.destination)
  oscillator.start()
  oscillator.stop(audioContext.currentTime + 0.2)
}

const displayDate = computed(() => new Date().toLocaleDateString('en-PH', {
  weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
}))

const badge = computed(() => {
  if (status.value === 'error') return 'NOT RECORDED'
  if (status.value === 'revoked') return 'DEVICE ACCESS REVOKED'
  if (status.value === 'duplicate') return 'ALREADY SCANNED'
  return result.value?.type === 'in' ? 'TIME IN' : 'TIME OUT'
})

const resultClass = computed(() => {
  if (status.value === 'error' || status.value === 'revoked') return 'result-error'
  if (status.value === 'duplicate') return 'result-duplicate'
  return result.value?.type === 'in' ? 'result-in' : 'result-out'
})

const initials = computed(() => (result.value?.student_name ?? '?')
  .split(/[\s,]+/).filter(Boolean).map(word => word[0]).slice(0, 2).join('').toUpperCase())

const scanTime = computed(() => result.value?.scan_time
  ? new Date(result.value.scan_time).toLocaleTimeString('en-PH', {
      hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true,
    })
  : '')

watch(result, () => { photoError.value = false })

onMounted(() => {
  updateClock()
  clockTimer = setInterval(updateClock, 1000)
  window.addEventListener('keydown', handleKeydown)
  window.addEventListener('offline', handleOffline)
  window.addEventListener('online', handleOnline)
})

onUnmounted(() => {
  clearTimeout(bufferTimer)
  clearTimeout(resultTimer)
  clearInterval(clockTimer)
  window.removeEventListener('keydown', handleKeydown)
  window.removeEventListener('offline', handleOffline)
  window.removeEventListener('online', handleOnline)
  audioContext?.close()
})
</script>

<template>
  <div class="self-scan-root">
    <header class="self-scan-header">
      <div class="flex items-center gap-3">
        <img src="/images/pshslogo.png" alt="PSHS" class="h-12 w-12 object-contain" />
        <div>
          <p class="text-lg font-bold text-white">PSHS–CRC Student Gate Attendance</p>
          <p class="text-sm text-slate-400">{{ displayDate }} · {{ clock }}</p>
        </div>
      </div>
      <div class="text-right">
        <p class="font-semibold text-white">{{ device.gate_label }}</p>
        <p class="text-sm" :class="online ? 'text-emerald-400' : 'text-red-400'">
          {{ online ? '● Online' : '● Offline' }} · {{ device.name }}
        </p>
      </div>
    </header>

    <main class="self-scan-stage">
      <section v-if="state === 'ready'" class="ready-panel">
        <div class="id-icon">▥</div>
        <h1>Scan Your Student ID</h1>
        <p>Hold your ID barcode under the gate scanner.</p>
        <div class="ready-pulse" />
      </section>

      <section v-else-if="state === 'submitting'" class="processing-panel">
        <div class="spinner" />
        <p>Checking attendance…</p>
      </section>

      <section v-else-if="state === 'offline'" class="offline-panel">
        <h1>Scanner Offline</h1>
        <p>Attendance cannot be recorded. Please notify the security guard.</p>
      </section>

      <section v-else-if="result" class="result-panel" :class="resultClass">
        <div class="result-badge">{{ badge }}</div>
        <div class="student-card">
          <img v-if="result.photo_url && !photoError" :src="result.photo_url" :alt="result.student_name" class="student-photo" @error="photoError = true" />
          <div v-else class="student-photo student-initials">{{ initials }}</div>
          <div>
            <p class="student-name">{{ result.student_name }}</p>
            <p class="student-meta">ID: {{ result.barcode }}</p>
            <p v-if="result.year_level" class="student-meta">Batch {{ result.year_level }}</p>
            <p v-if="scanTime" class="scan-time">{{ scanTime }}</p>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
.self-scan-root { min-height: 100vh; background: radial-gradient(circle at top, #1e1b4b, #020617 68%); color: white; display: flex; flex-direction: column; overflow: hidden; }
.self-scan-header { min-height: 82px; padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; background: #0f172a; border-bottom: 1px solid rgba(255,255,255,.08); }
.self-scan-stage { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; }
.ready-panel, .processing-panel, .offline-panel { text-align: center; }
.ready-panel h1, .offline-panel h1 { font-size: clamp(2rem, 5vw, 4rem); line-height: 1; font-weight: 900; }
.ready-panel p, .offline-panel p { margin-top: 1.25rem; color: #cbd5e1; font-size: clamp(1rem, 2vw, 1.5rem); }
.id-icon { margin: 0 auto 2rem; width: 130px; height: 96px; display: flex; align-items: center; justify-content: center; border: 5px solid #818cf8; border-radius: 1.25rem; color: #a5b4fc; font-size: 4rem; }
.ready-pulse { width: 18px; height: 18px; margin: 2rem auto 0; border-radius: 999px; background: #34d399; box-shadow: 0 0 0 0 rgba(52,211,153,.6); animation: pulse 1.4s infinite; }
.processing-panel { font-size: 1.5rem; font-weight: 700; }
.spinner { width: 64px; height: 64px; margin: 0 auto 1.5rem; border: 6px solid #334155; border-top-color: #818cf8; border-radius: 999px; animation: spin .75s linear infinite; }
.offline-panel { max-width: 680px; border: 1px solid rgba(248,113,113,.35); border-radius: 1.5rem; padding: 3rem; background: rgba(127,29,29,.3); }
.result-panel { width: min(850px, 94vw); border-radius: 2rem; padding: 2.5rem; box-shadow: 0 30px 100px rgba(0,0,0,.45); }
.result-in { background: linear-gradient(135deg, #065f46, #064e3b); }
.result-out { background: linear-gradient(135deg, #1d4ed8, #1e3a8a); }
.result-duplicate { background: linear-gradient(135deg, #92400e, #78350f); }
.result-error { background: linear-gradient(135deg, #991b1b, #7f1d1d); }
.result-badge { text-align: center; font-size: clamp(2rem, 5vw, 4rem); font-weight: 950; letter-spacing: .06em; }
.student-card { margin-top: 2rem; display: flex; align-items: center; justify-content: center; gap: 2rem; }
.student-photo { width: 150px; height: 150px; flex: 0 0 150px; border: 5px solid rgba(255,255,255,.7); border-radius: 1.5rem; object-fit: cover; background: #1e293b; }
.student-initials { display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 900; }
.student-name { font-size: clamp(1.5rem, 4vw, 3rem); font-weight: 900; }
.student-meta { margin-top: .35rem; color: rgba(255,255,255,.8); font-size: 1.15rem; }
.scan-time { margin-top: 1rem; font-size: 1.35rem; font-weight: 800; }
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes pulse { 70% { box-shadow: 0 0 0 20px rgba(52,211,153,0); } 100% { box-shadow: 0 0 0 0 rgba(52,211,153,0); } }
@media (max-width: 640px) { .self-scan-header { align-items: flex-start; } .student-card { flex-direction: column; text-align: center; } .result-panel { padding: 1.5rem; } }
</style>
