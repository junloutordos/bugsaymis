<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'
import LabScheduleCalendarCard from '@/Components/ComputerLabs/LabScheduleCalendarCard.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
import {
  ArrowLeftIcon, ArrowPathIcon, ArrowRightIcon, ArrowsRightLeftIcon, CalendarDaysIcon,
  CheckIcon, ComputerDesktopIcon, ExclamationTriangleIcon,
  PlusIcon, PrinterIcon, SignalIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  labs: { type: Array, default: () => [] },
  terms: { type: Array, default: () => [] },
  selectedTermId: { type: Number, default: null },
  weekStart: { type: String, default: '' },
  days: { type: Array, default: () => [] },
  bookings: { type: Array, default: () => [] },
  unassigned: { type: Array, default: () => [] },
  pendingBookings: { type: Array, default: () => [] },
  capabilities: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
  scheduleApproval: { type: Object, default: null },
  hasPin: { type: Boolean, default: false },
  signatureUri: { type: String, default: null },
})

const filters = reactive({
  term_id: props.selectedTermId,
  week_start: props.weekStart,
  lab_id: props.filters.lab_id ? Number(props.filters.lab_id) : '',
})

const visibleLabs = computed(() => {
  if (!filters.lab_id) return props.labs
  return props.labs.filter(lab => lab.room.id === Number(filters.lab_id))
})

function applyFilters() {
  router.get(route('computer-labs.index'), filters, { preserveState: true, preserveScroll: true })
}

function moveWeek(days) {
  const date = new Date(`${filters.week_start}T00:00:00`)
  date.setDate(date.getDate() + days)
  filters.week_start = date.toISOString().slice(0, 10)
  applyFilters()
}

function formatTime(time) {
  if (!time) return ''
  const [hour, minute] = time.split(':').map(Number)
  return new Intl.DateTimeFormat('en-PH', { hour: 'numeric', minute: '2-digit' })
    .format(new Date(2000, 0, 1, hour, minute))
}

const bookingModal = ref(false)
const bookingForm = useForm({
  room_id: props.labs[0]?.room?.id ?? null,
  academic_term_id: props.selectedTermId,
  booking_date: props.weekStart,
  start_time: '08:00',
  end_time: '09:00',
  title: '',
  purpose: '',
})

function openBooking(date = props.weekStart, roomId = null, startTime = '08:00', endTime = '09:00') {
  bookingForm.clearErrors()
  bookingForm.academic_term_id = props.selectedTermId
  bookingForm.booking_date = date
  bookingForm.room_id = roomId ?? visibleLabs.value[0]?.room?.id ?? props.labs[0]?.room?.id ?? null
  bookingForm.start_time = startTime
  bookingForm.end_time = endTime
  bookingModal.value = true
}

function submitBooking() {
  bookingForm.post(route('computer-labs.bookings.store'), {
    preserveScroll: true,
    onSuccess: () => {
      bookingModal.value = false
      bookingForm.reset('title', 'purpose')
    },
  })
}

const actionForm = useForm({ academic_term_id: props.selectedTermId, reason: '' })

async function synchronize() {
  if (!await confirmAction('Synchronize priority subjects with the official class schedule now?')) return
  actionForm.academic_term_id = filters.term_id
  actionForm.post(route('computer-labs.synchronize'), { preserveScroll: true })
}

const approvalLocked = computed(() => ['pending_approval', 'approved'].includes(props.scheduleApproval?.status))

const submitApprovalModal = ref(false)
const submitApprovalLoading = ref(false)

function openSubmitApproval() {
  submitApprovalModal.value = true
}

function confirmSubmitApproval(pin) {
  submitApprovalLoading.value = true
  router.post(route('computer-labs.schedule-approvals.submit', filters.term_id), { pin }, {
    preserveScroll: true,
    onSuccess: () => { submitApprovalModal.value = false },
    onFinish: () => { submitApprovalLoading.value = false },
  })
}

function openPrint(labId = null) {
  const query = { term_id: filters.term_id, week_start: filters.week_start }
  const resolvedLabId = labId ?? filters.lab_id
  if (resolvedLabId) query.lab_id = resolvedLabId
  window.open(route('computer-labs.print', query), '_blank')
}

function approve(booking) {
  useForm({}).post(route('computer-labs.bookings.approve', booking.id), { preserveScroll: true })
}

