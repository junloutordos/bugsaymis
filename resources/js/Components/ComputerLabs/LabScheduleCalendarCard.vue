<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import { subjectColorStyle } from '@/Utils/subjectColor.js'
import {
  ArrowsPointingInIcon,
  ArrowsPointingOutIcon,
  ArrowsRightLeftIcon,
  ClockIcon,
  PrinterIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  lab: { type: Object, required: true },
  days: { type: Array, default: () => [] },
  bookings: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
  canBook: { type: Boolean, default: false },
  movingBookingId: { type: Number, default: null },
  draggedBooking: { type: Object, default: null },
  dropTarget: { type: Object, default: null },
})

const emit = defineEmits(['cancel', 'transfer', 'drag-start', 'drag-end', 'drag-over', 'drop', 'request'])

const CALENDAR_START = 7 * 60
const CALENDAR_END = 17 * 60
const SCALE = 1.8
const GUTTER = 62
const MIN_SELECTION = 30
const SELECTION_STEP = 15

const rootEl = ref(null)
const isExpanded = ref(false)
const createDraft = ref(null)
let selectionColumn = null

function timeToMinutes(time) {
  if (!time) return 0
  const [hour, minute] = time.split(':').map(Number)
  return (hour * 60) + minute
}

function minutesToTime(minutes) {
  const bounded = Math.max(CALENDAR_START, Math.min(CALENDAR_END, minutes))
  return `${String(Math.floor(bounded / 60)).padStart(2, '0')}:${String(bounded % 60).padStart(2, '0')}`
}

function roundToStep(minutes) {
  return Math.round(minutes / SELECTION_STEP) * SELECTION_STEP
}

const calendarHeight = (CALENDAR_END - CALENDAR_START) * SCALE

const hours = computed(() => {
  const values = []
  for (let minute = CALENDAR_START; minute < CALENDAR_END; minute += 60) values.push(minute)
  return values
})

function bookingsFor(date) {
  return props.bookings.filter(booking => booking.date === date)
}

function packOverlaps(bookings) {
  const items = bookings
    .map(booking => ({
      id: booking.id,
      start: timeToMinutes(booking.start_time),
      end: timeToMinutes(booking.end_time),
    }))
    .sort((first, second) => first.start - second.start || first.end - second.end)

  const result = new Map()
  let laneEnds = []
  let cluster = []
  let maxLane = 0
  let clusterEnd = -Infinity

  const flush = () => {
    for (const id of cluster) result.get(id).totalLanes = maxLane + 1
    laneEnds = []
    cluster = []
    maxLane = 0
  }

  for (const item of items) {
    if (item.start >= clusterEnd) {
      flush()
      clusterEnd = -Infinity
    }

    let lane = 0
    while (lane < laneEnds.length && laneEnds[lane] > item.start) lane++
    laneEnds[lane] = item.end
    result.set(item.id, { lane, totalLanes: 1 })
    cluster.push(item.id)
    maxLane = Math.max(maxLane, lane)
    clusterEnd = Math.max(clusterEnd, item.end)
  }

  flush()
  return result
}

const packedBookings = computed(() => {
  const result = new Map()
  for (const day of props.days) {
    const packed = packOverlaps(bookingsFor(day.date))
    for (const [bookingId, layout] of packed) result.set(bookingId, layout)
  }
  return result
})

function bookingStyle(booking) {
  const start = Math.max(CALENDAR_START, timeToMinutes(booking.start_time))
  const end = Math.min(CALENDAR_END, timeToMinutes(booking.end_time))
  const layout = packedBookings.value.get(booking.id) ?? { lane: 0, totalLanes: 1 }
  const width = 100 / layout.totalLanes

  return {
    top: `${(start - CALENDAR_START) * SCALE}px`,
    height: `${Math.max((end - start) * SCALE, 56)}px`,
    left: `calc(${layout.lane * width}% + 2px)`,
    width: `calc(${width}% - 4px)`,
    ...bookingColorStyle(booking),
  }
}

function hourTop(minute) {
  return (minute - CALENDAR_START) * SCALE
}

function formatHour(minute) {
  const hour = Math.floor(minute / 60)
  return `${hour % 12 || 12}${hour >= 12 ? 'PM' : 'AM'}`
}

function formatTime(time) {
  if (!time) return ''
  const [hour, minute] = time.split(':').map(Number)
  return new Intl.DateTimeFormat('en-PH', { hour: 'numeric', minute: '2-digit' })
    .format(new Date(2000, 0, 1, hour, minute))
}

function bookingClasses(booking) {
  if (booking.status === 'pending') return 'border-dashed'
  return 'border-solid'
}

