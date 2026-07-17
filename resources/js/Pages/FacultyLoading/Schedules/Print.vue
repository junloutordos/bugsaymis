<template>
  <Head :title="documentTitle" />

  <div class="schedule-print-sheet">
    <img class="schedule-print-header print-asset" src="/images/report_header.jpeg" alt="" />

    <main class="schedule-print-body">
      <div class="schedule-print-heading">
        <h1>{{ documentTitle }}</h1>
        <p class="schedule-print-sy">S.Y. {{ schoolYearLabel }}</p>

        <div v-if="scheduleType === 'faculty'" class="schedule-print-facultymeta">
          <div><span class="schedule-print-facultymeta-label">Name:</span> <strong>{{ owner.name }}</strong></div>
          <div v-if="loadSummary"><span class="schedule-print-facultymeta-label">Load:</span> <em>{{ loadSummary }}</em></div>
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
              :class="{ 'schedule-print-time-first': minute === CAL_START, 'schedule-print-time-last': minute === CAL_END }"
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
                  <span class="schedule-print-blocked-time">{{ formatTimeRange(blocked.start, blocked.end) }}</span>
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
                :style="rangeStyle(entry.start_time, entry.end_time)"
              >
                <div class="schedule-print-event-title">{{ eventTitle(entry) }}</div>
                <div v-if="eventDetail(entry)" class="schedule-print-event-detail">{{ eventDetail(entry) }}</div>
                <div v-if="durationMinutes(entry) >= 40" class="schedule-print-event-time">
                  {{ formatTime(entry.start_time) }}–{{ formatTime(entry.end_time) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="scheduleType === 'faculty'" class="schedule-print-summary">
          <div class="schedule-print-summary-row">
            <div class="schedule-print-summary-label">Official<br>Time</div>
            <div v-for="day in WEEKDAYS" :key="'ot-' + day" class="schedule-print-summary-cell">
              {{ officialTimeLabel(day) }}
            </div>
          </div>
          <div class="schedule-print-summary-row">
            <div class="schedule-print-summary-label">Lunch<br>Time</div>
            <div v-for="day in WEEKDAYS" :key="'lt-' + day" class="schedule-print-summary-cell">
              {{ lunchTimeLabel(day) }}
            </div>
          </div>
        </div>
      </div>

      <div class="schedule-print-signatories" :class="{ 'schedule-print-signatories-three': scheduleType === 'faculty' }">
        <div class="schedule-print-signatory">
          <div class="schedule-print-signatory-caption">Prepared by:</div>
          <div class="schedule-print-signature-space">
            <img v-if="signatories?.prepared?.signature" :src="signatories.prepared.signature" alt="" />
          </div>
          <div class="schedule-print-signatory-name">{{ signatories?.prepared?.name ?? '' }}</div>
          <div class="schedule-print-signatory-position">{{ signatories?.prepared?.position ?? '' }}</div>
          <div v-if="signatories?.prepared?.signed_at" class="schedule-print-signatory-date">Digitally signed {{ signatories.prepared.signed_at }}</div>
        </div>
        <div class="schedule-print-signatory">
          <div class="schedule-print-signatory-caption">
            {{ scheduleType === 'faculty' ? 'Reviewed and Approved by:' : 'Approved by:' }}
          </div>
          <div class="schedule-print-signature-space">
            <img v-if="signatories?.approved?.signature" :src="signatories.approved.signature" alt="" />
          </div>
          <div class="schedule-print-signatory-name">{{ signatories?.approved?.name ?? '' }}</div>
          <div class="schedule-print-signatory-position">{{ signatories?.approved?.position ?? '' }}</div>
          <div v-if="signatories?.approved?.signed_at" class="schedule-print-signatory-date">Digitally signed {{ signatories.approved.signed_at }}</div>
        </div>
        <div v-if="scheduleType === 'faculty'" class="schedule-print-signatory">
          <div class="schedule-print-signatory-caption">Conforme:</div>
          <div class="schedule-print-signature-space">
            <img v-if="signatories?.conforme?.signature" :src="signatories.conforme.signature" alt="" />
          </div>
          <div class="schedule-print-signatory-name">{{ signatories?.conforme?.name ?? owner.name ?? '' }}</div>
          <div class="schedule-print-signatory-position">{{ signatories?.conforme?.position ?? owner.position ?? 'Faculty' }}</div>
          <div v-if="signatories?.conforme?.signed_at" class="schedule-print-signatory-date">Digitally signed {{ signatories.conforme.signed_at }}</div>
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
  signatories: { type: Object, default: null },
  loadSummary: { type: String, default: null },
  officialTimes: { type: Object, default: () => ({}) },
})

const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']
const CAL_START = 7 * 60
const CAL_END = 17 * 60 // bell schedules run to 17:00 (Consultation/ALP)
const NOON = 12 * 60
const HOUR_MARKS = Array.from({ length: 11 }, (_, index) => CAL_START + index * 60)
const GRID_MARKS = Array.from(
  { length: Math.floor((CAL_END - CAL_START) / 30) + 1 },
  (_, index) => CAL_START + index * 30,
)