async function reject(booking) {
  if (!await confirmAction(`Reject the booking request “${booking.title}”?`)) return
  useForm({ reason: 'Booking request rejected by the laboratory scheduler.' })
    .post(route('computer-labs.bookings.reject', booking.id), { preserveScroll: true })
}

async function cancel(booking) {
  if (!await confirmAction(`Cancel the booking “${booking.title}”?`)) return
  useForm({}).post(route('computer-labs.bookings.cancel', booking.id), { preserveScroll: true })
}

const movingBookingId = ref(null)
const draggedBooking = ref(null)
const dropTarget = ref(null)

function timeToMinutes(time) {
  if (!time) return 0
  const [hour, minute] = time.split(':').map(Number)
  return (hour * 60) + minute
}

function overlaps(first, second) {
  return timeToMinutes(first.start_time) < timeToMinutes(second.end_time)
    && timeToMinutes(first.end_time) > timeToMinutes(second.start_time)
}

function startBookingDrag(booking) {
  draggedBooking.value = booking
}

function previewBookingMove({ event, date, roomId }) {
  const booking = draggedBooking.value
  if (!booking || booking.date !== date) return

  const conflict = props.bookings.find(candidate =>
    candidate.id !== booking.id
    && candidate.date === date
    && candidate.room_id === roomId
    && ['confirmed', 'approved'].includes(candidate.status)
    && overlaps(booking, candidate)
  )

  dropTarget.value = {
    date,
    roomId,
    hasConflict: !!conflict,
    message: conflict ? `Occupied by ${conflict.title}` : 'Move here',
  }
  event.dataTransfer.dropEffect = conflict ? 'none' : 'move'
}

function clearBookingDrag() {
  draggedBooking.value = null
  dropTarget.value = null
}

function dropBooking({ date, roomId }) {
  const booking = draggedBooking.value
  const target = dropTarget.value
  clearBookingDrag()

  if (!booking || !target || target.hasConflict || booking.date !== date || booking.room_id === roomId) return
  moveBooking({ booking, roomId })
}

function moveBooking({ booking, roomId }) {
  if (movingBookingId.value) return
  movingBookingId.value = booking.id

  router.patch(route('computer-labs.bookings.move', booking.id), { room_id: roomId }, {
    preserveScroll: true,
    onFinish: () => { movingBookingId.value = null },
  })
}

const transferModal = ref(false)
const transferBooking = ref(null)
const transferRoomId = ref(null)
const transferError = ref(null)
const transferSwapOffer = ref(null)
const swapPending = ref(false)

const transferLabOptions = computed(() => {
  if (!transferBooking.value) return []
  return props.labs.filter(lab => lab.room.id !== transferBooking.value.room_id)
})

const transferConflict = computed(() => {
  const booking = transferBooking.value
  if (!booking || !transferRoomId.value) return null

  return props.bookings.find(candidate =>
    candidate.id !== booking.id
    && candidate.date === booking.date
    && candidate.room_id === Number(transferRoomId.value)
    && ['confirmed', 'approved'].includes(candidate.status)
    && overlaps(booking, candidate)
  ) ?? null
})

watch(transferRoomId, () => {
  transferError.value = null
  transferSwapOffer.value = null
})

function openTransfer(booking) {
  transferBooking.value = booking
  transferRoomId.value = transferLabOptions.value[0]?.room?.id ?? null
  transferError.value = null
  transferSwapOffer.value = null
  transferModal.value = true
}

function closeTransfer() {
  transferModal.value = false
  transferBooking.value = null
  transferRoomId.value = null
  transferError.value = null
  transferSwapOffer.value = null
}

function submitTransfer() {
  if (!transferBooking.value || !transferRoomId.value || movingBookingId.value) return

  const booking = transferBooking.value
  movingBookingId.value = booking.id
  swapPending.value = false
  transferError.value = null
  transferSwapOffer.value = null

  router.patch(route('computer-labs.bookings.move', booking.id), { room_id: Number(transferRoomId.value) }, {
    preserveScroll: true,
    onSuccess: () => closeTransfer(),
    onError: (errors) => {
      transferError.value = errors.booking ?? null
      transferSwapOffer.value = errors.can_swap === '1' ? { title: errors.conflict_title } : null
    },
    onFinish: () => { movingBookingId.value = null },
  })
}