function durationMinutes(booking) {
  return timeToMinutes(booking.end_time) - timeToMinutes(booking.start_time)
}

/**
 * Duration tiers scale font-size/line-height/layout down as the booking
 * cell shrinks, so short (e.g. 30-minute) bookings still show a legible
 * title + time instead of clipped/overflowing text. Mirrors the tiering
 * convention used by SchedulePrintSheet.vue's durationTierClass(). Tiers,
 * from smallest: xs (≤15min, title only), sm (16–25min, title + time, no
 * faculty/status), short (26–30min, compact stack), default (>30min, full
 * layout at base size).
 */
function durationTierClass(booking) {
  const minutes = durationMinutes(booking)
  if (minutes <= 15) return 'lab-booking-xs'
  if (minutes <= 25) return 'lab-booking-sm'
  if (minutes <= 30) return 'lab-booking-short'
  return ''
}

function subjectKey(booking) {
  return booking.subject || booking.title
}

function bookingColorStyle(booking) {
  return subjectColorStyle(subjectKey(booking))
}

function statusClasses(status) {
  return {
    confirmed: 'bg-indigo-100 text-indigo-700',
    approved: 'bg-emerald-100 text-emerald-700',
    pending: 'bg-amber-100 text-amber-700',
  }[status] ?? 'bg-slate-100 text-slate-600'
}

function isDropTarget(date) {
  return props.dropTarget?.date === date && props.dropTarget?.roomId === props.lab.room.id
}

function onDragStart(event, booking) {
  if (!props.canManage || !booking.can_move) {
    event.preventDefault()
    return
  }

  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', `computer-lab-booking:${booking.id}`)
  emit('drag-start', booking)
}

function onDragOver(event, date) {
  if (!props.draggedBooking || props.draggedBooking.date !== date) return
  event.preventDefault()
  emit('drag-over', { event, date, roomId: props.lab.room.id })
}

function onDrop(event, date) {
  event.preventDefault()
  emit('drop', { date, roomId: props.lab.room.id })
}

function minuteFromPointer(event) {
  const rect = selectionColumn.getBoundingClientRect()
  const offset = Math.max(0, Math.min(rect.height, event.clientY - rect.top))
  return Math.max(CALENDAR_START, Math.min(CALENDAR_END, roundToStep(CALENDAR_START + (offset / SCALE))))
}

function startSelection(event, day) {
  if (!props.canBook || event.button !== 0 || event.target.closest('[data-lab-booking]')) return

  selectionColumn = event.currentTarget
  const startMin = Math.min(minuteFromPointer(event), CALENDAR_END - MIN_SELECTION)
  createDraft.value = {
    date: day.date,
    startMin,
    endMin: Math.min(startMin + 60, CALENDAR_END),
  }

  window.addEventListener('mousemove', updateSelection)
  window.addEventListener('mouseup', finishSelection, { once: true })
  event.preventDefault()
}

function updateSelection(event) {
  if (!createDraft.value || !selectionColumn) return
  const pointerMin = minuteFromPointer(event)
  createDraft.value.endMin = Math.max(
    createDraft.value.startMin + MIN_SELECTION,
    Math.min(pointerMin, CALENDAR_END),
  )
}

function finishSelection() {
  window.removeEventListener('mousemove', updateSelection)

  if (createDraft.value) {
    emit('request', {
      date: createDraft.value.date,
      roomId: props.lab.room.id,
      startTime: minutesToTime(createDraft.value.startMin),
      endTime: minutesToTime(createDraft.value.endMin),
    })
  }

  createDraft.value = null
  selectionColumn = null
}

function draftStyle() {
  if (!createDraft.value) return {}
  return {
    top: `${(createDraft.value.startMin - CALENDAR_START) * SCALE}px`,
    height: `${(createDraft.value.endMin - createDraft.value.startMin) * SCALE}px`,
  }
}

function toggleExpand() {
  isExpanded.value = !isExpanded.value
}

function onKeydown(event) {
  if (event.key === 'Escape' && isExpanded.value) toggleExpand()
}

function printCard() {
  document.body.classList.add('print-single-lab-calendar')
  rootEl.value?.classList.add('lab-calendar-print-target')
  requestAnimationFrame(() => window.print())
}

function handleAfterPrint() {
  document.body.classList.remove('print-single-lab-calendar')
  rootEl.value?.classList.remove('lab-calendar-print-target')
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
  window.addEventListener('afterprint', handleAfterPrint)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  window.removeEventListener('afterprint', handleAfterPrint)
  window.removeEventListener('mousemove', updateSelection)
  window.removeEventListener('mouseup', finishSelection)
})
</script>

