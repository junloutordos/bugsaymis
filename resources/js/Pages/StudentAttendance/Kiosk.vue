<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  gateLocations: { type: Object, default: () => ({}) },
})

// ── State ─────────────────────────────────────────────────────────────────────

const barcodeInput  = ref(null)
const barcodeBuffer = ref('')
const gateLocation  = ref(Object.keys(props.gateLocations)[0] ?? '')
const scanning      = ref(false)
const clock         = ref('')
const lastScan      = ref(null)   // null = idle; object = scan result
const scanStatus    = ref('')     // 'ok' | 'duplicate' | 'error'

let resetTimer    = null
let clockInterval = null
let scanIdleTimer = null   // fires if no new char arrives within 100 ms
let cooldownTimer = null   // absorbs scanner double-fire
const scanCooldown = ref(false)

// ── Clock ─────────────────────────────────────────────────────────────────────

function updateClock() {
  clock.value = new Date().toLocaleTimeString('en-PH', {
    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true,
  })
}

// ── Barcode capture ───────────────────────────────────────────────────────────

function flushBuffer() {
  clearTimeout(scanIdleTimer)
  scanIdleTimer = null
  const val = barcodeBuffer.value.trim()
  barcodeBuffer.value = ''
  if (barcodeInput.value) barcodeInput.value.value = ''
  if (val) submitScan(val)
}

function onKeyDown(e) {
if (e.ctrlKey || e.altKey || e.metaKey) return

  if (e.key === 'Enter' || e.key === 'Tab' || e.key === 'Return') {
    e.preventDefault()
    flushBuffer()
    return
  }

  if (e.key.length === 1) {
    barcodeBuffer.value += e.key
    // Auto-submit if scanner doesn't send a terminator — 100 ms idle = scan complete
    clearTimeout(scanIdleTimer)
    scanIdleTimer = setTimeout(flushBuffer, 100)
  }
}

function refocusInput() {
  barcodeInput.value?.focus()
}

// Fallback: capture via the input's native value in case keydown misses rapid chars
function onBarcodeNativeInput(e) {
  const raw = e.target.value
  if (!raw) return
  // If scanner appended a newline/CR directly into the input value
  const trimmed = raw.replace(/[\r\n\t]+$/, '')
  const hadTerminator = raw !== trimmed
  barcodeBuffer.value = trimmed
  e.target.value = trimmed
  if (hadTerminator && trimmed) {
    clearTimeout(scanIdleTimer)
    barcodeBuffer.value = ''
    e.target.value = ''
    submitScan(trimmed)
  }
}

// ── Scan ──────────────────────────────────────────────────────────────────────

async function submitScan(barcode) {
  if (scanning.value || scanCooldown.value) return
  scanning.value = true
  scanCooldown.value = true
  clearTimeout(resetTimer)

  try {
    const { data } = await axios.post(route('student-attendance.scan'), {
      barcode,
      gate_location: gateLocation.value || null,
      source: 'gate_scanner',
    })

    lastScan.value  = data.data
    scanStatus.value = data.status

  } catch (err) {
    if (err.response?.status === 404) {
      lastScan.value   = { student_name: 'Unknown ID', barcode, type: null }
      scanStatus.value = 'error'
    } else {
      // Network error, 419 session expiry, 500, etc.
      lastScan.value   = { student_name: 'Scan Failed', barcode, type: null }
      scanStatus.value = 'error'
    }
  } finally {
    scanning.value = false
    // Hold cooldown for 2 s to absorb scanner double-fire
    clearTimeout(cooldownTimer)
    cooldownTimer = setTimeout(() => { scanCooldown.value = false }, 2000)
    refocusInput()
    // Auto-reset to idle after 4 seconds
    resetTimer = setTimeout(() => {
      lastScan.value   = null
      scanStatus.value = ''
    }, 4000)
  }
}

// ── Real-time Echo listener ───────────────────────────────────────────────────

let echoChannel = null

