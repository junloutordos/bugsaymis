<script setup>
import { computed, ref } from 'vue'
import AppBadge from '@/Components/AppBadge.vue'
import { ClockIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  days: { type: Array, default: () => [] },
  labs: { type: Array, default: () => [] },
  bookings: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
  canBook: { type: Boolean, default: false },
  movingBookingId: { type: Number, default: null },
})

const emit = defineEmits(['move', 'cancel', 'request'])

const DEFAULT_START = 7 * 60
const DEFAULT_END = 17 * 60
const SCALE = 0.9
const GUTTER = 62

const draggedBooking = ref(null)
const dropTarget = ref(null)

function timeToMinutes(time) {
  if (!time) return 0
  const [hour, minute] = time.split(':').map(Number)
  return (hour * 60) + minute
}

const calendarStart = computed(() => {
  if (!props.bookings.length) return DEFAULT_START
  const earliest = Math.min(...props.bookings.map(booking => timeToMinutes(booking.start_time)))
  return Math.min(DEFAULT_START, Math.floor(earliest / 60) * 60)
})

const calendarEnd = computed(() => {
  if (!props.bookings.length) return DEFAULT_END
  const latest = Math.max(...props.bookings.map(booking => timeToMinutes(booking.end_time)))
  return Math.max(DEFAULT_END, Math.ceil(latest / 60) * 60)
})

const calendarHeight = computed(() => (calendarEnd.value - calendarStart.value) * SCALE)

const hours = computed(() => {
  const values = []
  for (let minute = calendarStart.value; minute < calendarEnd.value; minute += 60) {
    values.push(minute)
  }
  return values
})

function bookingsFor(date, roomId) {
  return props.bookings.filter(booking => booking.date === date && booking.room_id === roomId)
}

function packOverlaps(bookings) {
  const items = bookings
    .map(booking => ({
      id: booking.id,
      start: timeToMinutes(booking.start_time),
      end: timeToMinutes(booking.end_time),
    }))
    .sort((a, b) => a.start - b.start || a.end - b.end)

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
    for (const lab of props.labs) {
      const roomId = lab.room.id
      const packed = packOverlaps(bookingsFor(day.date, roomId))
      for (const [bookingId, layout] of packed) result.set(bookingId, layout)
    }
  }
  return result
})

function bookingStyle(booking) {
  const start = Math.max(calendarStart.value, timeToMinutes(booking.start_time))
  const end = Math.min(calendarEnd.value, timeToMinutes(booking.end_time))
  const layout = packedBookings.value.get(booking.id) ?? { lane: 0, totalLanes: 1 }
  const width = 100 / layout.totalLanes

  return {
    top: `${(start - calendarStart.value) * SCALE}px`,
    height: `${Math.max((end - start) * SCALE, 30)}px`,
    left: `calc(${layout.lane * width}% + 2px)`,
    width: `calc(${width}% - 4px)`,
  }
}

function hourTop(minute) {
  return (minute - calendarStart.value) * SCALE
}

function formatHour(minute) {
  const hour = Math.floor(minute / 60)
  return `${hour % 12 || 12}${hour >= 12 ? 'PM' : 'AM'}`
}

function formatTime(time) {
  const [hour, minute] = time.split(':').map(Number)
  return new Intl.DateTimeFormat('en-PH', { hour: 'numeric', minute: '2-digit' })
    .format(new Date(2000, 0, 1, hour, minute))
}

function statusColor(status) {
  return { confirmed: 'indigo', approved: 'green', pending: 'orange' }[status] ?? 'slate'
}

function bookingClasses(booking) {
  if (booking.booking_type === 'priority_class') return 'border-indigo-200 bg-indigo-50 text-indigo-950'
  if (booking.status === 'approved') return 'border-emerald-200 bg-emerald-50 text-emerald-950'
  return 'border-amber-200 bg-amber-50 text-amber-950'
}

function overlaps(first, second) {
  return timeToMinutes(first.start_time) < timeToMinutes(second.end_time)
    && timeToMinutes(first.end_time) > timeToMinutes(second.start_time)
}

function localConflict(booking, date, roomId) {
  return props.bookings.find(candidate =>
    candidate.id !== booking.id
    && candidate.date === date
    && candidate.room_id === roomId
    && ['confirmed', 'approved'].includes(candidate.status)
    && overlaps(booking, candidate)
  )
}

function onDragStart(event, booking) {
  if (!props.canManage || !booking.can_move) {
    event.preventDefault()
    return
  }

  draggedBooking.value = booking
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', `computer-lab-booking:${booking.id}`)
}

function onDragOver(event, date, roomId) {
  const booking = draggedBooking.value
  if (!booking || booking.date !== date) return

  event.preventDefault()
  const conflict = localConflict(booking, date, roomId)
  dropTarget.value = {
    date,
    roomId,
    hasConflict: !!conflict,
    message: conflict ? `Occupied by ${conflict.title}` : 'Move here',
  }
  event.dataTransfer.dropEffect = conflict ? 'none' : 'move'
}

function onDrop(event, date, roomId) {
  event.preventDefault()
  const booking = draggedBooking.value
  const target = dropTarget.value
  clearDrag()

  if (!booking || !target || target.hasConflict || booking.date !== date || booking.room_id === roomId) return
  emit('move', { booking, roomId })
}

