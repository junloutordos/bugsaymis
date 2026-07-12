<template>
  <Head title="Work From Home" />
  <AdminLayout title="Work From Home">
    <div class="max-w-2xl mx-auto space-y-6">

      <AppPageHeader hero class="dash-section" style="--stagger: 0" title="Work From Home" subtitle="Track your time in/out, breaks, and daily accomplishments." />

      <!-- Status Card -->
      <AppCard :padded="false" class="dash-section" style="--stagger: 1">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-xl font-semibold text-slate-800">Today's WFH Status</h2>
          <span class="text-sm text-slate-400">{{ today }}</span>
        </div>
        <div class="p-5">
          <!-- Not yet timed in -->
          <div v-if="!attendance" class="py-16 text-center text-slate-400 text-sm">
            <p class="mb-1">You have not timed in yet.</p>
            <p class="text-xs">Camera access is required to time in.</p>
          </div>

          <!-- Timed in or completed -->
          <div v-else class="space-y-4">
            <!-- Row 1: Time In / Time Out -->
            <div class="grid grid-cols-2 gap-3">
              <!-- Time In card -->
              <div class="bg-emerald-50 border-2 border-emerald-300 rounded-xl p-3 flex flex-col items-center gap-2">
                <span class="text-xs font-semibold text-emerald-600 uppercase tracking-wide">Time In</span>
                <span class="text-base font-bold text-emerald-700">{{ formatTime(attendance.time_in) }}</span>
                <a v-if="attendance.time_in_photo_link" :href="attendance.time_in_photo_link" target="_blank">
                  <img :src="driveThumb(attendance.time_in_photo_link)"
                       class="w-28 h-28 object-cover rounded-lg border-2 border-emerald-300"
                       alt="Time-in photo" />
                </a>
                <div v-else class="w-28 h-28 rounded-lg border-2 border-dashed border-emerald-200 flex items-center justify-center text-emerald-300 text-xs">
                  No photo
                </div>
              </div>

              <!-- Time Out card -->
              <div class="bg-blue-50 border-2 border-blue-300 rounded-xl p-3 flex flex-col items-center gap-2">
                <span class="text-xs font-semibold text-blue-600 uppercase tracking-wide">Time Out</span>
                <span class="text-base font-bold text-blue-700">{{ attendance.time_out ? formatTime(attendance.time_out) : '—' }}</span>
                <a v-if="attendance.time_out_photo_link" :href="attendance.time_out_photo_link" target="_blank">
                  <img :src="driveThumb(attendance.time_out_photo_link)"
                       class="w-28 h-28 object-cover rounded-lg border-2 border-blue-300"
                       alt="Time-out photo" />
                </a>
                <div v-else class="w-28 h-28 rounded-lg border-2 border-dashed border-blue-200 flex items-center justify-center text-blue-300 text-xs">
                  {{ attendance.time_out ? 'No photo' : 'Pending' }}
                </div>
              </div>
            </div>

            <!-- Row 2: Break Out / Break In -->
            <div class="grid grid-cols-2 gap-3">
              <div class="rounded-xl p-3 flex flex-col items-center gap-1 border-2"
                   :class="attendance.break_out
                     ? 'bg-amber-50 border-amber-300'
                     : 'bg-slate-50 border-dashed border-slate-200'">
                <span class="text-xs font-semibold uppercase tracking-wide"
                      :class="attendance.break_out ? 'text-amber-600' : 'text-slate-400'">
                  Break Out
                </span>
                <span class="text-sm font-bold"
                      :class="attendance.break_out ? 'text-amber-700' : 'text-slate-300'">
                  {{ attendance.break_out ? formatTime(attendance.break_out) : '—' }}
                </span>
                <span class="text-[10px] text-slate-400">Lunch start</span>
              </div>

              <div class="rounded-xl p-3 flex flex-col items-center gap-1 border-2"
                   :class="attendance.break_in
                     ? 'bg-teal-50 border-teal-300'
                     : 'bg-slate-50 border-dashed border-slate-200'">
                <span class="text-xs font-semibold uppercase tracking-wide"
                      :class="attendance.break_in ? 'text-teal-600' : 'text-slate-400'">
                  Break In
                </span>
                <span class="text-sm font-bold"
                      :class="attendance.break_in ? 'text-teal-700' : 'text-slate-300'">
                  {{ attendance.break_in ? formatTime(attendance.break_in) : '—' }}
                </span>
                <span class="text-[10px] text-slate-400">Lunch end</span>
              </div>
            </div>

            <!-- Break duration pill -->
            <div v-if="attendance.break_out && attendance.break_in"
                 class="text-center text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg py-1.5">
              🍽️ Lunch break: <strong>{{ breakDuration }}</strong>
            </div>

            <!-- On break indicator -->
            <div v-else-if="attendance.break_out && !attendance.break_in"
                 class="text-center text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-lg py-1.5 animate-pulse">
              🍽️ Currently on lunch break…
            </div>

            <!-- Total work duration -->
            <div v-if="attendance.time_out"
                 class="text-center text-sm font-medium text-slate-600 bg-slate-50 rounded-lg py-2">
              Work Duration: <span class="text-slate-900 font-bold">{{ workDuration }}</span>
            </div>
          </div>
        </div>
      </AppCard>

      <!-- Camera Card -->
      <AppCard v-if="showCamera" :padded="false" class="dash-section" style="--stagger: 2">
        <div class="px-5 py-4 border-b border-slate-100">
          <h3 class="text-xl font-semibold text-slate-800">
            {{ cameraMode === 'in' ? 'Capture Time-In Photo' : 'Capture Time-Out Photo' }}
          </h3>
        </div>
        <div class="p-5 space-y-4">
          <div v-if="!capturedImage" class="relative">
            <video ref="videoEl" autoplay playsinline
                   class="w-full rounded-lg bg-black" style="max-height:280px;" />
            <AppButton block class="mt-3" @click="capture">📸 Capture Photo</AppButton>
          </div>

          <div v-else class="space-y-3">
            <img :src="capturedImage" class="w-full rounded-lg border border-slate-200" alt="Captured photo"
                 style="max-height:280px;object-fit:cover;" />
            <div class="flex gap-3">
              <AppButton variant="secondary" block class="flex-1" @click="retake">Retake</AppButton>
              <AppButton block class="flex-1" :disabled="loading" @click="confirmCapture">
                {{ loading ? 'Saving…' : (cameraMode === 'in' ? 'Confirm Time In' : 'Confirm Time Out') }}
              </AppButton>
            </div>
          </div>

          <AppButton variant="ghost" block @click="cancelCamera">Cancel</AppButton>

          <canvas ref="canvasEl" class="hidden" />
        </div>
      </AppCard>

      <!-- Action Buttons -->
      <div v-if="!showCamera" class="flex flex-wrap gap-3">
        <AppButton v-if="!attendance"
                   variant="success" size="lg" class="flex-1"
                   @click="openCamera('in')">
          🟢 Time In
        </AppButton>

        <AppButton v-if="attendance && !attendance.break_out && !attendance.time_out"
                   variant="warning" size="lg" class="flex-1"
                   :disabled="breakLoading" @click="recordBreak('out')">
          {{ breakLoading ? 'Saving…' : '🍽️ Break Out' }}
        </AppButton>

        <AppButton v-if="attendance && attendance.break_out && !attendance.break_in && !attendance.time_out"
                   variant="success" size="lg" class="flex-1"
                   :disabled="breakLoading" @click="recordBreak('in')">
          {{ breakLoading ? 'Saving…' : '✅ Break In' }}
        </AppButton>

        <AppButton v-if="attendance && !attendance.time_out"
                   size="lg" class="flex-1"
                   @click="openCamera('out')">
          🔵 Time Out
        </AppButton>

        <div v-if="attendance && attendance.time_out"
             class="flex-1 py-3 bg-slate-100 text-slate-500 rounded-xl text-center text-sm font-medium">
          ✅ WFH Day Complete
        </div>
      </div>

      <!-- Today's Accomplishments -->
      <AppCard v-if="attendance" :padded="false" class="dash-section" style="--stagger: 3">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <p class="font-semibold text-slate-800">Today's Accomplishments</p>
            <p class="text-sm text-slate-400">
              {{ localAccomplishments.length }} recorded today
            </p>
          </div>
          <AppButton size="sm" @click="showAccomplishmentPanel = !showAccomplishmentPanel">
            {{ showAccomplishmentPanel ? 'Hide' : 'Add / View' }}
          </AppButton>
        </div>

        <div v-if="showAccomplishmentPanel" class="p-5 space-y-4">
          <AccomplishmentForm :attendance-id="attendance.id" @saved="onAccomplishmentSaved" />
          <AccomplishmentList
            :items="localAccomplishments"
            @deleted="onAccomplishmentDeleted"
            @updated="onAccomplishmentUpdated"
          />
        </div>
      </AppCard>

      <!-- ── My WFH History ──────────────────────────────────────────────────── -->
      <AppCard :padded="false" class="dash-section" style="--stagger: 4">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3 flex-wrap">
          <h2 class="text-base font-semibold text-slate-800">My WFH History</h2>
          <div class="flex items-center gap-2">
            <input
              type="month"
              v-model="historyMonth"
              @change="loadHistory"
              class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <AppButton size="sm" variant="secondary" :disabled="historyLoading" @click="loadHistory">
              {{ historyLoading ? 'Loading…' : 'Refresh' }}
            </AppButton>
          </div>
        </div>

        <div class="divide-y divide-slate-100">
          <!-- Empty state -->
          <EmptyState v-if="!historyLoading && !historyRecords.length" title="No WFH records for this month." />

          <!-- Loading -->
          <div v-if="historyLoading" class="py-12 text-center text-slate-400 text-sm">
            Loading…
          </div>

          <!-- Records list -->
          <div
            v-for="record in historyRecords"
            :key="record.id"
          >
            <!-- Day header row -->
            <button
              @click="toggleHistoryRecord(record.id)"
              class="w-full px-5 py-3 flex items-center justify-between gap-3 hover:bg-slate-50 transition-colors text-left"
            >
              <div class="flex items-center gap-3">
                <!-- Date -->
                <div class="text-center w-10 shrink-0">
                  <p class="text-xs text-slate-400 leading-none">{{ fmtDayName(record.date) }}</p>
                  <p class="text-lg font-bold text-slate-800 leading-tight">{{ fmtDayNum(record.date) }}</p>
                </div>

                <!-- Status pills -->
                <div class="flex flex-wrap gap-1.5">
                  <AppBadge :color="record.time_in ? 'green' : 'slate'">
                    In {{ record.time_in ? fmtShortTime(record.time_in) : '—' }}
                  </AppBadge>
                  <AppBadge v-if="record.break_out" color="amber">
                    Break Out {{ fmtShortTime(record.break_out) }}
                  </AppBadge>
                  <AppBadge v-if="record.break_in" color="purple">
                    Break In {{ fmtShortTime(record.break_in) }}
                  </AppBadge>
                  <AppBadge :color="record.time_out ? 'blue' : 'slate'">
                    Out {{ record.time_out ? fmtShortTime(record.time_out) : '—' }}
                  </AppBadge>
                  <AppBadge v-if="record.accomplishments?.length" color="indigo">
                    📝 {{ record.accomplishments.length }}
                  </AppBadge>
                </div>
              </div>

              <!-- Chevron -->
              <ChevronDownIcon
                class="h-4 w-4 text-slate-400 shrink-0 transition-transform"
                :class="{ 'rotate-180': expandedHistoryId === record.id }"
              />
            </button>

            <!-- Expanded panel -->
            <div v-if="expandedHistoryId === record.id" class="px-5 pb-5 space-y-4 bg-slate-50/60">
              <!-- Accomplishments for this day -->
              <div class="pt-4">
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Accomplishments</p>
                <AccomplishmentList
                  :items="historyAccomplishments[record.id] ?? record.accomplishments ?? []"
                  @deleted="(id) => onHistoryAccomplishmentDeleted(record.id, id)"
                  @updated="(item) => onHistoryAccomplishmentUpdated(record.id, item)"
                />
              </div>

              <!-- Add accomplishment for this past day -->
              <div class="border-t border-slate-200 pt-4">
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Add Accomplishment</p>
                <AccomplishmentForm
                  :attendance-id="record.id"
                  @saved="(item) => onHistoryAccomplishmentSaved(record.id, item)"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <PaginationControl
          v-if="historyMeta"
          :current-page="historyMeta.current_page"
          :total-pages="historyMeta.last_page"
          @prev="loadHistory(historyMeta.current_page - 1)"
          @next="loadHistory(historyMeta.current_page + 1)"
        />
      </AppCard>

      <!-- Print shortcuts -->
      <AppCard class="dash-section" style="--stagger: 5">
        <div class="flex flex-wrap gap-3 items-center">
          <span class="text-sm font-medium text-slate-600 mr-auto">Print Reports</span>
          <AppButton variant="secondary" @click="showTimeLogPrintModal = true">🖨️ Time Logs</AppButton>
          <AppButton variant="secondary" @click="showPrintModal = true">🖨️ Accomplishments</AppButton>
        </div>
      </AppCard>

      <!-- Print Time Logs Modal -->
      <AppModal :show="showTimeLogPrintModal" title="Print Time Logs" size="sm" @close="showTimeLogPrintModal = false">
        <div class="space-y-4">
          <div class="flex gap-3">
            <label v-for="opt in [{v:'daily',l:'Daily'},{v:'monthly',l:'Monthly'},{v:'range',l:'Date Range'}]" :key="opt.v"
              class="flex items-center gap-1.5 cursor-pointer text-sm text-slate-700">
              <input type="radio" v-model="tlPrintMode" :value="opt.v" class="accent-indigo-600" />
              {{ opt.l }}
            </label>
          </div>

          <div v-if="tlPrintMode === 'daily'">
            <label class="block text-xs font-medium text-slate-600 mb-1">Date</label>
            <input type="date" v-model="tlPrintDate"
              class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
          </div>

          <div v-if="tlPrintMode === 'monthly'">
            <label class="block text-xs font-medium text-slate-600 mb-1">Month</label>
            <input type="month" v-model="tlPrintMonth"
              class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
          </div>

          <div v-if="tlPrintMode === 'range'" class="space-y-2">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">From</label>
              <input type="date" v-model="tlPrintDateFrom"
                class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">To</label>
              <input type="date" v-model="tlPrintDateTo"
                class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
          </div>
        </div>

        <template #footer>
          <AppButton variant="secondary" @click="showTimeLogPrintModal = false">Cancel</AppButton>
          <AppButton as="a" :href="printTimeLogsUrl" target="_blank">🖨️ Print</AppButton>
        </template>
      </AppModal>

      <!-- Print Accomplishments Modal -->
      <AppModal :show="showPrintModal" title="Print Accomplishments" size="sm" @close="showPrintModal = false">
        <div class="space-y-4">
          <div class="flex gap-3">
            <label v-for="opt in [{v:'daily',l:'Daily'},{v:'monthly',l:'Monthly'},{v:'range',l:'Date Range'}]" :key="opt.v"
              class="flex items-center gap-1.5 cursor-pointer text-sm text-slate-700">
              <input type="radio" v-model="printMode" :value="opt.v" class="accent-indigo-600" />
              {{ opt.l }}
            </label>
          </div>

          <div v-if="printMode === 'daily'">
            <label class="block text-xs font-medium text-slate-600 mb-1">Date</label>
            <input type="date" v-model="printDate"
              class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
          </div>

          <div v-if="printMode === 'monthly'">
            <label class="block text-xs font-medium text-slate-600 mb-1">Month</label>
            <input type="month" v-model="printMonth"
              class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
          </div>

          <div v-if="printMode === 'range'" class="space-y-2">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">From</label>
              <input type="date" v-model="printDateFrom"
                class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">To</label>
              <input type="date" v-model="printDateTo"
                class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
          </div>
        </div>

        <template #footer>
          <AppButton variant="secondary" @click="showPrintModal = false">Cancel</AppButton>
          <AppButton as="a" :href="printAccomplishmentsUrl" target="_blank">🖨️ Print</AppButton>
        </template>
      </AppModal>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { ChevronDownIcon } from '@heroicons/vue/24/outline'
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
const currentMonth           = new Date().toISOString().slice(0, 7)
const today_str              = new Date().toISOString().slice(0, 10)
const attendance             = ref(props.todayAttendance)
const localAccomplishments   = ref(props.todayAttendance?.accomplishments ?? [])
const showCamera             = ref(false)
const cameraMode             = ref('in')
const capturedImage          = ref(null)
const loading                = ref(false)
const breakLoading           = ref(false)
const showAccomplishmentPanel = ref(false)

