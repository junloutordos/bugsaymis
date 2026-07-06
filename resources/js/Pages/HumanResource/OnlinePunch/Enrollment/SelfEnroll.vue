<template>
  <Head title="Face Enrollment" />
  <AdminLayout title="Face Enrollment">
    <div class="max-w-lg mx-auto space-y-6">

      <AppPageHeader title="Face Enrollment" />

      <!-- Existing enrollment status -->
      <AppCard v-if="enrollment" class="text-center space-y-3">
        <img v-if="enrollment.photo_url" :src="enrollment.photo_url" alt="Enrolled photo"
             class="w-32 h-32 rounded-full object-cover mx-auto border-4"
             :class="statusBorderClass" />
        <p class="font-semibold" :class="statusTextClass">{{ statusMessage }}</p>
        <p v-if="enrollment.status === 'rejected' && enrollment.rejection_reason" class="text-sm text-slate-500">
          Reason: {{ enrollment.rejection_reason }}
        </p>
        <AppButton v-if="enrollment.status !== 'pending'" @click="showCamera = true">
          {{ enrollment.status === 'approved' ? 'Re-enroll' : 'Try Again' }}
        </AppButton>
      </AppCard>

      <!-- Consent + capture -->
      <AppCard v-if="!enrollment || showCamera" title="Enroll Your Face" :padded="false">
        <div class="p-5 space-y-4">
          <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 text-sm text-slate-600">
            <p class="font-medium text-slate-700 mb-1">Data Privacy Notice</p>
            <p>Your facial photo will be stored and used solely to verify your identity when recording Online Time Punches, in compliance with the Data Privacy Act of 2012. It will be reviewed and approved by HR before activation.</p>
          </div>

          <label class="flex items-start gap-2 text-sm text-slate-700">
            <input type="checkbox" v-model="consent" class="mt-0.5 accent-indigo-600" />
            I consent to the collection and use of my facial photo for this purpose.
          </label>

          <div v-if="consent">
            <div v-if="!capturedImage" class="relative">
              <video ref="videoEl" autoplay playsinline class="w-full rounded-lg bg-black" style="max-height:280px;" />
              <AppButton block class="mt-3" @click="capture">
                📸 Capture Photo
              </AppButton>
            </div>
            <div v-else class="space-y-3">
              <img :src="capturedImage" class="w-full rounded-lg border border-slate-200" style="max-height:280px;object-fit:cover;" />
              <div class="flex gap-3">
                <AppButton variant="secondary" block @click="retake">Retake</AppButton>
                <AppButton block :loading="loading" @click="submit">
                  {{ loading ? 'Submitting…' : 'Submit for Approval' }}
                </AppButton>
              </div>
            </div>
            <canvas ref="canvasEl" class="hidden" />
          </div>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import Swal from 'sweetalert2'
import axios from 'axios'

const props = defineProps({
  enrollment: { type: Object, default: null },
})

const enrollment    = ref(props.enrollment)
const showCamera    = ref(false)
const consent       = ref(false)
const capturedImage = ref(null)
const loading       = ref(false)
const videoEl       = ref(null)
const canvasEl       = ref(null)
let mediaStream = null

const statusBorderClass = computed(() => {
  const s = enrollment.value?.status
  if (s === 'approved') return 'border-emerald-300'
  if (s === 'pending') return 'border-amber-300'
  return 'border-rose-300'
})

const statusTextClass = computed(() => {
  const s = enrollment.value?.status
  if (s === 'approved') return 'text-emerald-700'
  if (s === 'pending') return 'text-amber-700'
  return 'text-rose-700'
})

const statusMessage = computed(() => {
  const s = enrollment.value?.status
  if (s === 'approved') return 'Your face is enrolled and approved.'
  if (s === 'pending') return 'Awaiting HR approval.'
  return 'Your enrollment was rejected.'
})

async function startCamera() {
  await new Promise(r => setTimeout(r, 0))
  try {
    try {
      mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
    } catch {
      mediaStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false })
    }
    if (videoEl.value) videoEl.value.srcObject = mediaStream
  } catch (err) {
    Swal.fire('Camera Error', 'Could not access your camera. Please allow camera access and try again.', 'error')
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
  startCamera()
}

function stopStream() {
  mediaStream?.getTracks().forEach(t => t.stop())
  mediaStream = null
}

async function submit() {
  if (!capturedImage.value || loading.value) return
  loading.value = true
  try {
    await axios.post(route('hr.face-enrollment.store'), {
      photo: capturedImage.value,
      consent: true,
    })
    const { data } = await axios.get(route('hr.face-enrollment.my-status'))
    enrollment.value = data.enrollment
    showCamera.value = false
    capturedImage.value = null
    consent.value = false
    Swal.fire({ icon: 'success', title: 'Submitted!', text: 'Your enrollment is awaiting HR approval.', timer: 2500, showConfirmButton: false })
  } catch (err) {
    const msg = err.response?.data?.message
      ?? Object.values(err.response?.data?.errors ?? {})[0]?.[0]
      ?? 'Something went wrong.'
    Swal.fire('Error', msg, 'error')
  } finally {
    loading.value = false
  }
}

watch(consent, (val) => {
  if (val) startCamera()
  else stopStream()
})

onUnmounted(stopStream)
</script>
