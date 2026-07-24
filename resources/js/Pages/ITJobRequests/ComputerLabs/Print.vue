<script setup>
import { computed, nextTick, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { subjectColorStyle } from '@/Utils/subjectColor.js'

const props = defineProps({
  labs: { type: Array, default: () => [] },
  term: { type: Object, required: true },
  days: { type: Array, default: () => [] },
  bookings: { type: Array, default: () => [] },
  signatories: { type: Object, default: () => ({}) },
})

const CAL_START = 7 * 60
const CAL_END = 17 * 60
const HOUR_MARKS = Array.from({ length: 11 }, (_, index) => CAL_START + index * 60)

function timeToMinutes(time) {
  if (!time) return 0
  const [hour, minute] = time.split(':').map(Number)
  return (hour * 60) + minute
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

function bookingsForLabDay(roomId, date) {
  return props.bookings.filter(booking => booking.room_id === roomId && booking.date === date)
}

function durationMinutes(booking) {
  return timeToMinutes(booking.end_time) - timeToMinutes(booking.start_time)
}

/**
 * Duration tiers scale font-size/line-height/layout down as the booking
 * cell shrinks, so short (e.g. 30-minute) bookings still show a legible
 * title + time instead of clipped/overflowing text. Mirrors
 * SchedulePrintSheet.vue's durationTierClass() convention.
 */
function durationTierClass(booking) {
  const minutes = durationMinutes(booking)
  if (minutes <= 15) return 'lab-print-event-xs'
  if (minutes <= 25) return 'lab-print-event-sm'
  if (minutes <= 30) return 'lab-print-event-short'
  return ''
}

const schoolYearLabel = computed(() => (props.term.school_year ?? '').replace('-', '–'))

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

<template>
  <Head title="Computer Laboratory Schedule — Print" />
  <div class="lab-print-pages">
    <section v-for="lab in labs" :key="lab.room.id" class="lab-print-sheet">
      <img class="lab-print-header print-asset" src="/images/report_header_landscape.jpg" alt="" />

      <main class="lab-print-body">
        <div class="lab-print-heading">
          <h1>{{ lab.room.name?.toUpperCase() }} SCHEDULE</h1>
          <p class="lab-print-sy">S.Y. {{ schoolYearLabel }}</p>
        </div>

        <div class="lab-print-calendar">
          <div class="lab-print-days">
            <div class="lab-print-corner">TIME</div>
            <div v-for="day in days" :key="day.date" class="lab-print-day">{{ day.name.slice(0, 3) }}</div>
          </div>

          <div class="lab-print-timeline">
            <div class="lab-print-time-axis">
              <span v-for="minute in HOUR_MARKS" :key="minute"
                :class="{ 'lab-print-time-first': minute === CAL_START, 'lab-print-time-last': minute === CAL_END }"
                :style="positionStyle(minute)">
                {{ formatHour(minute) }}
              </span>
            </div>

            <div class="lab-print-day-grid">
              <div v-for="minute in HOUR_MARKS" :key="`line-${minute}`" class="lab-print-gridline" :style="positionStyle(minute)" />

              <div v-for="day in days" :key="day.date" class="lab-print-column">
                <div v-for="booking in bookingsForLabDay(lab.room.id, day.date)" :key="booking.id"
                  class="lab-print-event" :class="durationTierClass(booking)"
                  :style="{ ...rangeStyle(booking.start_time, booking.end_time), ...subjectColorStyle(booking.subject || booking.title) }">
                  <div class="lab-print-event-title">{{ booking.title }}</div>
                  <div v-if="(booking.faculty || booking.requester) && durationMinutes(booking) > 15" class="lab-print-event-detail">{{ booking.faculty || booking.requester }}</div>
                  <div class="lab-print-event-time">{{ formatTime(booking.start_time) }}–{{ formatTime(booking.end_time) }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="lab-print-signatories">
          <div class="lab-print-signatory">
            <div class="lab-print-signatory-caption">Prepared by:</div>
            <div class="lab-print-signature-space">
              <img v-if="signatories?.prepared?.signature" :src="signatories.prepared.signature" alt="" />
            </div>
            <div class="lab-print-signatory-name">{{ signatories?.prepared?.name ?? '' }}</div>
            <div class="lab-print-signatory-position">{{ signatories?.prepared?.position ?? 'Science Research Assistant' }}</div>
            <div v-if="signatories?.prepared?.signed_at" class="lab-print-signatory-date">Digitally signed {{ signatories.prepared.signed_at }}</div>
          </div>
          <div class="lab-print-signatory">
            <div class="lab-print-signatory-caption">Approved by:</div>
            <div class="lab-print-signature-space">
              <img v-if="signatories?.approved?.signature" :src="signatories.approved.signature" alt="" />
            </div>
            <div class="lab-print-signatory-name">{{ signatories?.approved?.name ?? '' }}</div>
            <div class="lab-print-signatory-position">{{ signatories?.approved?.position ?? 'CID Chief' }}</div>
            <div v-if="signatories?.approved?.signed_at" class="lab-print-signatory-date">Digitally signed {{ signatories.approved.signed_at }}</div>
          </div>
        </div>
      </main>

      <img class="lab-print-footer print-asset" src="/images/report_footer_landscape.jpg" alt="" />
    </section>
  </div>
</template>

<style>
.lab-print-pages {
  background: #fff;
}

.lab-print-sheet {
  width: 297mm;
  height: 210mm;
  margin: 0 auto;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  background: #fff;
  color: #0f172a;
  font-family: 'Helvetica Neue', Arial, Helvetica, sans-serif;
  page-break-after: always;
}

.lab-print-sheet:last-child {
  page-break-after: auto;
}

.lab-print-header,
.lab-print-footer {
  display: block;
  width: 100%;
  height: auto;
  margin: 0;
  flex: 0 0 auto;
}

.lab-print-body {
  min-height: 0;
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  padding: 1mm 10mm 0;
}

.lab-print-heading {
  flex: 0 0 auto;
  margin-bottom: 1.5mm;
  text-align: center;
}

.lab-print-heading h1 {
  margin: 0;
  font-size: 13pt;
  font-weight: 800;
  letter-spacing: 0.06em;
}

.lab-print-sy {
  margin: 0.4mm 0 0;
  font-size: 9pt;
  font-weight: 600;
  letter-spacing: 0.08em;
  color: #334155;
}

.lab-print-calendar {
  min-height: 0;
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  border: 0.35mm solid #1e293b;
  border-radius: 1mm;
  overflow: hidden;
}

.lab-print-days {
  flex: 0 0 7mm;
  display: grid;
  grid-template-columns: 13mm repeat(5, 1fr);
  border-bottom: 0.3mm solid #1e293b;
  background: #eef2ff;
}

.lab-print-corner,
.lab-print-day {
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

.lab-print-corner {
  border-left: 0;
  font-size: 6pt;
  color: #6366f1;
}

.lab-print-timeline {
  min-height: 0;
  flex: 1 1 auto;
  display: flex;
}

.lab-print-time-axis {
  position: relative;
  flex: 0 0 13mm;
  border-right: 0.25mm solid #1e293b;
  background: #f8fafc;
}

.lab-print-time-axis span {
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

.lab-print-time-axis .lab-print-time-first {
  transform: translateY(15%);
}

.lab-print-time-axis .lab-print-time-last {
  transform: translateY(-100%);
}

.lab-print-day-grid {
  position: relative;
  min-width: 0;
  flex: 1 1 auto;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
}

.lab-print-gridline {
  position: absolute;
  left: 0;
  right: 0;
  z-index: 0;
  border-top: 0.16mm solid #cbd5e1;
}

.lab-print-column {
  position: relative;
  min-width: 0;
  border-left: 0.2mm solid #cbd5e1;
  overflow: hidden;
}

.lab-print-column:first-of-type {
  border-left: 0;
}

.lab-print-event {
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
  border: 0.18mm solid;
  border-left-width: 0.8mm;
  border-radius: 0.8mm;
  font-size: 5.8pt;
  line-height: 1.15;
  text-align: center;
}

.lab-print-event-title {
  overflow: hidden;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.lab-print-event-detail,
.lab-print-event-time {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.lab-print-event-detail {
  margin-top: 0.2mm;
  opacity: 0.9;
}

.lab-print-event-time {
  margin-top: 0.2mm;
  font-variant-numeric: tabular-nums;
  opacity: 0.75;
}

.lab-print-event-short {
  flex-direction: row;
  align-items: center;
  justify-content: flex-start;
  gap: 0.8mm;
  padding-top: 0.2mm;
  padding-bottom: 0.2mm;
  font-size: 5.3pt;
  text-align: left;
}

.lab-print-event-short .lab-print-event-title,
.lab-print-event-short .lab-print-event-detail {
  min-width: 0;
}

.lab-print-event-short .lab-print-event-time {
  margin-top: 0;
  flex: 0 0 auto;
}

/* 16–25min bookings — single line, title + time only, no detail line
 * (dropped in template to save room). Smaller font so both fit without
 * clipping. */
.lab-print-event-sm {
  flex-direction: row;
  align-items: center;
  justify-content: flex-start;
  gap: 0.6mm;
  padding: 0.1mm 0.6mm 0.1mm 0.9mm;
  min-height: 1.8mm;
  font-size: 4.8pt;
  line-height: 1.05;
  text-align: left;
}

.lab-print-event-sm .lab-print-event-title {
  min-width: 0;
  flex: 0 1 auto;
}

.lab-print-event-sm .lab-print-event-detail {
  display: none;
}

.lab-print-event-sm .lab-print-event-time {
  flex: 0 0 auto;
  margin-top: 0;
}

/* ≤15min bookings — tightest tier. Title truncates first; time is always
 * fully visible so the period is still identifiable at a glance. */
.lab-print-event-xs {
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
  gap: 0.4mm;
  padding: 0 0.5mm 0 0.8mm;
  min-height: 1.6mm;
  font-size: 4.3pt;
  line-height: 1;
  text-align: left;
}

.lab-print-event-xs .lab-print-event-title {
  min-width: 0;
  flex: 1 1 auto;
}

.lab-print-event-xs .lab-print-event-detail {
  display: none;
}

.lab-print-event-xs .lab-print-event-time {
  flex: 0 0 auto;
  margin-top: 0;
  opacity: 0.85;
}

.lab-print-signatories {
  flex: 0 0 auto;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0 24mm;
  padding: 2.5mm 6mm 1mm;
}

.lab-print-signatory-caption {
  font-size: 7.5pt;
  color: #334155;
}

.lab-print-signature-space {
  height: 7mm;
  display: flex;
  align-items: flex-end;
  justify-content: flex-start;
}

.lab-print-signature-space img {
  display: block;
  max-width: 36mm;
  max-height: 9mm;
  object-fit: contain;
}

.lab-print-signatory-name {
  font-size: 8.5pt;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.lab-print-signatory-position {
  font-size: 7pt;
  color: #475569;
}

.lab-print-signatory-date {
  margin-top: 0.3mm;
  font-size: 5.5pt;
  color: #64748b;
}

@media print {
  .lab-print-sheet {
    margin: 0;
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
  }

  @page {
    size: A4 landscape;
    margin: 0.4in;
  }
}
</style>