// History
const historyMonth           = ref(currentMonth)
const historyLoading         = ref(false)
const historyRecords         = ref([])
const historyMeta            = ref(null)
const expandedHistoryId      = ref(null)
// Per-record accomplishment overrides (after add/edit/delete)
const historyAccomplishments = ref({}) // { [recordId]: Accomplishment[] }

// Print modal — Accomplishments
const showPrintModal  = ref(false)
const printMode       = ref('monthly')
const printDate       = ref(today_str)
const printMonth      = ref(currentMonth)
const printDateFrom   = ref(today_str)
const printDateTo     = ref(today_str)

const printAccomplishmentsUrl = computed(() => {
  const base = '/hr/wfh/print/accomplishments'
  if (printMode.value === 'daily')   return `${base}?mode=daily&date=${printDate.value}`
  if (printMode.value === 'range')   return `${base}?mode=range&date_from=${printDateFrom.value}&date_to=${printDateTo.value}`
  return `${base}?mode=monthly&month=${printMonth.value}`
})

// Print modal — Time Logs
const showTimeLogPrintModal = ref(false)
const tlPrintMode           = ref('monthly')
const tlPrintDate           = ref(today_str)
const tlPrintMonth          = ref(currentMonth)
const tlPrintDateFrom       = ref(today_str)
const tlPrintDateTo         = ref(today_str)