function clearDrag() {
  draggedBooking.value = null
  dropTarget.value = null
}

function isDropTarget(date, roomId) {
  return dropTarget.value?.date === date && dropTarget.value?.roomId === roomId
}
</script>

<template>
  <div class="space-y-4">
    <section v-for="day in days" :key="day.date" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
      <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-2.5">
        <div>
          <h3 class="text-sm font-semibold text-slate-800">{{ day.name }}</h3>
          <p class="text-xs text-slate-400">{{ day.label }}</p>
        </div>
        <span v-if="canManage && labs.length > 1" class="text-xs text-slate-400">Drag a confirmed or approved block to another laboratory</span>
      </div>

      <div class="overflow-x-auto">
        <div class="min-w-[760px]">
          <div class="flex border-b border-slate-200">
            <div class="shrink-0 border-r border-slate-200" :style="{ width: `${GUTTER}px` }" />
            <div v-for="lab in labs" :key="lab.room.id" class="min-w-44 flex-1 border-r border-slate-200 px-2 py-2 text-center last:border-r-0">
              <p class="truncate text-xs font-semibold uppercase tracking-wide text-slate-600">{{ lab.room.name }}</p>
              <button v-if="canBook" type="button" class="mt-0.5 text-[10px] font-medium text-indigo-600 hover:underline"
                @click="emit('request', { date: day.date, roomId: lab.room.id })">Request use</button>
            </div>
          </div>

          <div class="flex" :style="{ height: `${calendarHeight}px` }">
            <div class="relative shrink-0 border-r border-slate-200" :style="{ width: `${GUTTER}px` }">
              <div v-for="minute in hours" :key="minute" class="absolute right-2 -translate-y-2 text-[11px] font-medium text-slate-400"
                :style="{ top: `${hourTop(minute)}px` }">
                {{ formatHour(minute) }}
              </div>
            </div>

            <div class="relative flex flex-1">
              <div v-for="minute in hours" :key="`hour-${minute}`" class="pointer-events-none absolute inset-x-0 z-0 border-t border-slate-100"
                :style="{ top: `${hourTop(minute)}px` }" />
              <div v-for="minute in hours.filter(value => value + 30 < calendarEnd)" :key="`half-${minute}`"
                class="pointer-events-none absolute inset-x-0 z-0 border-t border-dashed border-slate-100/70"
                :style="{ top: `${hourTop(minute + 30)}px` }" />

              <div v-for="lab in labs" :key="lab.room.id"
                class="relative min-w-44 flex-1 border-r border-slate-200 transition-colors last:border-r-0"
                :class="isDropTarget(day.date, lab.room.id)
                  ? dropTarget.hasConflict ? 'bg-red-50/80 ring-2 ring-inset ring-red-300' : 'bg-emerald-50/80 ring-2 ring-inset ring-emerald-300'
                  : ''"
                @dragover="onDragOver($event, day.date, lab.room.id)"
                @drop="onDrop($event, day.date, lab.room.id)">

                <div v-if="isDropTarget(day.date, lab.room.id)" class="pointer-events-none absolute inset-x-2 top-2 z-30 rounded-md border px-2 py-1 text-center text-xs font-semibold"
                  :class="dropTarget.hasConflict ? 'border-red-300 bg-red-100 text-red-700' : 'border-emerald-300 bg-emerald-100 text-emerald-700'">
                  {{ dropTarget.message }}
                </div>

                <article v-for="booking in bookingsFor(day.date, lab.room.id)" :key="booking.id"
                  class="absolute z-10 overflow-hidden rounded-md border shadow-sm transition hover:z-20 hover:shadow-md"
                  :class="[
                    bookingClasses(booking),
                    canManage && booking.can_move ? 'cursor-grab active:cursor-grabbing' : 'cursor-default',
                    movingBookingId === booking.id ? 'opacity-50' : '',
                  ]"
                  :style="bookingStyle(booking)"
                  :draggable="canManage && booking.can_move"
                  :title="`${booking.title}\n${formatTime(booking.start_time)}–${formatTime(booking.end_time)}${booking.faculty ? `\n${booking.faculty}` : ''}`"
                  @dragstart="onDragStart($event, booking)"
                  @dragend="clearDrag">
                  <div class="flex h-full flex-col gap-0.5 overflow-hidden px-1.5 py-1 text-[10px] leading-tight">
                    <div class="flex items-start justify-between gap-1">
                      <p class="truncate font-bold">{{ booking.title }}</p>
                      <AppBadge :color="statusColor(booking.status)" class="shrink-0">{{ booking.status }}</AppBadge>
                    </div>
                    <p class="flex items-center gap-1 truncate opacity-70">
                      <ClockIcon class="h-3 w-3 shrink-0" /> {{ formatTime(booking.start_time) }}–{{ formatTime(booking.end_time) }}
                    </p>
                    <p v-if="booking.faculty" class="truncate opacity-70">{{ booking.faculty }}</p>
                    <button v-if="booking.can_cancel" type="button" class="self-start text-red-600 hover:underline"
                      @mousedown.stop @click.stop="emit('cancel', booking)">Cancel</button>
                  </div>
                </article>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
