<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  gateLocations: { type: Object, default: () => ({}) },
})

// ── State ─────────────────────────────────────────────────────────────────────

const barcodeInput    = ref(null)   // template ref for the hidden input
const barcodeBuffer   = ref('')     // accumulates chars from barcode scanner
const gateLocation    = ref(Object.keys(props.gateLocations)[0] ?? '')
const scanning        = ref(false)
const clock           = ref('')

// Last scan result for display
const lastScan = ref(null)  // { student_name, type, type_label, scan_time, is_duplicate, status }
const flashClass = ref('')  // 'flash-in' | 'flash-out' | 'flash-error' | 'flash-duplicate'

// Recent scans list (last 8) for the side panel
const recentScans = ref([])

// ── Clock ─────────────────────────────────────────────────────────────────────

let clockInterval = null

function updateClock() {
  clock.value = new Date().toLocaleTimeString('en-PH', {
    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true,
  })
}

// ── Barcode capture ───────────────────────────────────────────────────────────

function onKeyDown(e) {
  // Ignore modifier-key-only events
  if (e.ctrlKey || e.altKey || e.metaKey) return

  if (e.key === 'Enter' || e.key === 'Tab') {
    e.preventDefault()
    if (barcodeBuffer.value.trim()) {
      submitScan(barcodeBuffer.value.trim())
      barcodeBuffer.value = ''
    }
    return
  }

  // Only accumulate printable single characters
  if (e.key.length === 1) {
    barcodeBuffer.value += e.key
  }
}

function refocusInput() {
  if (barcodeInput.value) {
    barcodeInput.value.focus()
  }
}

// ── Scan submission ───────────────────────────────────────────────────────────

async function submitScan(barcode) {
  if (scanning.value) return
  scanning.value = true

  try {
    const { data } = await axios.post(route('student-attendance.scan'), {
      barcode,
      gate_location: gateLocation.value || null,
      source: 'gate_scanner',
    })

    const scanData = data.data
    const status   = data.status   // 'ok' | 'duplicate' | 'not_found'

    lastScan.value = { ...scanData, status }

    if (status === 'ok') {
      flashClass.value = scanData.type === 'in' ? 'flash-in' : 'flash-out'
    } else if (status === 'duplicate') {
      flashClass.value = 'flash-duplicate'
    } else {
      flashClass.value = 'flash-error'
    }

    if (status !== 'not_found') {
      recentScans.value = [
        { ...scanData, status, time: new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', hour12: true }) },
        ...recentScans.value,
      ].slice(0, 8)
    }

    // Clear flash after 3 seconds
    setTimeout(() => {
      flashClass.value = ''
    }, 3000)

  } catch (err) {
    if (err.response?.status === 404) {
      lastScan.value = { status: 'not_found', student_name: 'Unknown Barcode', type: null }
      flashClass.value = 'flash-error'
      setTimeout(() => { flashClass.value = '' }, 3000)
    } else {
      console.error('Scan error:', err)
    }
  } finally {
    scanning.value = false
    refocusInput()
  }
}

// ── Real-time Echo listener ───────────────────────────────────────────────────
// The attendance channel is public — used by a separate monitor screen that
// doesn't submit scans but just displays them in real-time.

let echoChannel = null

function subscribeEcho() {
  if (!window.Echo) return
  echoChannel = window.Echo.channel('attendance')
  echoChannel.listen('.student.scanned', (payload) => {
    // Update recent scans from broadcast (handles multi-gate setups)
    recentScans.value = [
      {
        ...payload,
        time: new Date(payload.scan_time).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', hour12: true }),
      },
      ...recentScans.value.filter(s => s.barcode !== payload.barcode || Math.abs(new Date(s.scan_time) - new Date(payload.scan_time)) > 5000),
    ].slice(0, 8)
  })
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
  updateClock()
  clockInterval = setInterval(updateClock, 1000)
  subscribeEcho()
  refocusInput()
  // Ensure focus is restored if the user clicks elsewhere
  document.addEventListener('click', refocusInput)
})

onUnmounted(() => {
  clearInterval(clockInterval)
  if (echoChannel) window.Echo?.leaveChannel('attendance')
  document.removeEventListener('click', refocusInput)
})

// ── Computed ──────────────────────────────────────────────────────────────────

const displayDate = computed(() =>
  new Date().toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
)

const mainBg = computed(() => {
  if (!flashClass.value) return 'bg-slate-900'
  if (flashClass.value === 'flash-in')        return 'bg-emerald-900'
  if (flashClass.value === 'flash-out')       return 'bg-amber-900'
  if (flashClass.value === 'flash-duplicate') return 'bg-slate-800'
  if (flashClass.value === 'flash-error')     return 'bg-red-900'
  return 'bg-slate-900'
})

const cardBg = computed(() => {
  if (!flashClass.value || !lastScan.value) return 'bg-slate-800 border-slate-700'
  if (flashClass.value === 'flash-in')        return 'bg-emerald-800 border-emerald-500'
  if (flashClass.value === 'flash-out')       return 'bg-amber-800 border-amber-500'
  if (flashClass.value === 'flash-duplicate') return 'bg-slate-700 border-slate-500'
  if (flashClass.value === 'flash-error')     return 'bg-red-800 border-red-500'
  return 'bg-slate-800 border-slate-700'
})