const printTimeLogsUrl = computed(() => {
  const base = '/hr/wfh/print/timelogs'
  if (tlPrintMode.value === 'daily')   return `${base}?mode=daily&date=${tlPrintDate.value}`
  if (tlPrintMode.value === 'range')   return `${base}?mode=range&date_from=${tlPrintDateFrom.value}&date_to=${tlPrintDateTo.value}`
  return `${base}?mode=monthly&month=${tlPrintMonth.value}`
})

// Template refs
const videoEl  = ref(null)
const canvasEl = ref(null)
let mediaStream = null

// ── Helpers ───────────────────────────────────────────────────────────────────
/** Normalize any Laravel date string to ISO 8601 before parsing.
 *  Handles "2024-01-15 08:00:00" (MySQL) and "2024-01-15T08:00:00.000000Z" (ISO). */
function toDate(val) {
  if (!val) return null
  const d = new Date(String(val).replace(' ', 'T'))
  return isNaN(d.getTime()) ? null : d
}

// ── Computed ──────────────────────────────────────────────────────────────────
const breakDuration = computed(() => {
  const out = toDate(attendance.value?.break_out)
  const inn = toDate(attendance.value?.break_in)
  if (!out || !inn) return '—'
  const ms = inn - out
  const h  = Math.floor(ms / 3600000)
  const m  = Math.floor((ms % 3600000) / 60000)
  return h > 0 ? `${h}h ${m}m` : `${m}m`
})

