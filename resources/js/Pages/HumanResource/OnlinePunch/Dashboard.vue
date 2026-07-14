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
            <span v-if="attemptsUsed(slot.type) > 0 && canPunch(slot.type)" class="text-[11px] text-slate-400">
              Attempt {{ attemptsUsed(slot.type) + 1 }} of {{ maxAttempts }}
            </span>
            <AppButton v-if="canPunch(slot.type)" size="sm" block class="mt-1" @click="openCamera(slot.type)">
              {{ attemptsUsed(slot.type) > 0 ? 'Retry' : 'Punch' }}
            </AppButton>
            <p v-else-if="attemptsExhausted(slot.type)" class="text-[11px] text-rose-500 text-center leading-snug mt-1">
              Max attempts reached — use the fingerprint biometric device.
            </p>
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

          <!-- Locating -->
          <div v-if="stage === 'locating'" class="py-10 text-center text-slate-500">
            <p>Getting your location…</p>
            <p class="text-xs text-slate-400 mt-1">You must be on campus to punch.</p>
          </div>

          <!-- Location denied / failed -->
          <div v-else-if="stage === 'location_denied'" class="py-8 text-center space-y-3">
            <p class="text-rose-700 font-medium">Location access is required to punch.</p>
            <p class="text-sm text-slate-500">Please allow location access — you must be on campus to use Online Time Punches.</p>
            <AppButton block @click="startLocating">Retry</AppButton>
          </div>

          <!-- Loading detection model -->
          <div v-else-if="stage === 'loading_model'" class="py-10 text-center text-slate-500">
            Preparing camera…
          </div>

          <!-- Live camera + guide -->
          <div v-else class="relative">
            <div class="relative rounded-lg overflow-hidden bg-black aspect-[3/4]">
              <video ref="videoEl" autoplay playsinline muted class="w-full h-full object-cover" />

              <!-- Oval face guide -->
              <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="rounded-full border-4 transition-colors duration-150"
                     :class="guideReady ? 'border-emerald-400' : 'border-white/60'"
                     style="width:65%;aspect-ratio:3/4;" />
              </div>

              <!-- Challenge overlay: big, hard-to-miss instruction + countdown -->
              <div v-if="stage === 'challenge' || stage === 'capturing'"
                   class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-black/75 text-white text-center px-6 gap-3">
                <div class="text-5xl" :class="{ 'animate-bounce': stage === 'challenge' }">
                  {{ challengeType === 'blink' ? '👁️' : '↔️' }}
                </div>
                <p class="text-base font-semibold leading-snug">{{ challengeInstruction }}</p>
                <p v-if="stage === 'challenge'" class="text-4xl font-bold tabular-nums">
                  {{ countdown > 0 ? countdown : 'Now!' }}
                </p>
                <p v-else class="text-sm text-emerald-300 font-semibold animate-pulse">Keep going…</p>

                <div v-if="stage === 'capturing'" class="w-full max-w-[200px] h-1.5 bg-white/30 rounded-full overflow-hidden mt-1">
                  <div class="h-full bg-emerald-400 transition-all" :style="{ width: captureProgress + '%' }" />
                </div>
              </div>

              <!-- Guidance text (positioning stage only) -->
              <div v-if="stage === 'positioning'" class="absolute bottom-0 inset-x-0 bg-black/60 text-white text-xs text-center py-2 px-2">
                {{ guidanceMessage }}
              </div>
            </div>

            <div v-if="stage === 'verifying'" class="text-center text-sm text-slate-500 mt-3">Verifying…</div>

            <AppButton variant="ghost" block class="mt-3" @click="cancelCamera">Cancel</AppButton>
          </div>

          <canvas ref="canvasEl" class="hidden" />
          <canvas ref="sampleCanvasEl" width="32" height="32" class="hidden" />
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

const maxAttempts = ref(3)