function subscribeEcho() {
  if (!window.Echo) return
  echoChannel = window.Echo.channel('attendance')
  echoChannel.listen('.student.scanned', (payload) => {
    // Only show if this kiosk didn't originate the scan (avoid double-flash)
    if (scanning.value) return
    clearTimeout(resetTimer)
    lastScan.value   = payload
    scanStatus.value = payload.is_duplicate ? 'duplicate' : 'ok'
    resetTimer = setTimeout(() => {
      lastScan.value   = null
      scanStatus.value = ''
    }, 4000)
  })
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
  updateClock()
  clockInterval = setInterval(updateClock, 1000)
  subscribeEcho()
  refocusInput()
  // Use document-level listener so scanner input is captured regardless of focus
  document.addEventListener('keydown', onKeyDown)
  document.addEventListener('click', refocusInput)
})

onUnmounted(() => {
  clearInterval(clockInterval)
  clearTimeout(resetTimer)
  clearTimeout(scanIdleTimer)
  clearTimeout(cooldownTimer)
  if (echoChannel) window.Echo?.leaveChannel('attendance')
  document.removeEventListener('keydown', onKeyDown)
  document.removeEventListener('click', refocusInput)
})

// ── Computed helpers ──────────────────────────────────────────────────────────

const displayDate = computed(() =>
  new Date().toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
)

// Overlay color tint injected over the background on scan
const overlayColor = computed(() => {
  if (scanStatus.value === 'ok' && lastScan.value?.type === 'in')  return 'rgba(6,  95, 70,  0.55)'
  if (scanStatus.value === 'ok' && lastScan.value?.type === 'out') return 'rgba(120,53, 15,  0.55)'
  if (scanStatus.value === 'duplicate')                             return 'rgba(15, 23, 42,  0.55)'
  if (scanStatus.value === 'error')                                 return 'rgba(127,29, 29,  0.55)'
  return 'rgba(15, 23, 42, 0.0)'
})

const badgeClass = computed(() => {
  if (scanStatus.value === 'ok' && lastScan.value?.type === 'in')  return 'badge-in'
  if (scanStatus.value === 'ok' && lastScan.value?.type === 'out') return 'badge-out'
  if (scanStatus.value === 'duplicate')                             return 'badge-duplicate'
  return 'badge-error'
})

const badgeLabel = computed(() => {
  if (!lastScan.value) return ''
  if (scanStatus.value === 'error')     return 'ID NOT FOUND'
  if (scanStatus.value === 'duplicate') return 'ALREADY SCANNED'
  return lastScan.value.type === 'in' ? 'TIME IN' : 'TIME OUT'
})

const scanTimeDisplay = computed(() => {
  if (!lastScan.value?.scan_time) return ''
  return new Date(lastScan.value.scan_time).toLocaleTimeString('en-PH', {
    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true,
  })
})