<template>
  <section ref="rootEl"
    class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70"
    :class="isExpanded ? 'fixed inset-3 z-50 flex flex-col' : ''">
    <header class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-100 bg-gradient-to-r from-indigo-50 to-slate-50 px-4 py-3">
      <div class="min-w-0">
        <div class="flex items-center gap-2">
          <span class="rounded-full bg-indigo-500 px-2.5 py-0.5 text-xs font-bold text-white">Com Lab</span>
          <h2 class="truncate text-sm font-semibold text-slate-800">{{ lab.room.name }}</h2>
          <span class="text-xs text-slate-400">· {{ bookings.length }} booking(s)</span>
        </div>
        <p class="mt-0.5 text-xs text-slate-500">
          Capacity {{ lab.room.capacity ?? 30 }} · {{ lab.enrolled }}/{{ lab.total }} monitored units
        </p>
      </div>
      <div class="flex shrink-0 items-center gap-1 print:hidden">
        <AppIconButton label="Print" size="sm" @click="printCard">
          <PrinterIcon class="h-4 w-4" />
        </AppIconButton>
        <AppIconButton :label="isExpanded ? 'Collapse' : 'Expand'" size="sm" @click="toggleExpand">
          <ArrowsPointingInIcon v-if="isExpanded" class="h-4 w-4" />
          <ArrowsPointingOutIcon v-else class="h-4 w-4" />
        </AppIconButton>
      </div>
    </header>

    <div class="overflow-x-auto print:overflow-visible" :class="isExpanded ? 'flex-1' : ''">
      <div class="min-w-[820px] print:min-w-0">
        <div class="flex border-b border-slate-100">
          <div class="shrink-0 border-r border-slate-100" :style="{ width: `${GUTTER}px` }" />
          <div v-for="day in days" :key="day.date" class="flex-1 border-l border-slate-100 py-2 text-center first:border-l-0">
            <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ day.name.slice(0, 3) }}</span>
            <span class="block text-xs text-slate-400">{{ day.label }}</span>
          </div>
        </div>

        <div class="flex" :style="{ height: `${calendarHeight}px` }">
          <div class="relative shrink-0 border-r border-slate-100" :style="{ width: `${GUTTER}px` }">
            <div v-for="minute in hours" :key="minute" class="absolute right-2 -translate-y-2 text-[11px] font-medium text-slate-400"
              :style="{ top: `${hourTop(minute)}px` }">
              {{ formatHour(minute) }}
            </div>
          </div>

          <div class="relative flex flex-1">
            <div v-for="minute in hours" :key="`hour-${minute}`" class="pointer-events-none absolute inset-x-0 z-0 border-t border-slate-100"
              :style="{ top: `${hourTop(minute)}px` }" />
            <div v-for="minute in hours.filter(value => value + 30 < CALENDAR_END)" :key="`half-${minute}`"
              class="pointer-events-none absolute inset-x-0 z-0 border-t border-dashed border-slate-100/70"
              :style="{ top: `${hourTop(minute + 30)}px` }" />

            <div v-for="day in days" :key="day.date"
              class="relative flex-1 overflow-hidden border-l border-slate-100 transition-colors first:border-l-0"
              :class="[
                canBook ? 'cursor-crosshair' : '',
                isDropTarget(day.date)
                  ? dropTarget.hasConflict
                    ? 'bg-red-50/80 ring-2 ring-inset ring-red-300'
                    : 'bg-emerald-50/80 ring-2 ring-inset ring-emerald-300'
                  : '',
              ]"
              @mousedown="startSelection($event, day)"
              @dragover="onDragOver($event, day.date)"
              @drop="onDrop($event, day.date)">

              <div v-if="createDraft?.date === day.date" class="pointer-events-none absolute inset-x-0.5 z-30 rounded-md border-2 border-indigo-400 bg-indigo-100/80 px-1 text-center"
                :style="draftStyle()">
                <span class="text-xs font-semibold tabular-nums text-indigo-700">
                  {{ formatTime(minutesToTime(createDraft.startMin)) }}–{{ formatTime(minutesToTime(createDraft.endMin)) }}
                </span>
              </div>

              <div v-if="isDropTarget(day.date)" class="pointer-events-none absolute inset-x-2 top-2 z-30 rounded-md border px-2 py-1 text-center text-xs font-semibold"
                :class="dropTarget.hasConflict ? 'border-red-300 bg-red-100 text-red-700' : 'border-emerald-300 bg-emerald-100 text-emerald-700'">
                {{ dropTarget.message }}
              </div>

              <article v-for="booking in bookingsFor(day.date)" :key="booking.id" data-lab-booking
                class="absolute z-10 overflow-hidden rounded-md border shadow-sm transition hover:z-20 hover:shadow-md"
                :class="[
                  bookingClasses(booking),
                  durationTierClass(booking),
                  canManage && booking.can_move ? 'cursor-grab active:cursor-grabbing' : 'cursor-default',
                  movingBookingId === booking.id ? 'opacity-50' : '',
                ]"
                :style="bookingStyle(booking)"
                :draggable="canManage && booking.can_move"
                :title="`${booking.title}\n${formatTime(booking.start_time)}–${formatTime(booking.end_time)}${booking.faculty ? `\n${booking.faculty}` : ''}`"
                @dragstart="onDragStart($event, booking)"
                @dragend="emit('drag-end')">
                <div class="lab-booking-content flex h-full flex-col gap-0.5 overflow-hidden px-1.5 py-1 text-[10px] leading-tight">
                  <div class="lab-booking-title-row flex items-start justify-between gap-1">
                    <p class="truncate font-bold">{{ booking.title }}</p>
                    <span class="lab-booking-status shrink-0 rounded px-1 py-0.5 text-[9px] font-semibold" :class="statusClasses(booking.status)">
                      {{ booking.status }}
                    </span>
                  </div>
                  <p class="lab-booking-time flex items-center gap-1 truncate opacity-70">
                    <ClockIcon class="lab-booking-clock-icon h-3 w-3 shrink-0" /> {{ formatTime(booking.start_time) }}–{{ formatTime(booking.end_time) }}
                  </p>
                  <p v-if="booking.faculty" class="lab-booking-faculty truncate opacity-70">{{ booking.faculty }}</p>
                  <div v-if="booking.can_cancel || (canManage && booking.can_move)" class="lab-booking-actions mt-auto flex items-center gap-2 pt-0.5">
                    <button v-if="canManage && booking.can_move" type="button" class="flex items-center gap-0.5 text-indigo-600 hover:underline"
                      @mousedown.stop @click.stop="emit('transfer', booking)">
                      <ArrowsRightLeftIcon class="h-3 w-3" /> Transfer
                    </button>
                    <button v-if="booking.can_cancel" type="button" class="text-red-600 hover:underline"
                      @mousedown.stop @click.stop="emit('cancel', booking)">Cancel</button>
                  </div>
                </div>
              </article>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div v-if="isExpanded" class="fixed inset-0 z-40 bg-slate-900/40 print:hidden" @click="toggleExpand" />