const documentTitle = computed(() => {
  if (props.scheduleType === 'section') {
    return `GRADE ${props.owner.grade_level} ${props.owner.name?.toUpperCase() ?? ''} CLASS SCHEDULE`
  }
  return 'INDIVIDUAL FACULTY SCHEDULE'
})

const schoolYearLabel = computed(() => (props.term.school_year ?? '').replace('-', '–'))

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
  return `${displayHour}:${minute} ${hour < 12 ? 'AM' : 'PM'}`
}

/** "9:40–10:00 AM" — the meridiem is repeated only when it differs. */
function formatTimeRange(start, end) {
  const s = formatTime(start)
  const e = formatTime(end)
  return s.slice(-2) === e.slice(-2) ? `${s.slice(0, -3)}–${e}` : `${s}–${e}`
}

function eventTitle(entry) {
  if (entry.entry_type === 'non_teaching') return entry.title || 'Non-teaching'
  const name = entry.subject?.name ?? entry.subject?.code ?? 'TBA'
  return `${name}${entry.session_type === 'ilp' ? ' (ILP)' : ''}`
}

function eventDetail(entry) {
  if (props.scheduleType === 'faculty') {
    if (entry.entry_type === 'non_teaching') return entry.section_name ?? ''
    return `G${entry.grade_level} ${entry.section_name ?? ''}`.trim()
  }
  return entry.faculty?.name ?? (entry.entry_type === 'non_teaching' ? '' : 'TBA')
}

// ── Official / lunch time summary (faculty print only) ───────────────────────

function officialWindow(day) {
  const config = props.officialTimes?.[day]
  if (!config?.start || !config?.end) return null
  return { start: timeToMinutes(config.start), end: timeToMinutes(config.end) }
}

function officialTimeLabel(day) {
  const window = officialWindow(day)
  if (!window) return '—'
  return `${formatTime(minutesToTime(window.start))} – ${formatTime(minutesToTime(window.end))}`
}

/**
 * Lunch = up to 60 free minutes inside the 10:00 AM–2:00 PM window (clipped to
 * official time), taken from the longest free gap of the day's occupied blocks.
 * Like the CID Excel sheet, the slot ends when the next class starts; on a day
 * with no midday classes it defaults to the 12:00–1:00 block.
 */
function lunchTimeLabel(day) {
  const official = officialWindow(day) ?? { start: CAL_START, end: CAL_END }
  const windowStart = Math.max(10 * 60, official.start)
  const windowEnd = Math.min(14 * 60, official.end)
  if (windowEnd - windowStart < 30) return '—'

  const busy = (schedulesByDay.value[day] ?? [])
    .map(entry => [timeToMinutes(entry.start_time), timeToMinutes(entry.end_time)])
    .filter(([start, end]) => end > windowStart && start < windowEnd)
    .map(([start, end]) => [Math.max(start, windowStart), Math.min(end, windowEnd)])
    .sort((a, b) => a[0] - b[0])

  const gaps = []
  let cursor = windowStart
  for (const [start, end] of busy) {
    if (start > cursor) gaps.push([cursor, start])
    cursor = Math.max(cursor, end)
  }
  if (cursor < windowEnd) gaps.push([cursor, windowEnd])

  const longest = gaps.sort((a, b) => (b[1] - b[0]) - (a[1] - a[0]))[0]
  if (!longest || longest[1] - longest[0] < 30) return '—'

  let [start, end] = longest
  if (end < windowEnd) {
    // Gap is bounded by an afternoon class — lunch ends when that class starts.
    start = Math.max(start, end - 60)
  } else if (start <= NOON && end >= 13 * 60) {
    [start, end] = [NOON, 13 * 60]
  } else {
    start = Math.max(start, end - 60)
  }

  return `${formatTime(minutesToTime(start))} – ${formatTime(minutesToTime(end))}`
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
  color: #0f172a;
  font-family: 'Helvetica Neue', Arial, Helvetica, sans-serif;
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
  text-align: center;
}

.schedule-print-heading h1 {
  margin: 0;
  font-size: 13pt;
  font-weight: 800;
  letter-spacing: 0.06em;
}

.schedule-print-sy {
  margin: 0.4mm 0 0;
  font-size: 9pt;
  font-weight: 600;
  letter-spacing: 0.08em;
  color: #334155;
}

.schedule-print-facultymeta {
  margin-top: 1.2mm;
  padding-top: 1mm;
  border-top: 0.2mm solid #cbd5e1;
  text-align: left;
  font-size: 7.5pt;
  line-height: 1.35;
}

.schedule-print-facultymeta-label {
  display: inline-block;
  min-width: 10mm;
  font-weight: 700;
  color: #475569;
}

.schedule-print-calendar {
  min-height: 0;
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  border: 0.35mm solid #1e293b;
  border-radius: 1mm;
  overflow: hidden;
}

.schedule-print-days {
  flex: 0 0 7mm;
  display: grid;
  grid-template-columns: 13mm repeat(5, 1fr);
  border-bottom: 0.3mm solid #1e293b;
  background: #eef2ff;
}