const initials = computed(() => {
  if (!lastScan.value?.student_name) return '?'
  return lastScan.value.student_name
    .split(/[\s,]+/)
    .filter(Boolean)
    .map(w => w[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
})

const photoError = ref(false)

// Reset photoError whenever a new scan comes in
watch(lastScan, () => { photoError.value = false })
</script>

<template>
  <Head title="Attendance Kiosk" />

  <div class="kiosk-root" tabindex="-1">

    <!-- Barcode capture input — off-screen but focusable, catches scanner input via both events -->
    <input
      ref="barcodeInput"
      class="barcode-capture"
      autocomplete="off"
      autofocus
      @keydown="onKeyDown"
      @input="onBarcodeNativeInput"
      @blur="refocusInput"
    />

    <!-- Background photo -->
    <div class="kiosk-bg" />

    <!-- Static dark overlay -->
    <div class="kiosk-overlay-static" />

    <!-- Dynamic color tint on scan (transitions in/out) -->
    <div
      class="kiosk-overlay-tint"
      :style="{ backgroundColor: overlayColor }"
    />

    <!-- Content layer -->
    <div class="kiosk-content">

      <!-- ── Header ──────────────────────────────────────────────────────── -->
      <header class="kiosk-header">
        <div class="flex items-center gap-3">
          <img src="/images/pshslogo.png" alt="PSHS" class="h-12 w-12 object-contain drop-shadow-lg" />
          <div>
            <p class="text-white font-bold text-base leading-tight">PSHS – Caraga Region Campus in Butuan City</p>
            <p class="text-slate-400 text-xs tracking-wide">Student Gate Attendance System</p>
          </div>
        </div>

        <!-- Gate selector -->
        <div class="flex items-center gap-2">
          <span class="text-slate-400 text-sm">Gate:</span>
          <select
            v-model="gateLocation"
            class="bg-white/10 backdrop-blur text-white text-sm rounded-lg border border-white/20 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-400"
            @click.stop
          >
            <option v-for="(label, key) in gateLocations" :key="key" :value="key">{{ label }}</option>
            <option value="">— No gate —</option>
          </select>
        </div>
      </header>

      <!-- ── Main center area ────────────────────────────────────────────── -->
      <main class="kiosk-main">

        <!-- IDLE STATE -->
        <transition name="fade">
          <div v-if="!lastScan" key="idle" class="idle-state">
            <div class="clock-block">
              <p class="clock-time">{{ clock }}</p>
              <p class="clock-date">{{ displayDate }}</p>
            </div>
            <div class="scan-prompt">
              <div v-if="scanning" class="flex items-center gap-2 text-slate-300">
                <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <span>Processing…</span>
              </div>
              <template v-else>
                <div class="scan-icon-ring">
                  <svg class="h-10 w-10 text-white/60" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h3m0 0V3m0 1.5v3M3.75 19.5h3m0 0V21m0-1.5v-3M16.5 4.5h3m0 0V3m0 1.5v3M16.5 19.5h3m0 0V21m0-1.5v-3M4.5 9h15M4.5 15h15" />
                  </svg>
                </div>
                <p class="text-white/70 text-lg mt-4 animate-pulse">Scan your ID card</p>
              </template>
            </div>
          </div>

          <!-- SCAN RESULT STATE -->
          <div v-else key="result" class="result-state">

            <!-- Status badge -->
            <div :class="['scan-badge', badgeClass]">{{ badgeLabel }}</div>

            <!-- Student card -->
            <div class="student-card">
              <!-- Photo / Initials -->
              <div class="student-photo-wrap">
                <img
                  v-if="lastScan.photo_url && !photoError"
                  :src="lastScan.photo_url"
                  :alt="lastScan.student_name"
                  class="student-photo"
                  @error="photoError = true"
                />
                <div v-else class="student-initials">{{ initials }}</div>
              </div>

              <!-- Info -->
              <div class="student-info">
                <p class="student-name">{{ lastScan.student_name }}</p>
                <p v-if="lastScan.barcode" class="student-meta">ID: {{ lastScan.barcode }}</p>
                <p v-if="lastScan.year_level" class="student-meta">Batch {{ lastScan.year_level }}</p>
                <p v-if="scanStatus === 'duplicate'" class="student-meta text-slate-400 mt-1 italic">Duplicate scan — ignored</p>
              </div>
            </div>

            <!-- Scan time -->
            <p v-if="scanTimeDisplay" class="scan-time-display">
              <svg class="inline h-4 w-4 mr-1 opacity-60" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
              </svg>
              {{ scanTimeDisplay }}
              <span v-if="gateLocation" class="ml-2 opacity-60">· {{ gateLocations[gateLocation] ?? gateLocation }}</span>
            </p>

            <!-- Reset progress bar -->
            <div class="reset-bar"><div class="reset-bar-fill" /></div>
          </div>
        </transition>

      </main>

      <!-- ── Footer ──────────────────────────────────────────────────────── -->
      <footer class="kiosk-footer">
        <p class="text-slate-500 text-xs">© 2026 PSHS-CRC · BugsayMIS</p>
<p class="text-slate-600 text-xs">{{ clock }}</p>
      </footer>

    </div>
  </div>
</template>

<style scoped>
/* ── Barcode capture input — off-screen, always focusable ── */
.barcode-capture {
  position: fixed;
  top: -999px;
  left: -999px;
  width: 1px;
  height: 1px;
  opacity: 0;
  border: none;
  outline: none;
  background: transparent;
  color: transparent;
  caret-color: transparent;
}

/* ── Root ── */
.kiosk-root {
  position: relative;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background-color: #0f172a;
  outline: none;
}

/* ── Background layers ── */
.kiosk-bg {
  position: absolute;
  inset: 0;
  background-image: url('/storage/bg.jpg');
  background-size: cover;
  background-position: center;
  opacity: 1;
  mix-blend-mode: normal;
  z-index: 0;
}

.kiosk-overlay-static {
  position: absolute;
  inset: 0;
  background: linear-gradient(160deg, rgba(15,23,42,0.75) 0%, rgba(15,23,42,0.80) 50%, rgba(30,27,75,0.88) 100%);
  z-index: 1;
}

.kiosk-overlay-tint {
  position: absolute;
  inset: 0;
  z-index: 2;
  transition: background-color 0.5s ease;
}

/* ── Content ── */
.kiosk-content {
  position: relative;
  z-index: 10;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  padding: 1.5rem 2rem;
}

/* ── Header ── */
.kiosk-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}

/* ── Main ── */
.kiosk-main {
  flex: 1;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* ── Idle ── */
.idle-state {
  position: absolute;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3rem;
}

.clock-block {
  text-align: center;
}

.clock-time {
  font-size: clamp(3.5rem, 8vw, 7rem);
  font-family: ui-monospace, monospace;
  font-weight: 700;
  color: #ffffff;
  letter-spacing: 0.05em;
  line-height: 1;
}

.clock-date {
  margin-top: 0.5rem;
  font-size: 1rem;
  color: #94a3b8;
}

.scan-prompt {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.scan-icon-ring {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  border: 2px solid rgba(255,255,255,0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255,255,255,0.05);
}

/* ── Result ── */
.result-state {
  position: absolute;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.5rem;
  animation: popIn 0.35s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

.scan-badge {
  font-size: clamp(1.5rem, 4vw, 2.5rem);
  font-weight: 900;
  letter-spacing: 0.15em;
  padding: 0.5rem 2rem;
  border-radius: 9999px;
  border: 2px solid;
}

.badge-in {
  color: #6ee7b7;
  border-color: #6ee7b7;
  background: rgba(6, 95, 70, 0.3);
}

.badge-out {
  color: #fcd34d;
  border-color: #fcd34d;
  background: rgba(120, 53, 15, 0.3);
}

.badge-duplicate {
  color: #94a3b8;
  border-color: #94a3b8;
  background: rgba(30, 41, 59, 0.5);
}

.badge-error {
  color: #fca5a5;
  border-color: #fca5a5;
  background: rgba(127, 29, 29, 0.4);
}

/* ── Student card ── */
.student-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.25rem;
  background: rgba(255,255,255,0.07);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 1.5rem;
  padding: 2rem 3rem;
  min-width: 320px;
  max-width: 480px;
  text-align: center;
}

.student-photo-wrap {
  width: 400px;
  height: 400px;
  border-radius: 50%;
  overflow: hidden;
  border: 4px solid rgba(255,255,255,0.25);
  background: rgba(99,102,241,0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.student-photo {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.student-initials {
  font-size: 8rem;
  font-weight: 700;
  color: #c7d2fe;
  letter-spacing: 0.05em;
}

.student-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.student-name {
  font-size: clamp(1.4rem, 3vw, 2rem);
  font-weight: 700;
  color: #ffffff;
  line-height: 1.2;
}

.student-meta {
  font-size: 0.875rem;
  color: #94a3b8;
}

/* ── Scan time ── */
.scan-time-display {
  font-size: 1rem;
  color: #cbd5e1;
  display: flex;
  align-items: center;
}

/* ── Reset progress bar ── */
.reset-bar {
  width: 200px;
  height: 3px;
  background: rgba(255,255,255,0.1);
  border-radius: 9999px;
  overflow: hidden;
}

.reset-bar-fill {
  height: 100%;
  background: rgba(255,255,255,0.35);
  border-radius: 9999px;
  animation: drain 4s linear forwards;
}

@keyframes drain {
  from { width: 100%; }
  to   { width: 0%; }
}

/* ── Footer ── */
.kiosk-footer {
  display: flex;
  justify-content: space-between;
  padding-top: 1rem;
  border-top: 1px solid rgba(255,255,255,0.06);
}

/* ── Transitions ── */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}
.fade-enter-from {
  opacity: 0;
  transform: scale(0.96);
}
.fade-leave-to {
  opacity: 0;
  transform: scale(1.02);
}

@keyframes popIn {
  from { opacity: 0; transform: scale(0.88); }
  to   { opacity: 1; transform: scale(1); }
}
</style>