const workDuration = computed(() => {
  const inn = toDate(attendance.value?.time_in)
  const out = toDate(attendance.value?.time_out)
  if (!inn || !out) return '—'
  let ms = out - inn
  const bOut = toDate(attendance.value?.break_out)
  const bIn  = toDate(attendance.value?.break_in)
  if (bOut && bIn) ms -= bIn - bOut
  const h = Math.floor(ms / 3600000)
  const m = Math.floor((ms % 3600000) / 60000)
  return `${h}h ${m}m`
})

// ── Camera ────────────────────────────────────────────────────────────────────
async function openCamera(mode) {
  cameraMode.value    = mode
  capturedImage.value = null
  showCamera.value    = true

  await nextTick()

  try {
    // Try front camera first, fall back to any available camera
    try {
      mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
    } catch {
      mediaStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false })
    }
    if (videoEl.value) videoEl.value.srcObject = mediaStream
  } catch (err) {
    const denied = err?.name === 'NotAllowedError' || err?.name === 'PermissionDeniedError'
    Swal.fire(
      'Camera Error',
      denied
        ? 'Camera permission was denied. Please allow camera access in your browser settings and try again.'
        : 'Could not access your camera. Make sure no other app is using it and try again.',
      'error'
    )
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

  const payload = { photo: capturedImage.value, latitude: null, longitude: null }

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
    attendance.value           = data.attendance
    localAccomplishments.value = data.attendance?.accomplishments ?? []
    showCamera.value           = false
    capturedImage.value        = null

    await Swal.fire({
      icon:  'success',
      title: cameraMode.value === 'in' ? 'Timed In!' : 'Timed Out!',
      text:  data.message,
      timer: 2000,
      showConfirmButton: false,
    })

    // Refresh history so today's record appears
    loadHistory()
  } catch (err) {
    const msg = err.response?.data?.message
      ?? err.response?.data?.errors?.date
      ?? 'Something went wrong.'
    Swal.fire('Error', Array.isArray(msg) ? msg[0] : msg, 'error')
  } finally {
    loading.value = false
  }
}

