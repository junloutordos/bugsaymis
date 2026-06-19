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
              <button v-if="canPunch(slot.type)" @click="openCamera(slot.type)"
                      class="mt-1 w-full inline-flex items-center justify-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                Punch
              </button>
            </div>
          </div>
        </div>
      </template>

      <!-- Camera Modal -->
      <div v-if="showCamera" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-4 space-y-3">
          <h3 class="text-base font-semibold text-slate-800 text-center">{{ activeSlotLabel }}</h3>

          <div v-if="!capturedImage" class="relative">
            <video ref="videoEl" autoplay playsinline class="w-full rounded-lg bg-black" style="max-height:280px;" />
            <button @click="capture"
                    class="mt-3 w-full inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
              📸 Capture Photo
            </button>
          </div>

          <div v-else class="space-y-3">
            <img :src="capturedImage" class="w-full rounded-lg border border-slate-200" style="max-height:280px;object-fit:cover;" alt="Captured photo" />
            <div class="flex gap-3">
              <button @click="retake"
                      class="flex-1 inline-flex items-center justify-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                Retake
              </button>
              <button @click="confirmPunch" :disabled="loading"
                      class="flex-1 inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                {{ loading ? 'Verifying…' : 'Confirm Punch' }}
              </button>
            </div>
          </div>

          <button @click="cancelCamera" class="text-sm text-slate-400 hover:text-slate-600 w-full text-center">
            Cancel
          </button>

          <canvas ref="canvasEl" class="hidden" />
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

const props = defineProps({
  today:            { type: String, required: true },
  enrollmentStatus: { type: String, default: null },
  todayPunches:     { type: Array, default: () => [] },
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

// ── Camera ────────────────────────────────────────────────────────────────────
const showCamera     = ref(false)
const activeSlotType = ref(null)
const capturedImage  = ref(null)
const loading        = ref(false)
const videoEl        = ref(null)
const canvasEl       = ref(null)
let mediaStream = null

const activeSlotLabel = computed(() => punchSlots.find(s => s.type === activeSlotType.value)?.label ?? '')

async function openCamera(type) {
  activeSlotType.value = type
  capturedImage.value  = null
  showCamera.value     = true

  await new Promise(resolve => setTimeout(resolve, 0)) // wait for v-if DOM mount

  try {
    try {
      mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
    } catch {
      mediaStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false })
    }
    if (videoEl.value) videoEl.value.srcObject = mediaStream
  } catch (err) {
    Swal.fire('Camera Error', 'Could not access your camera. Please allow camera access and try again.', 'error')
    showCamera.value = false
  }
}

function capture() {
  if (!videoEl.value || !canvasEl.value) return
  const video = videoEl.value, canvas = canvasEl.value
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight
  canvas.getContext('2d').drawImage(video, 0, 0)
  capturedImage.value = canvas.toDataURL('image/jpeg', 0.9)
  stopStream()
}

function retake() {
  capturedImage.value = null
  openCamera(activeSlotType.value)
}

function cancelCamera() {
  stopStream()
  capturedImage.value = null
  showCamera.value = false
  activeSlotType.value = null
}

function stopStream() {
  mediaStream?.getTracks().forEach(t => t.stop())
  mediaStream = null
}

async function confirmPunch() {
  if (!capturedImage.value || loading.value) return
  loading.value = true

  let latitude = null, longitude = null
  try {
    const pos = await getPosition()
    latitude = pos.coords.latitude
    longitude = pos.coords.longitude
  } catch { /* geolocation optional */ }

  try {
    const { data } = await axios.post(route('hr.online-punch.punch'), {
      punch_type: activeSlotType.value,
      photo: capturedImage.value,
      latitude,
      longitude,
    })

    const idx = punches.value.findIndex(p => p.punch_type === activeSlotType.value)
    if (idx !== -1) punches.value[idx] = data.punch
    else punches.value.push(data.punch)

    cancelCamera()

    const icon = data.punch.match_status === 'verified' ? 'success' : 'warning'
    await Swal.fire({ icon, title: data.message, timer: 2500, showConfirmButton: false })
  } catch (err) {
    const msg = err.response?.data?.message
      ?? Object.values(err.response?.data?.errors ?? {})[0]?.[0]
      ?? 'Something went wrong.'
    Swal.fire('Error', msg, 'error')
  } finally {
    loading.value = false
  }
}

function getPosition() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) return reject(new Error('No geolocation'))
    navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 5000 })
  })
}

onUnmounted(stopStream)
</script>
