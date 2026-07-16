<template>
  <Head :title="documentTitle" />

  <div class="schedule-print-sheet">
    <img class="schedule-print-header print-asset" src="/images/report_header.jpeg" alt="" />

    <main class="schedule-print-body">
      <div class="schedule-print-heading">
        <h1>{{ documentTitle }}</h1>
        <div class="schedule-print-meta">
          <div>
            <strong>{{ scheduleType === 'section' ? 'Section:' : 'Faculty:' }}</strong>
            {{ ownerLabel }}
          </div>
          <div><strong>School Year:</strong> {{ term.school_year ?? '-' }}</div>
          <div><strong>Academic Term:</strong> {{ term.label }}</div>
        </div>
        <div v-if="scheduleType === 'section' && (owner.adviser || owner.classroom)" class="schedule-print-submeta">
          <span v-if="owner.adviser"><strong>Adviser:</strong> {{ owner.adviser }}</span>
          <span v-if="owner.classroom"><strong>Home Room:</strong> {{ owner.classroom }}</span>
        </div>
        <div v-else-if="scheduleType === 'faculty' && owner.position" class="schedule-print-submeta">
          <span><strong>Position:</strong> {{ owner.position }}</span>
        </div>
      </div>

      <div class="schedule-print-calendar">
        <div class="schedule-print-days">
          <div class="schedule-print-corner">TIME</div>
          <div v-for="day in WEEKDAYS" :key="day" class="schedule-print-day">
            {{ day }}
          </div>
        </div>

        <div class="schedule-print-timeline">
          <div class="schedule-print-time-axis">
            <span
              v-for="minute in HOUR_MARKS"
              :key="minute"
              :style="positionStyle(minute)"
            >
              {{ formatHour(minute) }}
            </span>
          </div>

          <div class="schedule-print-day-grid">
            <div
              v-for="minute in GRID_MARKS"
              :key="'line-' + minute"
              class="schedule-print-gridline"
              :class="{ 'schedule-print-gridline-half': minute % 60 !== 0 }"
              :style="positionStyle(minute)"
            />

            <div v-for="day in WEEKDAYS" :key="day" class="schedule-print-column">
              <template v-if="dayConfigs[day]">
                <div
                  v-for="blocked in dayConfigs[day].blocked ?? []"
                  :key="`${day}-${blocked.label}-${blocked.start}`"
                  class="schedule-print-blocked"
                  :style="rangeStyle(blocked.start, blocked.end)"
                >
                  <span>{{ blocked.label }}</span>
                </div>
                <div
                  v-if="timeToMinutes(dayConfigs[day].end) <= NOON"
                  class="schedule-print-no-classes"
                  :style="rangeStyle(dayConfigs[day].end, minutesToTime(CAL_END))"
                >
                  <span>No Classes</span>
                </div>
              </template>

              <div
                v-for="entry in schedulesByDay[day] ?? []"
                :key="entry.id"
                class="schedule-print-event"
                :class="{
                  'schedule-print-event-nonteaching': entry.entry_type === 'non_teaching',
                  'schedule-print-event-tentative': entry.status === 'tentative',
                  'schedule-print-event-short': durationMinutes(entry) <= 30,
                }"
                :style="[rangeStyle(entry.start_time, entry.end_time), eventColorStyle(entry)]"
              >
                <div class="schedule-print-event-title">{{ eventTitle(entry) }}</div>
                <div class="schedule-print-event-detail">{{ eventDetail(entry) }}</div>
                <div v-if="durationMinutes(entry) >= 45" class="schedule-print-event-time">
                  {{ formatTime(entry.start_time) }}-{{ formatTime(entry.end_time) }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <img class="schedule-print-footer print-asset" src="/images/report_footer.jpeg" alt="" />
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  scheduleType: { type: String, required: true },
  owner: { type: Object, required: true },
  term: { type: Object, required: true },
  schedules: { type: Array, default: () => [] },
  dayConfigs: { type: Object, default: () => ({}) },
})

const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']
const CAL_START = 7 * 60
const CAL_END = 16 * 60 + 30
const NOON = 12 * 60
const HOUR_MARKS = Array.from({ length: 10 }, (_, index) => CAL_START + index * 60)
const GRID_MARKS = Array.from(
  { length: Math.floor((CAL_END - CAL_START) / 30) + 1 },
  (_, index) => CAL_START + index * 30,
)