// Retries create additional rows per slot (up to maxAttempts) instead of
// overwriting — the most recent one (by punched_at) is what the UI reflects.
function punchFor(type) {
  const matches = punches.value.filter(p => p.punch_type === type)
  if (!matches.length) return null
  return matches.reduce((latest, p) => (p.punched_at > latest.punched_at ? p : latest))
}

function attemptsUsed(type) {
  return punches.value.filter(p => p.punch_type === type && p.match_status === 'rejected').length
}

function attemptsExhausted(type) {
  const latest = punchFor(type)
  if (latest && ['verified', 'manual_review'].includes(latest.match_status)) return false
  return attemptsUsed(type) >= maxAttempts.value
}

function canPunch(type) {
  const latest = punchFor(type)
  if (latest && ['verified', 'manual_review'].includes(latest.match_status)) return false
  if (attemptsExhausted(type)) return false
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

// ── Camera + Liveness Flow ───────────────────────────────────────────────────
// stage: locating -> location_denied | loading_model -> positioning -> challenge -> capturing -> verifying
const showCamera     = ref(false)
const activeSlotType = ref(null)
const stage          = ref('locating')
const guideReady     = ref(false)
const guidanceMessage = ref('')
const captureProgress = ref(0)
const challengeType        = ref(null)
const challengeInstruction = ref('')
const countdown             = ref(0)
const videoEl        = ref(null)
const canvasEl       = ref(null)
const sampleCanvasEl = ref(null)

let mediaStream = null
let rafId = null
let faceLandmarker = null
let readyStreak = 0
let brightnessTick = 0
let lastBrightness = 128
let geo = { latitude: null, longitude: null, accuracy: null }

const READY_STREAK_REQUIRED = 8
const FRAME_COUNT = 7
const FRAME_INTERVAL_MS = 450
const MIN_BRIGHTNESS = 55
const MAX_BRIGHTNESS = 235
const MIN_FACE_WIDTH_RATIO = 0.28
const MAX_FACE_WIDTH_RATIO = 0.6
const MAX_CENTER_OFFSET = 0.14

const activeSlotLabel = computed(() => punchSlots.find(s => s.type === activeSlotType.value)?.label ?? '')

async function openCamera(type) {
  activeSlotType.value = type
  showCamera.value     = true
  await startLocating()
}

async function startLocating() {
  stage.value = 'locating'
  try {
    const pos = await getPosition()
    geo = { latitude: pos.coords.latitude, longitude: pos.coords.longitude, accuracy: pos.coords.accuracy }
    await startCamera()
  } catch {
    stage.value = 'location_denied'
  }
}

function getPosition() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) return reject(new Error('No geolocation'))
    navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, timeout: 8000 })
  })
}

async function startCamera() {
  stage.value = 'loading_model'

  try {
    try {
      mediaStream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: { ideal: 720 }, height: { ideal: 960 } },
        audio: false,
      })
    } catch {
      mediaStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false })
    }
  } catch {
    Swal.fire('Camera Error', 'Could not access your camera. Please allow camera access and try again.', 'error')
    cancelCamera()
    return
  }

  await ensureFaceLandmarker()

  // The <video> element only exists in the DOM once stage leaves 'loading_model'
  // (see the v-else branch below) — flip the stage first so it mounts, then wait
  // a tick for Vue to render it before touching videoEl.
  stage.value = 'positioning'
  await new Promise(resolve => setTimeout(resolve, 0))
  if (videoEl.value) {
    videoEl.value.srcObject = mediaStream
    await new Promise(resolve => { videoEl.value.onloadedmetadata = resolve })
  }

  guidanceMessage.value = 'Center your face in the oval'
  readyStreak = 0
  detectionLoop()
}

async function ensureFaceLandmarker() {
  if (faceLandmarker) return

  const { FaceLandmarker, FilesetResolver } = await import('@mediapipe/tasks-vision')
  const vision = await FilesetResolver.forVisionTasks('/vendor/mediapipe/wasm')

  try {
    faceLandmarker = await FaceLandmarker.createFromOptions(vision, {
      baseOptions: { modelAssetPath: '/vendor/mediapipe/models/face_landmarker.task', delegate: 'GPU' },
      runningMode: 'VIDEO',
      numFaces: 1,
    })
  } catch {
    faceLandmarker = await FaceLandmarker.createFromOptions(vision, {
      baseOptions: { modelAssetPath: '/vendor/mediapipe/models/face_landmarker.task', delegate: 'CPU' },
      runningMode: 'VIDEO',
      numFaces: 1,
    })
  }
}

