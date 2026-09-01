<template>
  <div class="space-y-4">
    <div v-for="grade in gradesWithEntries" :key="grade.grade_level" class="rounded-xl border border-slate-200 bg-white p-4">
      <h3 class="mb-3 text-sm font-semibold text-slate-700">Grade {{ grade.grade_level }}</h3>
      <div class="flex gap-3 overflow-x-auto pb-2">
        <div v-for="section in grade.sections" :key="section.id" class="w-56 shrink-0">
          <div class="mb-1 text-center text-xs font-semibold text-slate-500">{{ section.name }}</div>
          <div
            class="relative rounded-lg border border-slate-100 bg-slate-50"
            :style="{ height: `${totalMinutes * PX_PER_MINUTE}px` }"
            :data-section-id="section.id"
            @dragover.prevent="onDragOver($event, section)"
            @drop.prevent="onDrop($event, section)"
          >
            <div
              v-for="band in section.bands"
              :key="`${band.type}-${band.start}`"
              class="absolute inset-x-0 rounded bg-slate-200/60 px-1.5 py-0.5 text-[10px] text-slate-500"
              :style="bandStyle(band)"
            >
              {{ band.label }}
            </div>
            <div
              v-for="entry in section.entries"
              :key="entry.id"
              :draggable="true"
              class="absolute inset-x-1 cursor-grab rounded-md border px-2 py-1 text-xs shadow-sm active:cursor-grabbing"
              :class="entryClass(entry)"
              :style="entryStyle(entry)"
              :data-entry-id="entry.id"
              @dragstart="onDragStart($event, entry, section)"
              @click="$emit('edit-entry', entry)"
            >
              <div class="flex items-center justify-between gap-1">
                <span class="truncate font-medium">{{ entry.subject?.name ?? entry.title ?? '—' }}</span>
                <span v-if="entry.subject?.is_stem" class="shrink-0 rounded-full bg-purple-100 px-1.5 text-[9px] font-semibold text-purple-700">STEM</span>
              </div>
              <div class="text-[10px] text-slate-500">{{ entry.start_time }}–{{ entry.end_time }} · {{ entry.classroom?.name ?? '—' }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  preview: { type: Object, required: true },
})

defineEmits(['edit-entry', 'move-entry'])

const PX_PER_MINUTE = 1.5

const gradesWithEntries = computed(() => (props.preview.grades ?? []).filter(grade => grade.sections?.length))

const calendarStartMinutes = computed(() => toMinutes(props.preview.calendar_start ?? '07:30'))
const calendarEndMinutes = computed(() => toMinutes(props.preview.calendar_end ?? '17:00'))
const totalMinutes = computed(() => calendarEndMinutes.value - calendarStartMinutes.value)

function toMinutes(hhmm) {
  const [h, m] = hhmm.split(':').map(Number)
  return h * 60 + m
}

function offsetStyle(startHHMM, endHHMM) {
  const top = (toMinutes(startHHMM) - calendarStartMinutes.value) * PX_PER_MINUTE
  const height = Math.max(16, (toMinutes(endHHMM) - toMinutes(startHHMM)) * PX_PER_MINUTE)
  return { top: `${top}px`, height: `${height}px` }
}

function entryStyle(entry) {
  return offsetStyle(entry.start_time, entry.end_time)
}

function bandStyle(band) {
  return offsetStyle(band.start, band.end)
}

function entryClass(entry) {
  return entry.manually_adjusted
    ? 'border-indigo-300 bg-indigo-50 text-indigo-800'
    : 'border-slate-200 bg-white text-slate-700'
}

function onDragStart(event, entry, section) {
  event.dataTransfer.setData('text/plain', String(entry.id))
}
function onDragOver(event, section) {}
function onDrop(event, section) {}

defineExpose({ toMinutes, calendarStartMinutes })
</script>
