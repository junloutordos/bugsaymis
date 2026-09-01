<template>
  <div class="space-y-4">
    <div v-for="grade in gradesWithEntries" :key="grade.grade_level" class="rounded-xl border border-slate-200 bg-white p-4">
      <h3 class="mb-3 text-sm font-semibold text-slate-700">Grade {{ grade.grade_level }}</h3>
      <div class="flex gap-3 overflow-x-auto pb-2">
        <div v-for="section in grade.sections" :key="section.id" class="w-56 shrink-0">
          <div class="mb-1 text-center text-xs font-semibold text-slate-500">{{ section.name }}</div>
          <div
            class="relative rounded-lg border bg-slate-50"
            :class="conflictSectionId === section.id ? 'border-rose-300 ring-2 ring-rose-200' : 'border-slate-100'"
            :style="{ height: `${totalMinutes * PX_PER_MINUTE}px` }"
            @dragover.prevent="onDragOver($event, section)"
            @dragleave="conflictSectionId = null"
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
              @click="openOverride(entry)"
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

  <AppModal :show="showOverrideModal" title="Adjust class time" size="sm" @close="showOverrideModal = false">
    <div v-if="editingEntry" class="space-y-4">
      <p class="text-sm text-slate-600">
        {{ editingEntry.subject?.name ?? editingEntry.title }} — currently {{ editingEntry.start_time }}–{{ editingEntry.end_time }}
      </p>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">New start time</label>
          <input v-model="overrideForm.override_start_time" type="time"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">New end time</label>
          <input v-model="overrideForm.override_end_time" type="time"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
      </div>
      <p v-if="overrideError" class="text-xs text-rose-600">{{ overrideError }}</p>
    </div>

    <template #footer>
      <div class="flex w-full items-center justify-between gap-2">
        <AppButton v-if="editingEntry?.manually_adjusted" variant="ghost" class="text-rose-600" @click="removeOverride">Remove override</AppButton>
        <div class="ml-auto flex gap-2">
          <AppButton variant="ghost" @click="showOverrideModal = false">Cancel</AppButton>
          <AppButton :loading="savingOverride" @click="saveOverride">Save</AppButton>
        </div>
      </div>
    </template>
  </AppModal>
</template>

<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import AppModal from '@/Components/AppModal.vue'
import AppButton from '@/Components/AppButton.vue'

const props = defineProps({
  preview: { type: Object, required: true },
  adjustment: { type: Object, required: true },
})

const emit = defineEmits(['update:preview'])

const PX_PER_MINUTE = 1.5
const SNAP_MINUTES = 5

const dragging = ref(null) // { entry, durationMinutes, section }
const conflictSectionId = ref(null)

const showOverrideModal = ref(false)
const editingEntry = ref(null)
const overrideForm = ref({ override_start_time: '', override_end_time: '' })
const overrideError = ref('')
const savingOverride = ref(false)

const gradesWithEntries = computed(() => (props.preview.grades ?? []).filter(grade => grade.sections?.length))

const calendarStartMinutes = computed(() => toMinutes(props.preview.calendar_start ?? '07:30'))
const calendarEndMinutes = computed(() => toMinutes(props.preview.calendar_end ?? '17:00'))
const totalMinutes = computed(() => calendarEndMinutes.value - calendarStartMinutes.value)

function toMinutes(hhmm) {
  const [h, m] = hhmm.split(':').map(Number)
  return h * 60 + m
}

function fromMinutes(total) {
  const h = Math.floor(total / 60)
  const m = total % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
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

function allEntries() {
  return gradesWithEntries.value.flatMap(grade => grade.sections).flatMap(section => section.entries)
}

// Live client-side pre-check only — purely advisory (drives the drop
// target's highlight color). The server call on drop is authoritative;
// this never blocks a drop, it only colors it.
function wouldConflict(entry, proposedStartMinutes, proposedEndMinutes) {
  return allEntries().some(other => {
    if (other.id === entry.id) return false
    const sameRoom = other.classroom?.id && entry.classroom?.id && other.classroom.id === entry.classroom.id
    const sameFaculty = other.faculty?.id && entry.faculty?.id && other.faculty.id === entry.faculty.id
    if (!sameRoom && !sameFaculty) return false
    const otherStart = toMinutes(other.start_time)
    const otherEnd = toMinutes(other.end_time)
    return proposedStartMinutes < otherEnd && otherStart < proposedEndMinutes
  })
}

function onDragStart(event, entry, section) {
  const durationMinutes = toMinutes(entry.end_time) - toMinutes(entry.start_time)
  dragging.value = { entry, durationMinutes, section }
  event.dataTransfer.setData('text/plain', String(entry.id))
  event.dataTransfer.effectAllowed = 'move'
}

function proposedStartMinutes(event, columnEl) {
  const rect = columnEl.getBoundingClientRect()
  const offsetY = event.clientY - rect.top
  const rawMinutes = calendarStartMinutes.value + offsetY / PX_PER_MINUTE
  return Math.round(rawMinutes / SNAP_MINUTES) * SNAP_MINUTES
}

function onDragOver(event, section) {
  if (!dragging.value) return
  const start = proposedStartMinutes(event, event.currentTarget)
  const end = start + dragging.value.durationMinutes
  conflictSectionId.value = wouldConflict(dragging.value.entry, start, end) ? section.id : null
}

async function onDrop(event, section) {
  if (!dragging.value) return
  const { entry, durationMinutes } = dragging.value
  const start = proposedStartMinutes(event, event.currentTarget)
  const end = start + durationMinutes
  dragging.value = null
  conflictSectionId.value = null

  const { data } = await axios.post(
    route('faculty-loading.schedules.day-adjustments.overrides.store', props.adjustment.id),
    {
      class_schedule_id: entry.id,
      override_start_time: fromMinutes(start),
      override_end_time: fromMinutes(end),
    },
  )
  emit('update:preview', data)
}

function openOverride(entry) {
  editingEntry.value = entry
  overrideForm.value = { override_start_time: entry.start_time, override_end_time: entry.end_time }
  overrideError.value = ''
  showOverrideModal.value = true
}

async function saveOverride() {
  savingOverride.value = true
  overrideError.value = ''
  try {
    const { data } = await axios.post(route('faculty-loading.schedules.day-adjustments.overrides.store', props.adjustment.id), {
      class_schedule_id: editingEntry.value.id,
      override_start_time: overrideForm.value.override_start_time,
      override_end_time: overrideForm.value.override_end_time,
    })
    emit('update:preview', data)
    showOverrideModal.value = false
  } catch (error) {
    const errors = error.response?.data?.errors ?? {}
    overrideError.value = errors.override_end_time?.[0] ?? errors.override_start_time?.[0] ?? error.response?.data?.message ?? 'Unable to save this adjustment.'
  } finally {
    savingOverride.value = false
  }
}

async function removeOverride() {
  const { data } = await axios.delete(route('faculty-loading.schedules.day-adjustments.overrides.destroy', [props.adjustment.id, editingEntry.value.id]))
  emit('update:preview', data)
  showOverrideModal.value = false
}
</script>
