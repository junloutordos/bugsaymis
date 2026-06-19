<template>
  <Head title="Online Time Punches" />
  <AdminLayout title="Online Time Punches">
    <div class="max-w-2xl mx-auto space-y-6">

      <!-- Not enrolled -->
      <div v-if="!enrollmentStatus" class="bg-white rounded-xl border border-slate-100 shadow-sm p-8 text-center">
        <p class="text-slate-600 mb-4">You haven't enrolled your face yet. Enrollment is required before you can use Online Time Punches.</p>
        <Link :href="route('hr.face-enrollment.self')"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          Enroll My Face
        </Link>
      </div>

      <!-- Pending approval -->
      <div v-else-if="enrollmentStatus === 'pending'" class="bg-amber-50 border border-amber-200 rounded-xl p-8 text-center">
        <p class="text-amber-700 font-medium">Your face enrollment is awaiting HR approval.</p>
        <p class="text-sm text-amber-600 mt-1">You'll be able to punch in once it's approved.</p>
      </div>

      <!-- Rejected -->
      <div v-else-if="enrollmentStatus === 'rejected'" class="bg-rose-50 border border-rose-200 rounded-xl p-8 text-center">
        <p class="text-rose-700 font-medium">Your face enrollment was rejected.</p>
        <Link :href="route('hr.face-enrollment.self')"
              class="inline-flex items-center gap-2 mt-3 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          Re-enroll
        </Link>
      </div>

      <!-- Approved: punch dashboard -->
      <template v-else>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
          <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Today's Punches</h2>
            <span class="text-sm text-slate-400">{{ today }}</span>
          </div>
          <div class="p-5 grid grid-cols-2 gap-3">
            <div v-for="slot in punchSlots" :key="slot.type"
                 class="rounded-xl p-3 flex flex-col items-center gap-2 border-2"
                 :class="slotClass(slot.type)">
              <span class="text-xs font-semibold uppercase tracking-wide" :class="slotLabelClass(slot.type)">{{ slot.label }}</span>
              <span class="text-sm font-bold" :class="slotLabelClass(slot.type)">
                {{ punchFor(slot.type) ? fmtTime(punchFor(slot.type).punched_at) : '—' }}
              </span>
              <span v-if="punchFor(slot.type)" class="text-[10px]" :class="statusTextClass(punchFor(slot.type).match_status)">
                {{ statusLabel(punchFor(slot.type).match_status) }}
              </span>
              <button v-if="canPunch(slot.type)" @click="startPunch(slot.type)"
                      class="mt-1 w-full inline-flex items-center justify-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                Punch
              </button>
            </div>
          </div>
        </div>
      </template>

      <!-- Liveness Modal -->
      <div v-if="showLiveness" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-4 space-y-3">
          <h3 class="text-base font-semibold text-slate-800 text-center">
            {{ activeSlotLabel }} — Face Verification
          </h3>
          <div ref="livenessContainer" style="min-height: 400px;"></div>
          <button @click="cancelLiveness" class="text-sm text-slate-400 hover:text-slate-600 w-full text-center">
            Cancel
          </button>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'
import axios from 'axios'

// Lazy-loaded: the React + AWS Amplify Face Liveness bundle is large and
// only needed once the user actually starts a punch, not on page load.

const props = defineProps({
  today:                  { type: String, required: true },
  enrollmentStatus:       { type: String, default: null },
  todayPunches:           { type: Array, default: () => [] },
  awsRegion:              { type: String, default: null },
  cognitoIdentityPoolId:  { type: String, default: null },
})

const punches = ref(props.todayPunches)

const punchSlots = [
  { type: 'time_in_am',  label: 'Time In AM' },
  { type: 'time_out_am', label: 'Time Out AM' },
  { type: 'time_in_pm',  label: 'Time In PM' },
  { type: 'time_out_pm', label: 'Time Out PM' },
]

function punchFor(type) {
  return punches.value.find(p => p.punch_type === type) || null
}