.schedule-print-corner,
.schedule-print-day {
  display: flex;
  align-items: center;
  justify-content: center;
  border-left: 0.2mm solid #c7d2fe;
  color: #312e81;
  font-size: 7pt;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.schedule-print-corner {
  border-left: 0;
  font-size: 6pt;
  color: #6366f1;
}

.schedule-print-timeline {
  min-height: 0;
  flex: 1 1 auto;
  display: flex;
}

.schedule-print-time-axis {
  position: relative;
  flex: 0 0 13mm;
  border-right: 0.25mm solid #1e293b;
  background: #f8fafc;
}

.schedule-print-time-axis span {
  position: absolute;
  right: 1.2mm;
  z-index: 3;
  transform: translateY(-50%);
  padding: 0 0.5mm;
  font-size: 5.8pt;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  color: #475569;
  white-space: nowrap;
}

/* First hour label sits below its gridline so it never straddles the day-header border */
.schedule-print-time-axis .schedule-print-time-first {
  transform: translateY(15%);
}

.schedule-print-time-axis .schedule-print-time-last {
  transform: translateY(-100%);
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
  border-top: 0.16mm solid #cbd5e1;
}

.schedule-print-gridline-half {
  border-top: 0.1mm dashed #e2e8f0;
}

.schedule-print-column {
  position: relative;
  min-width: 0;
  border-left: 0.2mm solid #cbd5e1;
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
  /* Label + time share one line when they fit; tall bands wrap to two. */
  flex-wrap: wrap;
  column-gap: 1.2mm;
  line-height: 1.15;
  overflow: hidden;
  border-top: 0.12mm solid #cbd5e1;
  border-bottom: 0.12mm solid #cbd5e1;
  background: #e2e8f0;
  color: #64748b;
  font-size: 5.5pt;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  text-align: center;
}

.schedule-print-blocked-time {
  font-size: 5pt;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: none;
  font-variant-numeric: tabular-nums;
  opacity: 0.85;
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
  display: flex;
  flex-direction: column;
  justify-content: center;
  min-height: 2.4mm;
  overflow: hidden;
  padding: 0.4mm 0.8mm 0.4mm 1.1mm;
  background: #dbeafe;
  border: 0.18mm solid #93c5fd;
  border-left: 0.8mm solid #2563eb;
  border-radius: 0.8mm;
  color: #1e3a8a;
  font-size: 5.8pt;
  line-height: 1.15;
  text-align: center;
}

.schedule-print-event-nonteaching {
  background: #f1f5f9;
  border-color: #94a3b8;
  border-left-color: #64748b;
  border-style: dashed;
  border-left-style: solid;
  color: #1e293b;
}

.schedule-print-event-tentative {
  border-right-width: 1mm;
  border-right-style: solid;
  border-right-color: #1e40af;
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
  font-variant-numeric: tabular-nums;
  opacity: 0.75;
}

.schedule-print-event-short {
  flex-direction: row;
  align-items: center;
  justify-content: flex-start;
  gap: 0.8mm;
  padding-top: 0.2mm;
  padding-bottom: 0.2mm;
  text-align: left;
}

.schedule-print-event-short .schedule-print-event-title,
.schedule-print-event-short .schedule-print-event-detail {
  min-width: 0;
}

/* ── Official / lunch time summary rows (faculty print) ───────────────────── */

.schedule-print-summary {
  flex: 0 0 auto;
  border-top: 0.3mm solid #1e293b;
}

.schedule-print-summary-row {
  display: grid;
  grid-template-columns: 13mm repeat(5, 1fr);
  border-top: 0.15mm solid #c7d2fe;
}

.schedule-print-summary-row:first-of-type {
  border-top: 0;
}

.schedule-print-summary-label {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.8mm 0.5mm;
  background: #eef2ff;
  color: #4338ca;
  font-size: 4.8pt;
  font-weight: 700;
  letter-spacing: 0.08em;
  line-height: 1.25;
  text-align: center;
  text-transform: uppercase;
}

.schedule-print-summary-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.8mm 0.5mm;
  border-left: 0.2mm solid #c7d2fe;
  background: #f5f7ff;
  font-size: 6pt;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  color: #1e3a8a;
}

/* ── Signatories ──────────────────────────────────────────────────────────── */

.schedule-print-signatories {
  flex: 0 0 auto;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0 24mm;
  padding: 2.5mm 6mm 1mm;
}

.schedule-print-signatories-three {
  grid-template-columns: repeat(3, 1fr);
  gap: 0 12mm;
}

.schedule-print-signatory-caption {
  font-size: 7.5pt;
  color: #334155;
}

.schedule-print-signature-space {
  height: 7mm;
  display: flex;
  align-items: flex-end;
  justify-content: flex-start;
}

.schedule-print-signature-space img {
  display: block;
  max-width: 36mm;
  max-height: 9mm;
  object-fit: contain;
}

.schedule-print-signatory-name {
  font-size: 8.5pt;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.schedule-print-signatory-position {
  font-size: 7pt;
  color: #475569;
}

.schedule-print-signatory-date {
  margin-top: 0.3mm;
  font-size: 5.5pt;
  color: #64748b;
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