</template>

<style>
/*
 * Duration tiers — shrink font/line-height and progressively drop the
 * least-important line as the booking's real time-slot gets shorter, so
 * 30-minute (and shorter) bookings stay legible instead of clipping their
 * stacked title/status/time/faculty lines into an unreadable ~54px cell.
 * Mirrors SchedulePrintSheet.vue's duration-tier convention.
 */
.lab-booking-short .lab-booking-content {
  gap: 0;
  padding-top: 2px;
  padding-bottom: 2px;
}
.lab-booking-short .lab-booking-faculty {
  display: none;
}

.lab-booking-sm .lab-booking-content {
  flex-direction: row;
  align-items: center;
  gap: 4px;
  padding-top: 1px;
  padding-bottom: 1px;
  font-size: 9px;
}
.lab-booking-sm .lab-booking-title-row {
  min-width: 0;
  flex: 1 1 auto;
}
.lab-booking-sm .lab-booking-time {
  flex: 0 0 auto;
  width: auto;
}
.lab-booking-sm .lab-booking-status,
.lab-booking-sm .lab-booking-faculty,
.lab-booking-sm .lab-booking-actions {
  display: none;
}

.lab-booking-xs .lab-booking-content {
  flex-direction: row;
  align-items: center;
  padding-top: 1px;
  padding-bottom: 1px;
  font-size: 8.5px;
}
.lab-booking-xs .lab-booking-title-row {
  min-width: 0;
  flex: 1 1 auto;
}
.lab-booking-xs .lab-booking-status,
.lab-booking-xs .lab-booking-time,
.lab-booking-xs .lab-booking-faculty,
.lab-booking-xs .lab-booking-actions {
  display: none;
}

@media print {
  body.print-single-lab-calendar * {
    visibility: hidden;
  }
  body.print-single-lab-calendar .lab-calendar-print-target,
  body.print-single-lab-calendar .lab-calendar-print-target * {
    visibility: visible;
  }
  body.print-single-lab-calendar .lab-calendar-print-target {
    position: fixed;
    inset: 0;
    z-index: 9999;
    border-radius: 0;
    box-shadow: none;
  }
  @page {
    size: landscape;
    margin: 0.4in;
  }
}
</style>