// Single-color print: every teaching event uses one blue scheme (per-subject
// palette dropped so B/W and color printers both get a clean, uniform sheet)
const TEACHING_COLOR    = { backgroundColor: '#dbeafe', borderColor: '#60a5fa', color: '#1e3a8a' }
const NONTEACHING_COLOR = { backgroundColor: '#f1f5f9', borderColor: '#64748b', color: '#1e293b' }

const documentTitle = computed(() => (
  props.scheduleType === 'section' ? 'CLASS SCHEDULE' : 'INDIVIDUAL FACULTY SCHEDULE'
))

const ownerLabel = computed(() => {
  if (props.scheduleType === 'section') {
    return `Grade ${props.owner.grade_level} - ${props.owner.name}`
  }
  return props.owner.name?.toUpperCase() ?? ''
})

const schedulesByDay = computed(() => {
  const grouped = Object.fromEntries(WEEKDAYS.map(day => [day, []]))
  for (const schedule of props.schedules) {
    if (grouped[schedule.day_of_week]) grouped[schedule.day_of_week].push(schedule)
  }
  return grouped
})

function timeToMinutes(time) {
  if (!time) return 0
  const [hours, minutes] = time.split(':').map(Number)
  return hours * 60 + minutes
}

function minutesToTime(minutes) {
  return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`
}

function positionStyle(minutes) {
  return { top: `${((minutes - CAL_START) / (CAL_END - CAL_START)) * 100}%` }
}

function rangeStyle(start, end) {
  const startMinutes = Math.max(CAL_START, timeToMinutes(start))
  const endMinutes = Math.min(CAL_END, timeToMinutes(end))
  const total = CAL_END - CAL_START

  return {
    top: `${((startMinutes - CAL_START) / total) * 100}%`,
    height: `${(Math.max(0, endMinutes - startMinutes) / total) * 100}%`,
  }
}

function durationMinutes(entry) {
  return timeToMinutes(entry.end_time) - timeToMinutes(entry.start_time)
}

function formatHour(minutes) {
  const hour = Math.floor(minutes / 60)
  if (hour === 12) return '12 PM'
  return hour < 12 ? `${hour} AM` : `${hour - 12} PM`
}

function formatTime(time) {
  const minutes = timeToMinutes(time)
  const hour = Math.floor(minutes / 60)
  const minute = String(minutes % 60).padStart(2, '0')
  const displayHour = hour % 12 || 12
  return `${displayHour}:${minute}${hour < 12 ? 'AM' : 'PM'}`
}

function lastName(name) {
  const parts = name?.trim().split(/\s+/) ?? []
  return parts.at(-1) ?? 'TBA'
}

function eventTitle(entry) {
  if (entry.entry_type === 'non_teaching') return entry.title || 'Non-teaching'
  return `${entry.subject?.code ?? 'TBA'}${entry.session_type === 'ilp' ? ' (ILP)' : ''}`
}

function eventDetail(entry) {
  const room = entry.classroom?.code ?? entry.classroom?.name

  if (entry.entry_type === 'non_teaching') {
    const assignment = props.scheduleType === 'section'
      ? (entry.faculty?.name ? lastName(entry.faculty.name) : '')
      : (entry.section_name ?? '')
    return [assignment, room].filter(Boolean).join(' - ')
  }

  const assignment = props.scheduleType === 'section'
    ? lastName(entry.faculty?.name)
    : `G${entry.grade_level} ${entry.section_name}`

  return [assignment, room].filter(Boolean).join(' - ')
}

function eventColorStyle(entry) {
  return entry.entry_type === 'non_teaching' ? NONTEACHING_COLOR : TEACHING_COLOR
}

onMounted(async () => {
  await nextTick()
  const images = [...document.querySelectorAll('.print-asset')]
  await Promise.all(images.map(image => image.complete ? Promise.resolve() : new Promise(resolve => {
    image.addEventListener('load', resolve, { once: true })
    image.addEventListener('error', resolve, { once: true })
  })))
  await document.fonts?.ready
  setTimeout(() => window.print(), 100)
})
</script>

<style>
* {
  box-sizing: border-box;
}

html,
body {
  margin: 0;
  padding: 0;
  background: #d1d5db;
}

.schedule-print-sheet {
  width: 297mm;
  height: 210mm;
  margin: 0 auto;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  background: #fff;
  color: #000;
  font-family: Arial, Helvetica, sans-serif;
}

.schedule-print-header,
.schedule-print-footer {
  display: block;
  width: auto;
  height: auto;
  flex: 0 0 auto;
}

.schedule-print-header {
  max-width: 170mm;
  margin: 2mm auto 0;
}

.schedule-print-footer {
  max-width: 140mm;
  margin: 0 auto 2mm;
}

.schedule-print-body {
  min-height: 0;
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  padding: 1mm 10mm 0;
}

.schedule-print-heading {
  flex: 0 0 auto;
  margin-bottom: 1.5mm;
  font-size: 7.5pt;
}

.schedule-print-heading h1 {
  margin: 0 0 1mm;
  text-align: center;
  font-size: 12pt;
  letter-spacing: 0;
}

.schedule-print-meta {
  display: grid;
  grid-template-columns: 1.35fr 0.8fr 1.35fr;
  gap: 4mm;
  line-height: 1.25;
}

.schedule-print-submeta {
  display: flex;
  gap: 12mm;
  margin-top: 0.6mm;
  line-height: 1.2;
}

.schedule-print-calendar {
  min-height: 0;
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  border: 0.35mm solid #111827;
  overflow: hidden;
}

.schedule-print-days {
  flex: 0 0 7mm;
  display: grid;
  grid-template-columns: 12mm repeat(5, 1fr);
  border-bottom: 0.25mm solid #111827;
  background: #e5e7eb;
}

.schedule-print-corner,
.schedule-print-day {
  display: flex;
  align-items: center;
  justify-content: center;
  border-left: 0.2mm solid #9ca3af;
  font-size: 7pt;
  font-weight: 700;
}

.schedule-print-corner {
  border-left: 0;
}

.schedule-print-timeline {
  min-height: 0;
  flex: 1 1 auto;
  display: flex;
}

.schedule-print-time-axis {
  position: relative;
  flex: 0 0 12mm;
  border-right: 0.25mm solid #111827;
}

.schedule-print-time-axis span {
  position: absolute;
  right: 1.2mm;
  z-index: 3;
  transform: translateY(-50%);
  padding: 0 0.5mm;
  background: #fff;
  font-size: 5.8pt;
  font-weight: 600;
  white-space: nowrap;
}

.schedule-print-day-grid {
  position: relative;
  min-width: 0;
  flex: 1 1 auto;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
}

.schedule-print-gridline {
  position: absolute;
  left: 0;
  right: 0;
  z-index: 0;
  border-top: 0.18mm solid #9ca3af;
}

.schedule-print-gridline-half {
  border-top: 0.12mm dashed #d1d5db;
}

.schedule-print-column {
  position: relative;
  min-width: 0;
  border-left: 0.2mm solid #9ca3af;
  overflow: hidden;
}

.schedule-print-column:first-of-type {
  border-left: 0;
}

.schedule-print-blocked,
.schedule-print-no-classes {
  position: absolute;
  left: 0;
  right: 0;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border-top: 0.12mm solid #cbd5e1;
  border-bottom: 0.12mm solid #cbd5e1;
  background: #e5e7eb;
  color: #64748b;
  font-size: 5.5pt;
  font-weight: 600;
  text-align: center;
}

.schedule-print-no-classes {
  background: #f8fafc;
  color: #94a3b8;
}

.schedule-print-event {
  position: absolute;
  left: 0.7mm;
  right: 0.7mm;
  z-index: 2;
  min-height: 2.4mm;
  overflow: hidden;
  padding: 0.5mm 0.7mm;
  border: 0.25mm solid;
  border-radius: 0.8mm;
  font-size: 5.8pt;
  line-height: 1.1;
}

.schedule-print-event-nonteaching {
  border-style: dashed;
}

.schedule-print-event-tentative {
  border-right-width: 1mm;
  border-right-color: #1e40af !important;
}

.schedule-print-event-title {
  overflow: hidden;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.schedule-print-event-detail,
.schedule-print-event-time {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.schedule-print-event-detail {
  margin-top: 0.2mm;
  opacity: 0.9;
}

.schedule-print-event-time {
  margin-top: 0.2mm;
  opacity: 0.75;
}

.schedule-print-event-short {
  display: flex;
  align-items: center;
  gap: 0.8mm;
  padding-top: 0.2mm;
  padding-bottom: 0.2mm;
}

.schedule-print-event-short .schedule-print-event-title,
.schedule-print-event-short .schedule-print-event-detail {
  min-width: 0;
}

@page {
  size: A4 landscape;
  margin: 0;
}

@media print {
  html,
  body {
    width: 297mm;
    height: 210mm;
    margin: 0;
    padding: 0;
    overflow: hidden;
    background: #fff;
  }

  .schedule-print-sheet {
    margin: 0;
    break-after: avoid;
    break-inside: avoid;
    page-break-after: avoid;
    page-break-inside: avoid;
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
  }
}
</style>