function canPunch(type) {
  if (punchFor(type)) return false
  const idx = punchSlots.findIndex(s => s.type === type)
  if (idx === 0) return true
  const prev = punchSlots[idx - 1]
  return punchFor(prev.type)?.match_status === 'verified'
}

function slotClass(type) {
  const p = punchFor(type)
  if (!p) return 'bg-slate-50 border-dashed border-slate-200'
  if (p.match_status === 'verified') return 'bg-emerald-50 border-emerald-300'
  if (p.match_status === 'manual_review') return 'bg-amber-50 border-amber-300'
  return 'bg-rose-50 border-rose-300'
}

function slotLabelClass(type) {
  const p = punchFor(type)
  if (!p) return 'text-slate-400'
  if (p.match_status === 'verified') return 'text-emerald-700'
  if (p.match_status === 'manual_review') return 'text-amber-700'
  return 'text-rose-700'
}

function statusTextClass(status) {
  if (status === 'verified') return 'text-emerald-500'
  if (status === 'manual_review') return 'text-amber-500'
  return 'text-rose-500'
}

function statusLabel(status) {
  if (status === 'verified') return 'Verified'
  if (status === 'manual_review') return 'Under review'
  return 'Rejected — please retry'
}

function fmtTime(val) {
  if (!val) return '—'
  const d = new Date(String(val).replace(' ', 'T'))
  if (isNaN(d.getTime())) return '—'
  return d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })
}

// ── Liveness flow ────────────────────────────────────────────────────────────
const showLiveness     = ref(false)
const activeSlotType   = ref(null)
const livenessContainer = ref(null)
let unmountLiveness = null

const activeSlotLabel = computed(() => punchSlots.find(s => s.type === activeSlotType.value)?.label ?? '')

async function startPunch(type) {
  activeSlotType.value = type
  showLiveness.value = true

  try {
    const { data } = await axios.post(route('hr.online-punch.liveness-session'))
    const sessionId = data.session_id

    await new Promise(resolve => setTimeout(resolve, 0)) // wait for v-if DOM mount

    const { mountLivenessCheck } = await import('./Liveness/mountLiveness.js')

    unmountLiveness = mountLivenessCheck(livenessContainer.value, {
      region: props.awsRegion,
      identityPoolId: props.cognitoIdentityPoolId,
      sessionId,
      onAnalysisComplete: () => submitPunch(type, sessionId),
      onError: (err) => {
        Swal.fire('Camera Error', err?.message || 'Could not start face verification.', 'error')
        cancelLiveness()
      },
      onUserCancel: cancelLiveness,
    })
  } catch (err) {
    Swal.fire('Error', 'Could not start the liveness check. Please try again.', 'error')
    showLiveness.value = false
  }
}

async function submitPunch(type, sessionId) {
  let latitude = null, longitude = null
  try {
    const pos = await getPosition()
    latitude = pos.coords.latitude
    longitude = pos.coords.longitude
  } catch { /* geolocation optional */ }

  try {
    const { data } = await axios.post(route('hr.online-punch.punch'), {
      punch_type: type,
      session_id: sessionId,
      latitude,
      longitude,
    })

    const idx = punches.value.findIndex(p => p.punch_type === type)
    if (idx !== -1) punches.value[idx] = data.punch
    else punches.value.push(data.punch)

    cancelLiveness()

    const icon = data.punch.match_status === 'verified' ? 'success' : 'warning'
    await Swal.fire({ icon, title: data.message, timer: 2500, showConfirmButton: false })
  } catch (err) {
    const msg = err.response?.data?.message
      ?? Object.values(err.response?.data?.errors ?? {})[0]?.[0]
      ?? 'Something went wrong.'
    Swal.fire('Error', msg, 'error')
    cancelLiveness()
  }
}

function cancelLiveness() {
  unmountLiveness?.()
  unmountLiveness = null
  showLiveness.value = false
  activeSlotType.value = null
}

function getPosition() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) return reject(new Error('No geolocation'))
    navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 5000 })
  })
}

onUnmounted(() => unmountLiveness?.())
</script>