// ── Break Out / Break In ──────────────────────────────────────────────────────
async function recordBreak(direction) {
  if (breakLoading.value) return
  breakLoading.value = true

  const url = direction === 'out'
    ? route('hr.wfh.break-out')
    : route('hr.wfh.break-in')

  const titles = {
    out: { title: 'Break Out!', text: 'Enjoy your lunch break. 🍽️' },
    in:  { title: 'Welcome Back!', text: 'Break ended. Back to work! 💪' },
  }

  try {
    const { data } = await axios.post(url)
    attendance.value = data.attendance

    await Swal.fire({
      icon:  'success',
      title: titles[direction].title,
      text:  titles[direction].text,
      timer: 1800,
      showConfirmButton: false,
    })
  } catch (err) {
    const msg = err.response?.data?.message
      ?? err.response?.data?.errors?.date
      ?? 'Something went wrong.'
    Swal.fire('Error', Array.isArray(msg) ? msg[0] : msg, 'error')
  } finally {
    breakLoading.value = false
  }
}

// ── Today's accomplishment callbacks ──────────────────────────────────────────
function onAccomplishmentSaved(item) {
  localAccomplishments.value.unshift(item)
  if (attendance.value) {
    attendance.value.accomplishments = localAccomplishments.value
  }
}

function onAccomplishmentDeleted(id) {
  localAccomplishments.value = localAccomplishments.value.filter(a => a.id !== id)
}