function detectionLoop() {
  if (stage.value !== 'positioning' || !videoEl.value || !faceLandmarker) return

  if (videoEl.value.readyState >= 2) {
    const result = faceLandmarker.detectForVideo(videoEl.value, performance.now())
    evaluateReadiness(result)
  }

  rafId = requestAnimationFrame(detectionLoop)
}

function evaluateReadiness(result) {
  const landmarks = result?.faceLandmarks?.[0]

  if (!landmarks) {
    guideReady.value = false
    guidanceMessage.value = 'No face detected — center your face in the oval'
    readyStreak = 0
    return
  }

  let minX = 1, maxX = 0, minY = 1, maxY = 0
  for (const p of landmarks) {
    if (p.x < minX) minX = p.x
    if (p.x > maxX) maxX = p.x
    if (p.y < minY) minY = p.y
    if (p.y > maxY) maxY = p.y
  }
  const widthRatio = maxX - minX
  const centerX = (minX + maxX) / 2
  const centerY = (minY + maxY) / 2
  const offset = Math.max(Math.abs(centerX - 0.5), Math.abs(centerY - 0.5))

  brightnessTick++
  if (brightnessTick % 5 === 0) lastBrightness = sampleBrightness()

  let msg = null
  if (widthRatio < MIN_FACE_WIDTH_RATIO) msg = 'Move closer'
  else if (widthRatio > MAX_FACE_WIDTH_RATIO) msg = 'Move back a little'
  else if (offset > MAX_CENTER_OFFSET) msg = 'Center your face in the oval'
  else if (lastBrightness < MIN_BRIGHTNESS) msg = 'Too dark — face a light source'
  else if (lastBrightness > MAX_BRIGHTNESS) msg = 'Too bright — avoid direct glare'

  if (msg) {
    guideReady.value = false
    guidanceMessage.value = msg
    readyStreak = 0
    return
  }

  readyStreak++
  guidanceMessage.value = 'Hold still…'

  if (readyStreak >= READY_STREAK_REQUIRED) {
    guideReady.value = true
    beginChallenge()
  }
}

function sampleBrightness() {
  const video = videoEl.value, canvas = sampleCanvasEl.value
  if (!video || !canvas) return lastBrightness
  const ctx = canvas.getContext('2d')
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height)
  const data = ctx.getImageData(0, 0, canvas.width, canvas.height).data
  let sum = 0
  for (let i = 0; i < data.length; i += 4) {
    sum += 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2]
  }
  return sum / (data.length / 4)
}

let challengeInFlight = false

async function beginChallenge() {
  if (challengeInFlight || stage.value !== 'positioning') return
  challengeInFlight = true

  try {
    const { data } = await axios.post(route('hr.online-punch.challenge.start'))
    stopDetectionLoop()
    stage.value = 'challenge'
    challengeType.value = data.challenge_type
    challengeInstruction.value = data.instruction

    countdown.value = 5
    while (countdown.value > 0) {
      await new Promise(resolve => setTimeout(resolve, 800))
      countdown.value--
    }

    await captureBurst(data.challenge_token)
  } catch {
    challengeInFlight = false
    guidanceMessage.value = 'Something went wrong — hold still and try again'
    readyStreak = 0
    detectionLoop()
  }
}

function stopDetectionLoop() {
  if (rafId) cancelAnimationFrame(rafId)
  rafId = null
}

