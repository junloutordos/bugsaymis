<template>
  <Head title="Work From Home" />
  <AdminLayout title="Work From Home">
    <div class="max-w-2xl mx-auto p-6 space-y-6">

      <!-- ── Status Card ──────────────────────────────────────────────── -->
      <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-800">Today's WFH Status</h2>
          <span class="text-sm text-gray-400">{{ today }}</span>
        </div>

        <!-- Not yet timed in -->
        <div v-if="!attendance" class="text-center py-4">
          <p class="text-gray-500 mb-1">You have not timed in yet.</p>
          <p class="text-xs text-gray-400">Camera access is required to time in.</p>
        </div>

        <!-- Timed in or completed -->
        <div v-else class="space-y-4">
          <!-- Times row -->
          <div class="grid grid-cols-2 gap-3">

            <!-- Time In card -->
            <div class="bg-green-50 border-2 border-green-300 rounded-xl p-3 flex flex-col items-center gap-2">
              <span class="text-xs font-semibold text-green-600 uppercase tracking-wide">Time In</span>
              <span class="text-base font-bold text-green-700">{{ formatTime(attendance.time_in) }}</span>
              <a v-if="attendance.time_in_photo_link"
                 :href="attendance.time_in_photo_link" target="_blank">
                <img :src="driveThumb(attendance.time_in_photo_link)"
                     class="w-28 h-28 object-cover rounded-lg border-2 border-green-300"
                     alt="Time-in photo" />
              </a>
              <div v-else class="w-28 h-28 rounded-lg border-2 border-dashed border-green-200 flex items-center justify-center text-green-300 text-xs">
                No photo
              </div>
            </div>

            <!-- Time Out card -->
            <div class="bg-blue-50 border-2 border-blue-300 rounded-xl p-3 flex flex-col items-center gap-2">
              <span class="text-xs font-semibold text-blue-600 uppercase tracking-wide">Time Out</span>
              <span class="text-base font-bold text-blue-700">{{ attendance.time_out ? formatTime(attendance.time_out) : '—' }}</span>
              <a v-if="attendance.time_out_photo_link"
                 :href="attendance.time_out_photo_link" target="_blank">
                <img :src="driveThumb(attendance.time_out_photo_link)"
                     class="w-28 h-28 object-cover rounded-lg border-2 border-blue-300"
                     alt="Time-out photo" />
              </a>
              <div v-else class="w-28 h-28 rounded-lg border-2 border-dashed border-blue-200 flex items-center justify-center text-blue-300 text-xs">
                {{ attendance.time_out ? 'No photo' : 'Pending' }}
              </div>
            </div>

          </div>

          <!-- Duration (only when fully complete) -->
          <div v-if="attendance.time_out"
               class="text-center text-sm font-medium text-gray-600 bg-gray-50 rounded-lg py-2">
            Total Duration: <span class="text-gray-900 font-bold">{{ duration }}</span>
          </div>
        </div>
      </div>

      <!-- ── Camera Card ───────────────────────────────────────────────── -->
      <div v-if="showCamera" class="bg-white rounded-xl shadow p-6 space-y-4">
        <h3 class="font-semibold text-gray-700">
          {{ cameraMode === 'in' ? 'Capture Time-In Photo' : 'Capture Time-Out Photo' }}
        </h3>

        <!-- Not yet captured -->
        <div v-if="!capturedImage" class="relative">
          <video ref="videoEl" autoplay playsinline
                 class="w-full rounded-lg bg-black" style="max-height:280px;" />
          <button @click="capture"
                  class="mt-3 w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition">
            📸 Capture Photo
          </button>
        </div>

        <!-- Captured preview -->
        <div v-else class="space-y-3">
          <img :src="capturedImage" class="w-full rounded-lg border" alt="Captured photo"
               style="max-height:280px;object-fit:cover;" />
          <div class="flex gap-3">
            <button @click="retake"
                    class="flex-1 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
              Retake
            </button>
            <button @click="confirmCapture" :disabled="loading"
                    class="flex-1 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white rounded-lg font-medium transition">
              {{ loading ? 'Saving…' : (cameraMode === 'in' ? 'Confirm Time In' : 'Confirm Time Out') }}
            </button>
          </div>
        </div>

        <button @click="cancelCamera" class="text-sm text-gray-400 hover:text-gray-600 w-full text-center">
          Cancel
        </button>

        <!-- Hidden canvas for getUserMedia snapshot -->
        <canvas ref="canvasEl" class="hidden" />
      </div>

      <!-- ── Action Buttons ────────────────────────────────────────────── -->
      <div v-if="!showCamera" class="flex gap-4">
        <!-- Time In -->
        <button v-if="!attendance"
                @click="openCamera('in')"
                class="flex-1 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold shadow transition">
          🟢 Time In
        </button>

        <!-- Time Out -->
        <button v-if="attendance && !attendance.time_out"
                @click="openCamera('out')"
                class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold shadow transition">
          🔵 Time Out
        </button>

        <!-- Already completed -->
        <div v-if="attendance && attendance.time_out"
             class="flex-1 py-3 bg-gray-100 text-gray-500 rounded-xl text-center font-medium">
          ✅ WFH Day Complete
        </div>
      </div>

      <!-- ── Accomplishments shortcut ──────────────────────────────────── -->
      <div v-if="attendance" class="bg-white rounded-xl shadow p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="font-semibold text-gray-800">Accomplishments</p>
            <p class="text-sm text-gray-400">
              {{ attendance.accomplishments?.length ?? 0 }} recorded today
            </p>
          </div>
          <button @click="showAccomplishmentPanel = !showAccomplishmentPanel"
                  class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-lg transition">
            {{ showAccomplishmentPanel ? 'Hide' : 'Add / View' }}
          </button>
        </div>

        <!-- Inline Accomplishment Panel -->
        <div v-if="showAccomplishmentPanel" class="mt-4 border-t pt-4 space-y-4">
          <!-- Form -->
          <AccomplishmentForm :attendance-id="attendance.id" @saved="onAccomplishmentSaved" />

          <!-- List -->
          <AccomplishmentList :items="localAccomplishments" @deleted="onAccomplishmentDeleted" />
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'
import axios from 'axios'
import AccomplishmentForm from './AccomplishmentForm.vue'
import AccomplishmentList from './AccomplishmentList.vue'


// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps({
  todayAttendance: { type: Object, default: null },
  today:           { type: String, required: true },
})

// ── State ─────────────────────────────────────────────────────────────────────
const attendance             = ref(props.todayAttendance)
const localAccomplishments   = ref(props.todayAttendance?.accomplishments ?? [])
const showCamera             = ref(false)
const cameraMode             = ref('in')   // 'in' | 'out'
const capturedImage          = ref(null)
const loading                = ref(false)
const showAccomplishmentPanel = ref(false)

// Template refs
const videoEl  = ref(null)
const canvasEl = ref(null)
let mediaStream = null

// ── Computed ──────────────────────────────────────────────────────────────────
const duration = computed(() => {
  if (!attendance.value?.time_in || !attendance.value?.time_out) return '—'
  const ms = new Date(attendance.value.time_out) - new Date(attendance.value.time_in)
  const h  = Math.floor(ms / 3600000)
  const m  = Math.floor((ms % 3600000) / 60000)
  return `${h}h ${m}m`
})

// ── Camera ────────────────────────────────────────────────────────────────────
async function openCamera(mode) {
  cameraMode.value    = mode
  capturedImage.value = null
  showCamera.value    = true

  await nextTick()

  try {
    mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
    if (videoEl.value) videoEl.value.srcObject = mediaStream
  } catch {
    Swal.fire('Camera Error', 'Could not access your camera. Please allow camera permission.', 'error')
    showCamera.value = false
  }
}

function capture() {
  if (!videoEl.value || !canvasEl.value) return
  const video  = videoEl.value
  const canvas = canvasEl.value
  canvas.width  = video.videoWidth
  canvas.height = video.videoHeight
  canvas.getContext('2d').drawImage(video, 0, 0)
  capturedImage.value = canvas.toDataURL('image/jpeg', 0.85)
  stopStream()
}

function retake() {
  capturedImage.value = null
  openCamera(cameraMode.value)
}

function cancelCamera() {
  stopStream()
  capturedImage.value = null
  showCamera.value    = false
}

function stopStream() {
  mediaStream?.getTracks().forEach(t => t.stop())
  mediaStream = null
}

onUnmounted(stopStream)

// ── Submit time-in / time-out ─────────────────────────────────────────────────
async function confirmCapture() {
  if (!capturedImage.value || loading.value) return
  loading.value = true

  const payload = {
    photo:     capturedImage.value,
    latitude:  null,
    longitude: null,
  }

  // Optionally grab geolocation
  try {
    const pos = await getPosition()
    payload.latitude  = pos.coords.latitude
    payload.longitude = pos.coords.longitude
  } catch { /* geolocation optional */ }

  const url = cameraMode.value === 'in'
    ? route('hr.wfh.time-in')
    : route('hr.wfh.time-out')

  try {
    const { data } = await axios.post(url, payload)
    attendance.value    = data.attendance
    localAccomplishments.value = data.attendance?.accomplishments ?? []
    showCamera.value    = false
    capturedImage.value = null

    await Swal.fire({
      icon:  'success',
      title: cameraMode.value === 'in' ? 'Timed In!' : 'Timed Out!',
      text:  data.message,
      timer: 2000,
      showConfirmButton: false,
    })
  } catch (err) {
    const msg = err.response?.data?.message
      ?? err.response?.data?.errors?.date
      ?? 'Something went wrong.'
    Swal.fire('Error', Array.isArray(msg) ? msg[0] : msg, 'error')
  } finally {
    loading.value = false
  }
}

// ── Accomplishment callbacks ──────────────────────────────────────────────────
function onAccomplishmentSaved(item) {
  localAccomplishments.value.unshift(item)
  if (attendance.value) {
    attendance.value.accomplishments = localAccomplishments.value
  }
}

function onAccomplishmentDeleted(id) {
  localAccomplishments.value = localAccomplishments.value.filter(a => a.id !== id)
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function driveThumb(url) {
  if (!url) return ''
  const match = url.match(/\/d\/([a-zA-Z0-9_-]+)/)
  if (match) return route('hr.wfh.photo', { fileId: match[1] })
  return url
}

function formatTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })
}

function getPosition() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) return reject(new Error('No geolocation'))
    navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 5000 })
  })
}

// nextTick polyfill (Vue's nextTick used inline)
import { nextTick } from 'vue'
</script>