function onAccomplishmentUpdated(item) {
  const idx = localAccomplishments.value.findIndex(a => a.id === item.id)
  if (idx !== -1) localAccomplishments.value[idx] = item
}

// ── History ───────────────────────────────────────────────────────────────────
async function loadHistory(page = 1) {
  historyLoading.value = true
  try {
    const { data } = await axios.get(route('hr.wfh.attendance.index'), {
      params: { month: historyMonth.value, page },
    })
    historyRecords.value = data.data
    historyMeta.value    = data.meta ?? { current_page: data.current_page, last_page: data.last_page }
    // Reset per-record accomplishments cache when month changes
    historyAccomplishments.value = {}
    expandedHistoryId.value = null
  } catch {
    Swal.fire('Error', 'Could not load WFH history.', 'error')
  } finally {
    historyLoading.value = false
  }
}

function toggleHistoryRecord(id) {
  expandedHistoryId.value = expandedHistoryId.value === id ? null : id
}

function onHistoryAccomplishmentSaved(recordId, item) {
  if (!historyAccomplishments.value[recordId]) {
    const record = historyRecords.value.find(r => r.id === recordId)
    historyAccomplishments.value[recordId] = [...(record?.accomplishments ?? [])]
  }
  historyAccomplishments.value[recordId].unshift(item)

  // Also update the count in historyRecords
  const record = historyRecords.value.find(r => r.id === recordId)
  if (record) record.accomplishments = historyAccomplishments.value[recordId]
}

function onHistoryAccomplishmentDeleted(recordId, itemId) {
  if (!historyAccomplishments.value[recordId]) {
    const record = historyRecords.value.find(r => r.id === recordId)
    historyAccomplishments.value[recordId] = [...(record?.accomplishments ?? [])]
  }
  historyAccomplishments.value[recordId] = historyAccomplishments.value[recordId].filter(a => a.id !== itemId)

  const record = historyRecords.value.find(r => r.id === recordId)
  if (record) record.accomplishments = historyAccomplishments.value[recordId]
}

function onHistoryAccomplishmentUpdated(recordId, item) {
  if (!historyAccomplishments.value[recordId]) {
    const record = historyRecords.value.find(r => r.id === recordId)
    historyAccomplishments.value[recordId] = [...(record?.accomplishments ?? [])]
  }
  const idx = historyAccomplishments.value[recordId].findIndex(a => a.id === item.id)
  if (idx !== -1) historyAccomplishments.value[recordId][idx] = item
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function driveThumb(url) {
  if (!url) return ''
  const match = url.match(/\/d\/([a-zA-Z0-9_-]+)/)
  if (match) return route('hr.wfh.photo', { fileId: match[1] })
  return url
}

function formatTime(val) {
  const d = toDate(val)
  if (!d) return '—'
  return d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })
}

function fmtShortTime(val) {
  const d = toDate(val)
  if (!d) return '—'
  return d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', hour12: true })
}

function fmtDayName(dateStr) {
  if (!dateStr) return ''
  const d = toDate(dateStr)
  if (!d) return ''
  return d.toLocaleDateString('en-PH', { weekday: 'short' })
}

function fmtDayNum(dateStr) {
  if (!dateStr) return ''
  const d = toDate(dateStr)
  if (!d) return ''
  return d.getDate()
}

function getPosition() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) return reject(new Error('No geolocation'))
    navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 5000 })
  })
}

// ── Init ──────────────────────────────────────────────────────────────────────
onMounted(() => loadHistory())
</script>