async function captureBurst(challengeToken) {
  stage.value = 'capturing'
  captureProgress.value = 0

  const frames = []
  const start = performance.now()

  for (let i = 0; i < FRAME_COUNT; i++) {
    frames.push({
      data: snapshotFrame(),
      capturedAtMs: Math.round(performance.now() - start),
    })
    captureProgress.value = Math.round(((i + 1) / FRAME_COUNT) * 100)
    if (i < FRAME_COUNT - 1) await new Promise(resolve => setTimeout(resolve, FRAME_INTERVAL_MS))
  }

  await submitPunch(frames, challengeToken)
}

function snapshotFrame() {
  const video = videoEl.value, canvas = canvasEl.value
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight
  canvas.getContext('2d').drawImage(video, 0, 0)
  return canvas.toDataURL('image/jpeg', 0.9)
}

function dtrSummaryMessage(baseMessage, summary) {
  if (!summary) return baseMessage
  const m = summary.minutes
  const phrase = {
    early:     `You're ${m} minute${m === 1 ? '' : 's'} early.`,
    on_time:   'Right on schedule!',
    late:      `You're ${m} minute${m === 1 ? '' : 's'} late.`,
    undertime: `${m} minute${m === 1 ? '' : 's'} undertime.`,
    overtime:  `${m} minute${m === 1 ? '' : 's'} overtime.`,
  }[summary.status]
  return phrase ? `${baseMessage} ${phrase}` : baseMessage
}

async function submitPunch(frames, challengeToken) {
  stage.value = 'verifying'
  stopStream()
  const slotType = activeSlotType.value

  try {
    const { data } = await axios.post(route('hr.online-punch.punch'), {
      punch_type:      slotType,
      frames,
      challenge_token: challengeToken,
      latitude:        geo.latitude,
      longitude:       geo.longitude,
      accuracy:        geo.accuracy,
    })

    punches.value.push(data.punch)
    maxAttempts.value = data.max_attempts ?? maxAttempts.value
    cancelCamera()

    if (data.punch.match_status === 'verified') {
      await Swal.fire({ icon: 'success', title: dtrSummaryMessage(data.message, data.dtr_summary), timer: 3000, showConfirmButton: false })
      return
    }

    if (data.punch.match_status === 'manual_review') {
      await Swal.fire({ icon: 'warning', title: data.message, timer: 3000, showConfirmButton: false })
      return
    }

    // rejected — offer a retry if attempts remain, otherwise redirect to the fingerprint device.
    if (data.attempts_used >= data.max_attempts) {
      await Swal.fire({
        icon: 'error',
        title: 'Maximum attempts reached',
        text: `You've used all ${data.max_attempts} attempts for this punch. Please proceed to the fingerprint biometric device to record your attendance.`,
        confirmButtonText: 'OK',
      })
      return
    }

    const result = await Swal.fire({
      icon: 'warning',
      title: data.message,
      text: `${data.attempts_used} of ${data.max_attempts} attempts used.`,
      showCancelButton: true,
      confirmButtonText: 'Retry',
      cancelButtonText: 'Cancel',
    })
    if (result.isConfirmed) openCamera(slotType)
  } catch (err) {
    if (err.response?.data?.errors?.max_attempts) {
      await Swal.fire({
        icon: 'error',
        title: 'Maximum attempts reached',
        text: err.response.data.errors.max_attempts[0],
        confirmButtonText: 'OK',
      })
      cancelCamera()
      return
    }

    const msg = err.response?.data?.message
      ?? Object.values(err.response?.data?.errors ?? {})[0]?.[0]
      ?? 'Something went wrong.'
    await Swal.fire('Error', msg, 'error')
    cancelCamera()
  } finally {
    challengeInFlight = false
  }
}

function cancelCamera() {
  stopDetectionLoop()
  stopStream()
  showCamera.value = false
  activeSlotType.value = null
  stage.value = 'locating'
  guideReady.value = false
  readyStreak = 0
  challengeInFlight = false
}

function stopStream() {
  mediaStream?.getTracks().forEach(t => t.stop())
  mediaStream = null
}

onUnmounted(() => {
  stopDetectionLoop()
  stopStream()
  faceLandmarker?.close()
})
</script>
