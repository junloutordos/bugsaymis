<script setup>
import { computed } from 'vue'
import { PlusIcon, ClockIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  wat: { type: Object, required: true },
  plottingRecords: { type: Array, default: () => [] },
})

const emit = defineEmits(['plot'])

const START_MIN = 7 * 60
const END_MIN = 17 * 60
const SCALE = 0.85
const HEIGHT = (END_MIN - START_MIN) * SCALE
const HOURS = Array.from({ length: 11 }, (_, index) => index + 7)

function timeMinutes(value) {
  const [hours, minutes] = String(value ?? '').split(':').map(Number)
  return (hours * 60) + (minutes || 0)
}

function position(start, end) {
  const startMin = Math.max(START_MIN, timeMinutes(start))
  const endMin = Math.min(END_MIN, timeMinutes(end))
  return {
    top: `${(startMin - START_MIN) * SCALE}px`,
    height: `${Math.max(28, (endMin - startMin) * SCALE)}px`,
  }
}

function itemTimes(item) {
  if (!item.time_label?.includes('–')) return null
  const [start, end] = item.time_label.split('–')
  return { start, end }
}

function dayName(date) {
  return new Date(`${date}T00:00:00`).toLocaleDateString('en-PH', { weekday: 'long' })
}

function dayLabel(date) {
  return new Date(`${date}T00:00:00`).toLocaleDateString('en-PH', {
    weekday: 'short', month: 'short', day: 'numeric',
  })
}

function periodsFor(day) {
  const weekday = dayName(day.date)
  return props.plottingRecords.flatMap(record =>
    (record.periods ?? [])
      .filter(period => period.day === weekday)
      .map(period => ({ ...period, record }))
  )
}

function timedItems(day) {
  return day.items.filter(item => itemTimes(item))
}

function unscheduledItems(day) {
  return day.items.filter(item => !itemTimes(item))
}

function itemPosition(item, day) {
  const times = itemTimes(item)
  const sameTime = timedItems(day).filter(other => other.time_label === item.time_label)
  const index = sameTime.findIndex(other => other.id === item.id)
  const width = 100 / Math.max(1, sameTime.length)

  return {
    ...position(times.start, times.end),
    left: `calc(${index * width}% + 3px)`,
    width: `calc(${width}% - 6px)`,
  }
}

function availabilityPosition(period) {
  return position(period.start_time, period.end_time)
}

function plottingRecordFor(item) {
  return props.plottingRecords.find(record => record.id === item.class_record_id) ?? null
}

function plotBeside(item, day) {
  const record = plottingRecordFor(item)
  if (record && canOfferDay(day)) {
    emit('plot', { date: day.date, record })
  }
}

function hourTop(hour) {
  return ((hour * 60) - START_MIN) * SCALE
}

function canOfferDay(day) {
  return day.graded_count < props.wat.limits.daily_graded
}

const hasPlottingRecords = computed(() => props.plottingRecords.length > 0)
</script>

<template>
  <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-gradient-to-r from-indigo-50 to-slate-50 px-4 py-3">
      <div>
        <h3 class="text-sm font-semibold text-slate-800">Weekly Assessment Calendar</h3>
        <p class="text-xs text-slate-500">Assessment blocks follow the section's class schedule. Click an available class period to plot.</p>
      </div>
      <span v-if="!hasPlottingRecords" class="text-xs text-slate-400">View only</span>
    </div>

    <div class="overflow-x-auto">
      <div class="min-w-[900px]">
        <div class="flex border-b border-slate-100">
          <div class="w-16 shrink-0 border-r border-slate-100"></div>
          <div v-for="day in wat.days" :key="day.date" class="flex-1 border-l border-slate-100 px-2 py-2 text-center first:border-l-0">
            <p class="text-xs font-semibold text-slate-700">{{ dayLabel(day.date) }}</p>
            <p :class="['text-[10px] font-medium', day.over_daily && !day.is_exam_window ? 'text-red-600' : 'text-slate-400']">
              {{ day.graded_count }}/{{ wat.limits.daily_graded }} graded ·
              {{ day.major_count }}/{{ wat.limits.daily_major }} major
            </p>
          </div>
        </div>

        <div class="flex">
          <div class="relative w-16 shrink-0 border-r border-slate-100" :style="{ height: `${HEIGHT}px` }">
            <div v-for="hour in HOURS" :key="hour"
              class="absolute right-2 -translate-y-2 text-[10px] font-medium text-slate-400"
              :style="{ top: `${hourTop(hour)}px` }">
              {{ new Date(2000, 0, 1, hour).toLocaleTimeString('en-PH', { hour: 'numeric' }) }}
            </div>
          </div>

          <div v-for="day in wat.days" :key="day.date"
            class="relative flex-1 border-l border-slate-100 first:border-l-0"
            :style="{ height: `${HEIGHT}px` }">
            <div v-for="hour in HOURS" :key="hour"
              class="pointer-events-none absolute inset-x-0 border-t border-slate-100"
              :style="{ top: `${hourTop(hour)}px` }"></div>

            <button v-for="period in periodsFor(day)" :key="`${period.record.id}-${period.day}-${period.start_time}`"
              type="button"
              :disabled="!canOfferDay(day)"
              :style="availabilityPosition(period)"
              :title="canOfferDay(day) ? `Plot ${period.record.subject_name}` : 'Daily graded-assessment limit reached'"
              class="absolute inset-x-1 z-[1] overflow-hidden rounded-md border border-dashed border-indigo-200 bg-indigo-50/30 px-1 text-left transition hover:border-indigo-400 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-50"
              @click="emit('plot', { date: day.date, record: period.record })">
              <span class="flex items-center gap-1 truncate text-[10px] font-medium text-indigo-500">
                <PlusIcon class="h-3 w-3 shrink-0" />
                {{ period.record.subject_name }}
              </span>
            </button>

            <div v-for="item in timedItems(day)" :key="item.id"
              :style="itemPosition(item, day)"
              :class="['absolute z-10 overflow-hidden rounded-md border px-1.5 py-1 shadow-sm',
                plottingRecordFor(item) && canOfferDay(day) ? 'cursor-pointer hover:ring-2 hover:ring-indigo-300' : '',
                item.is_major ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-indigo-200 bg-indigo-50 text-indigo-800']"
              @click="plotBeside(item, day)">
              <p class="truncate text-[10px] font-bold">{{ item.subject_name }}</p>
              <p class="truncate text-[10px]">{{ item.title }}</p>
              <p class="truncate text-[9px] opacity-70">{{ item.type_label }}</p>
            </div>
          </div>
        </div>

        <div v-if="wat.days.some(day => unscheduledItems(day).length)" class="border-t border-slate-100 px-4 py-3">
          <div class="mb-2 flex items-center gap-1.5 text-xs font-semibold text-slate-500">
            <ClockIcon class="h-4 w-4" /> Assessments without a matching schedule time
          </div>
          <div class="grid grid-cols-5 gap-2">
            <div v-for="day in wat.days" :key="day.date" class="space-y-1">
              <div v-for="item in unscheduledItems(day)" :key="item.id" class="rounded border border-slate-200 bg-slate-50 px-2 py-1">
                <p class="truncate text-[10px] font-semibold text-slate-700">{{ item.subject_name }}</p>
                <p class="truncate text-[10px] text-slate-500">{{ item.title }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