const typeColor = computed(() => {
  if (!lastScan.value) return 'text-slate-300'
  if (lastScan.value.status === 'not_found') return 'text-red-400'
  if (lastScan.value.status === 'duplicate') return 'text-slate-300'
  return lastScan.value.type === 'in' ? 'text-emerald-300' : 'text-amber-300'
})

const typeLabel = computed(() => {
  if (!lastScan.value) return ''
  if (lastScan.value.status === 'not_found')   return 'NOT FOUND'
  if (lastScan.value.status === 'duplicate')   return 'ALREADY SCANNED'
  return lastScan.value.type === 'in' ? 'TIME IN' : 'TIME OUT'
})

const scanTimeDisplay = computed(() => {
  if (!lastScan.value?.scan_time) return ''
  return new Date(lastScan.value.scan_time).toLocaleTimeString('en-PH', {
    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true,
  })
})
</script>

<template>
  <Head title="Attendance Kiosk" />

  <!-- Full-screen kiosk — no admin layout -->
  <div
    class="min-h-screen flex transition-colors duration-500 select-none"
    :class="mainBg"
    @keydown="onKeyDown"
    tabindex="-1"
  >

    <!-- Hidden barcode capture input — always focused -->
    <input
      ref="barcodeInput"
      v-model="barcodeBuffer"
      @keydown="onKeyDown"
      class="absolute opacity-0 w-0 h-0 pointer-events-none"
      autocomplete="off"
      tabindex="-1"
    />

    <!-- ── Left panel: main scan display ──────────────────────────────────── -->
    <div class="flex-1 flex flex-col items-center justify-between p-8">

      <!-- Header -->
      <div class="w-full flex items-center justify-between">
        <div class="flex items-center gap-3">
          <img src="/images/pshslogo.png" alt="PSHS" class="h-10 w-10 object-contain" />
          <div>
            <p class="text-white font-bold text-lg leading-tight">PSHS – Caraga Campus</p>
            <p class="text-slate-400 text-sm">Student Gate Attendance</p>
          </div>
        </div>

        <!-- Gate location selector -->
        <div class="flex items-center gap-2">
          <label class="text-slate-400 text-sm">Gate:</label>
          <select
            v-model="gateLocation"
            class="bg-slate-700 text-white text-sm rounded-lg border border-slate-600 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            @click.stop
          >
            <option v-for="(label, key) in gateLocations" :key="key" :value="key">{{ label }}</option>
            <option value="">— No gate —</option>
          </select>
        </div>
      </div>

      <!-- Clock -->
      <div class="text-center">
        <p class="text-6xl font-mono font-bold text-white tabular-nums tracking-wider">{{ clock }}</p>
        <p class="text-slate-400 mt-1 text-sm">{{ displayDate }}</p>
      </div>

      <!-- Scan result card -->
      <div
        class="w-full max-w-lg rounded-2xl border-2 transition-all duration-300 p-8 text-center"
        :class="cardBg"
      >
        <template v-if="!lastScan">
          <p class="text-slate-400 text-xl mb-2">Ready to scan</p>
          <p class="text-slate-500 text-4xl font-bold tracking-widest">— — —</p>
          <p class="text-slate-600 text-sm mt-4">Point barcode scanner at this terminal</p>
        </template>

        <template v-else>
          <!-- Type badge -->
          <p
            class="text-3xl font-black tracking-widest mb-4 transition-all duration-300"
            :class="typeColor"
          >
            {{ typeLabel }}
          </p>

          <!-- Student name -->
          <p class="text-white text-2xl font-bold leading-tight">
            {{ lastScan.student_name ?? 'Unknown' }}
          </p>

          <!-- Scan time -->
          <p class="text-slate-400 text-sm mt-3">
            {{ scanTimeDisplay }}
            <span v-if="lastScan.status === 'duplicate'" class="text-slate-500 ml-1">(duplicate scan ignored)</span>
          </p>
        </template>
      </div>

      <!-- Scan prompt -->
      <div class="text-center">
        <div v-if="scanning" class="flex items-center gap-2 text-slate-400">
          <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
          </svg>
          <span class="text-sm">Processing…</span>
        </div>
        <p v-else class="text-slate-500 text-sm animate-pulse">Scan your ID card</p>
      </div>
    </div>

    <!-- ── Right panel: recent scans ──────────────────────────────────────── -->
    <div class="w-72 bg-slate-950 border-l border-slate-800 flex flex-col p-4">
      <p class="text-slate-500 text-xs font-semibold uppercase tracking-wide mb-3">Recent Scans</p>

      <div v-if="recentScans.length === 0" class="text-slate-700 text-sm text-center mt-8">
        No scans yet today
      </div>

      <div class="flex flex-col gap-2 overflow-y-auto flex-1">
        <div
          v-for="(scan, i) in recentScans"
          :key="i"
          class="rounded-lg px-3 py-2 flex items-center justify-between text-sm"
          :class="scan.type === 'in' ? 'bg-emerald-900/40 border border-emerald-800/50' : 'bg-amber-900/40 border border-amber-800/50'"
        >
          <div class="min-w-0">
            <p class="text-white font-medium truncate text-xs leading-tight">{{ scan.student_name }}</p>
            <p class="text-xs mt-0.5" :class="scan.type === 'in' ? 'text-emerald-400' : 'text-amber-400'">
              {{ scan.type === 'in' ? 'IN' : 'OUT' }}
            </p>
          </div>
          <p class="text-slate-500 text-xs ml-2 shrink-0">{{ scan.time }}</p>
        </div>
      </div>
    </div>

  </div>
</template>
