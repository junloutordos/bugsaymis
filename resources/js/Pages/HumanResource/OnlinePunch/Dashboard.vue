<template>
  <Head title="Online Time Punches" />
  <AdminLayout title="Online Time Punches">
    <div class="max-w-2xl mx-auto space-y-5">

      <AppPageHeader title="Online Time Punches" subtitle="Face-verified time in/out for today." />

      <!-- Not enrolled -->
      <AppCard v-if="!enrollmentStatus" class="text-center">
        <p class="text-slate-600 mb-4">You haven't enrolled your face yet. Enrollment is required before you can use Online Time Punches.</p>
        <AppButton as="link" :href="route('hr.face-enrollment.self')">Enroll My Face</AppButton>
      </AppCard>

      <!-- Pending approval -->
      <div v-else-if="enrollmentStatus === 'pending'" class="bg-warning-50 border border-warning-100 rounded-xl p-8 text-center">
        <p class="text-warning-700 font-medium">Your face enrollment is awaiting HR approval.</p>
        <p class="text-sm text-warning-600 mt-1">You'll be able to punch in once it's approved.</p>
      </div>

      <!-- Rejected -->
      <div v-else-if="enrollmentStatus === 'rejected'" class="bg-danger-50 border border-danger-100 rounded-xl p-8 text-center">
        <p class="text-danger-700 font-medium">Your face enrollment was rejected.</p>
        <AppButton as="link" :href="route('hr.face-enrollment.self')" class="mt-3">Re-enroll</AppButton>
      </div>

      <!-- Approved: punch dashboard -->
      <AppCard v-else title="Today's Punches" :padded="false">
        <template #header>
          <span class="text-sm text-slate-400">{{ today }}</span>
        </template>
        <div class="p-5 grid grid-cols-2 gap-3">
          <div v-for="slot in punchSlots" :key="slot.type"
               class="rounded-xl p-3 flex flex-col items-center gap-2 border-2"
               :class="slotClass(slot.type)">
            <span class="text-xs font-semibold uppercase tracking-wide" :class="slotLabelClass(slot.type)">{{ slot.label }}</span>
            <span class="text-sm font-bold" :class="slotLabelClass(slot.type)">
              {{ punchFor(slot.type) ? fmtTime(punchFor(slot.type).punched_at) : '—' }}
            </span>
            <AppBadge v-if="punchFor(slot.type)" :color="statusBadgeColor(punchFor(slot.type).match_status)">
              {{ statusLabel(punchFor(slot.type).match_status) }}
            </AppBadge>
            <AppButton v-if="canPunch(slot.type)" size="sm" block class="mt-1" @click="openCamera(slot.type)">
              Punch
            </AppButton>
          </div>
        </div>
      </AppCard>

      <!-- Camera Modal -->
      <AppModal
        :show="showCamera"
        :title="activeSlotLabel"
        size="sm"
        :close-on-backdrop="false"
        :show-close-button="false"
        @close="cancelCamera"
      >
        <div class="space-y-3">
          <div v-if="!capturedImage" class="relative">
            <video ref="videoEl" autoplay playsinline class="w-full rounded-lg bg-black" style="max-height:280px;" />
            <AppButton block class="mt-3" @click="capture">
              📸 Capture Photo
            </AppButton>
          </div>

          <div v-else class="space-y-3">
            <img :src="capturedImage" class="w-full rounded-lg border border-slate-200" style="max-height:280px;object-fit:cover;" alt="Captured photo" />
            <div class="flex gap-3">
              <AppButton variant="secondary" block @click="retake">Retake</AppButton>
              <AppButton block :loading="loading" @click="confirmPunch">
                {{ loading ? 'Verifying…' : 'Confirm Punch' }}
              </AppButton>
            </div>
          </div>

          <AppButton variant="ghost" block @click="cancelCamera">Cancel</AppButton>

          <canvas ref="canvasEl" class="hidden" />
        </div>
      </AppModal>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
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

function statusBadgeColor(status) {
  if (status === 'verified') return 'green'
  if (status === 'manual_review') return 'amber'
  return 'red'
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