function submitTransferSwap() {
  if (!transferBooking.value || !transferRoomId.value || movingBookingId.value) return

  const booking = transferBooking.value
  movingBookingId.value = booking.id
  swapPending.value = true

  router.patch(route('computer-labs.bookings.move', booking.id), { room_id: Number(transferRoomId.value), swap: true }, {
    preserveScroll: true,
    onSuccess: () => closeTransfer(),
    onError: (errors) => {
      transferError.value = errors.booking ?? null
      transferSwapOffer.value = null
    },
    onFinish: () => { movingBookingId.value = null; swapPending.value = false },
  })
}
</script>

<template>
  <Head title="Computer Laboratories" />
  <AdminLayout title="Computer Laboratories">
    <div class="space-y-5">
      <AppPageHeader title="Computer Laboratory Scheduling" subtitle="Priority computer subjects are synchronized from the official timetable. Remaining periods are available for other booking requests.">
        <template #actions>
          <AppButton v-if="capabilities.canManage && selectedTermId" variant="secondary" :loading="actionForm.processing" @click="synchronize">
            <ArrowPathIcon class="h-4 w-4" /> Sync Class Schedules
          </AppButton>
          <AppButton v-if="labs.length && selectedTermId" variant="secondary" @click="openPrint()">
            <PrinterIcon class="h-4 w-4" /> Print Schedule
          </AppButton>
          <AppButton v-if="capabilities.canSubmitApproval && selectedTermId && !approvalLocked" @click="openSubmitApproval">
            <CheckIcon class="h-4 w-4" /> Submit for Approval
          </AppButton>
          <AppButton v-if="capabilities.canBook && labs.length && selectedTermId" @click="openBooking()">
            <PlusIcon class="h-4 w-4" /> Request Lab Use
          </AppButton>
        </template>
      </AppPageHeader>

      <div v-if="scheduleApproval" class="rounded-lg border px-4 py-3 text-sm"
        :class="{
          'border-amber-200 bg-amber-50 text-amber-800': scheduleApproval.status === 'pending_approval',
          'border-emerald-200 bg-emerald-50 text-emerald-800': scheduleApproval.status === 'approved',
          'border-red-200 bg-red-50 text-red-800': scheduleApproval.status === 'returned',
        }">
        <p v-if="scheduleApproval.status === 'pending_approval'">
          Submitted by {{ scheduleApproval.submitted_by }} on {{ scheduleApproval.submitted_at }} — awaiting CID Chief approval.
        </p>
        <p v-else-if="scheduleApproval.status === 'approved'">
          Approved by {{ scheduleApproval.approved_by }} on {{ scheduleApproval.approved_at }}. Prepared by {{ scheduleApproval.submitted_by }} on {{ scheduleApproval.submitted_at }}.
        </p>
        <template v-else-if="scheduleApproval.status === 'returned'">
          <p>Returned for revision. Reason: {{ scheduleApproval.return_remarks }}</p>
        </template>
      </div>

      <div v-if="$page.props.flash?.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.booking" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ $page.props.errors.booking }}
      </div>

      <div v-if="!labs.length" class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
        <EmptyState title="No computer laboratories yet" subtitle="Set a room's type in Data Management → Rooms." />
      </div>

      <template v-else>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <component :is="capabilities.canViewEquipment ? Link : 'div'" v-for="lab in labs" :key="lab.room.id"
            :href="capabilities.canViewEquipment ? route('computer-labs.show', lab.room.id) : null"
            class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition"
            :class="capabilities.canViewEquipment ? 'hover:border-indigo-300 hover:shadow-md' : ''">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2 min-w-0">
                <ComputerDesktopIcon class="h-5 w-5 shrink-0 text-indigo-500" />
                <span class="truncate text-sm font-semibold text-slate-800">{{ lab.room.name }}</span>
              </div>
              <AppBadge v-if="lab.critical" color="red">{{ lab.critical }} critical</AppBadge>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-xs text-slate-500">
              <SignalIcon class="h-3.5 w-3.5" /> {{ lab.enrolled }}/{{ lab.total }} monitored units
            </div>
          </component>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex flex-wrap items-center gap-3">
            <div class="w-72">
              <AppSelect v-model.number="filters.term_id" :show-blank="false" @update:model-value="applyFilters">
                <option v-for="term in terms" :key="term.id" :value="term.id">{{ term.label }}{{ term.is_current ? ' (current)' : '' }}</option>
              </AppSelect>
            </div>
            <div class="w-52">
              <AppSelect v-model="filters.lab_id" :show-blank="false" @update:model-value="applyFilters">
                <option value="">All laboratories</option>
                <option v-for="lab in labs" :key="lab.room.id" :value="lab.room.id">{{ lab.room.name }}</option>
              </AppSelect>
            </div>
            <div class="ml-auto flex items-center gap-2">
              <AppButton variant="secondary" size="sm" @click="moveWeek(-7)"><ArrowLeftIcon class="h-4 w-4" /> Previous</AppButton>
              <span class="min-w-28 text-center text-sm font-medium text-slate-700">Week of {{ days[0]?.label }}</span>
              <AppButton variant="secondary" size="sm" @click="moveWeek(7)">Next <ArrowRightIcon class="h-4 w-4" /></AppButton>
            </div>
          </div>
          <div class="mt-3 flex flex-wrap gap-4 text-xs text-slate-500">
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full border-2 border-slate-400"></span> Color = subject/activity</span>
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full border-2 border-dashed border-slate-400"></span> Dashed border = pending request</span>
          </div>
        </div>

        <div class="space-y-6">
          <LabScheduleCalendarCard
            v-for="lab in visibleLabs"
            :key="lab.room.id"
            :lab="lab"
            :days="days"
            :bookings="bookings.filter(booking => booking.room_id === lab.room.id)"
            :can-manage="capabilities.canManage"
            :can-book="capabilities.canBook"
            :moving-booking-id="movingBookingId"
            :dragged-booking="draggedBooking"
            :drop-target="dropTarget"
            @drag-start="startBookingDrag"
            @drag-end="clearBookingDrag"
            @drag-over="previewBookingMove"
            @drop="dropBooking"
            @cancel="cancel"
            @transfer="openTransfer"
            @request="({ date, roomId, startTime, endTime }) => openBooking(date, roomId, startTime, endTime)"
            @print="openPrint"
          />
        </div>

        <div v-if="unassigned.length" class="rounded-xl border border-red-200 bg-red-50 p-4">
          <div class="flex items-center gap-2 text-sm font-semibold text-red-800">
            <ExclamationTriangleIcon class="h-5 w-5" /> Priority classes without a laboratory ({{ unassigned.length }})
          </div>
          <p class="mt-1 text-xs text-red-600">These classes remain placed in the official timetable. Only their laboratory reservation is unresolved.</p>
          <div class="mt-3 grid gap-2 md:grid-cols-2">
            <div v-for="booking in unassigned" :key="booking.id" class="rounded-lg border border-red-200 bg-white p-3 text-sm">
              <p class="font-medium text-slate-800">{{ booking.title }}</p>
              <p class="mt-1 text-xs text-slate-500">{{ booking.day_of_week }} · {{ formatTime(booking.start_time) }}–{{ formatTime(booking.end_time) }} · {{ booking.faculty }}</p>
              <p class="mt-1 text-xs text-red-600">{{ booking.conflict_note }}</p>
            </div>
          </div>
        </div>

        <div v-if="capabilities.canManage && pendingBookings.length" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
          <h2 class="text-sm font-semibold text-amber-900">Pending laboratory requests ({{ pendingBookings.length }})</h2>
          <div class="mt-3 grid gap-3 lg:grid-cols-2">
            <div v-for="booking in pendingBookings" :key="booking.id" class="rounded-lg border border-amber-200 bg-white p-3">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="text-sm font-semibold text-slate-800">{{ booking.title }}</p>
                  <p class="mt-1 text-xs text-slate-500">{{ booking.room_name }} · {{ booking.date }} · {{ formatTime(booking.start_time) }}–{{ formatTime(booking.end_time) }}</p>
                  <p class="mt-1 text-xs text-slate-500">Requested by {{ booking.requester }}</p>
                  <p v-if="booking.conflict_note" class="mt-1 text-xs text-red-600">{{ booking.conflict_note }}</p>
                </div>
                <div class="flex gap-1">
                  <AppButton size="sm" variant="success" @click="approve(booking)"><CheckIcon class="h-4 w-4" /> Approve</AppButton>
                  <AppButton size="sm" variant="danger" @click="reject(booking)"><XMarkIcon class="h-4 w-4" /> Reject</AppButton>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <AppModal :show="bookingModal" title="Request Computer Laboratory Use" subtitle="Priority classes are protected. The request will be submitted only if the selected laboratory is currently available." @close="bookingModal = false">
      <form class="space-y-4" @submit.prevent="submitBooking">
        <AppSelect v-model.number="bookingForm.room_id" label="Computer Laboratory" :show-blank="false" :error="bookingForm.errors.room_id">
          <option v-for="lab in labs" :key="lab.room.id" :value="lab.room.id">{{ lab.room.name }} (capacity {{ lab.room.capacity ?? 30 }})</option>
        </AppSelect>
        <AppInput v-model="bookingForm.booking_date" type="date" label="Date" required :error="bookingForm.errors.booking_date" />
        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model="bookingForm.start_time" type="time" label="Start Time" required :error="bookingForm.errors.start_time" />
          <AppInput v-model="bookingForm.end_time" type="time" label="End Time" required :error="bookingForm.errors.end_time" />
        </div>
        <AppInput v-model="bookingForm.title" label="Activity or Subject" required :error="bookingForm.errors.title" />
        <AppTextarea v-model="bookingForm.purpose" label="Purpose" :rows="3" required :error="bookingForm.errors.purpose" />
        <div v-if="bookingForm.errors.booking" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ bookingForm.errors.booking }}</div>
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
          <AppButton type="button" variant="secondary" @click="bookingModal = false">Cancel</AppButton>
          <AppButton type="submit" :loading="bookingForm.processing"><CalendarDaysIcon class="h-4 w-4" /> Submit Request</AppButton>
        </div>
      </form>
    </AppModal>

    <AppModal :show="transferModal" title="Transfer Laboratory Schedule" subtitle="Move this schedule to another computer laboratory. The target must be vacant for the same day and time." @close="closeTransfer">
      <form v-if="transferBooking" class="space-y-4" @submit.prevent="submitTransfer">
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
          <p class="font-medium text-slate-800">{{ transferBooking.title }}</p>
          <p class="mt-1 text-xs text-slate-500">
            Currently in {{ transferBooking.room_name }} · {{ transferBooking.date || transferBooking.day_of_week }} ·
            {{ formatTime(transferBooking.start_time) }}–{{ formatTime(transferBooking.end_time) }}
          </p>
        </div>

        <AppSelect v-model.number="transferRoomId" label="Transfer to Laboratory" :show-blank="false">
          <option v-for="lab in transferLabOptions" :key="lab.room.id" :value="lab.room.id">
            {{ lab.room.name }} (capacity {{ lab.room.capacity ?? 30 }})
          </option>
        </AppSelect>

        <div v-if="transferRoomId && !transferError" class="rounded-lg border px-3 py-2 text-sm"
          :class="transferConflict ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'">
          <span v-if="transferConflict">Occupied by "{{ transferConflict.title }}" during this period.</span>
          <span v-else>This laboratory is vacant for the requested day and time.</span>
        </div>

        <div v-if="transferError" class="space-y-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          <p>{{ transferError }}</p>
          <AppButton v-if="transferSwapOffer" type="button" variant="secondary" :loading="swapPending" @click="submitTransferSwap">
            <ArrowsRightLeftIcon class="h-4 w-4" /> Swap Rooms Instead
          </AppButton>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
          <AppButton type="button" variant="secondary" @click="closeTransfer">Cancel</AppButton>
          <AppButton type="submit" :disabled="!transferRoomId" :loading="movingBookingId === transferBooking.id && !swapPending">
            <ArrowRightIcon class="h-4 w-4" /> Transfer
          </AppButton>
        </div>
      </form>
    </AppModal>

    <DigitalSignaturePin
      :show="submitApprovalModal"
      :has-pin="hasPin"
      :signature-uri="signatureUri"
      :loading="submitApprovalLoading"
      confirm-label="Submit for Approval"
      @confirm="confirmSubmitApproval"
      @cancel="submitApprovalModal = false"
    />
  </AdminLayout>
</template>
