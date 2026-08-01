<template>
  <Head :title="documentTitle" />

  <section v-for="grade in snapshot.grades" :key="grade.grade_level" class="adjusted-sheet">
    <img class="report-header print-asset" src="/images/report_header_landscape.jpg" alt="" />

    <main class="sheet-body">
      <header class="document-heading">
        <h1>Grade {{ grade.grade_level }} - Adjusted Class Schedule</h1>
        <p>{{ formattedEffectiveDate }}</p>
      </header>

      <div class="adjustment-note">
        <strong>Flag Ceremony {{ formatRange(snapshot.ceremony.start, snapshot.ceremony.end) }}</strong>
        <span>All classes and student bell periods are shifted {{ adjustment.shift_minutes }} minutes.</span>
        <span class="reason">Reason: {{ adjustment.reason }}</span>
      </div>

      <div class="schedule-grid" :style="{ '--section-count': Math.max(grade.sections.length, 1) }">
        <div class="section-headings">
          <div class="time-heading">TIME</div>
          <div v-for="section in grade.sections" :key="section.id" class="section-heading">{{ section.name }}</div>
        </div>

        <div class="timeline" :style="{ height: timelineHeight + 'mm' }">
          <div class="time-axis">
            <span v-for="minute in timeMarks" :key="minute" :style="pointStyle(minute)">{{ formatMinutes(minute) }}</span>
          </div>

          <div class="section-columns">
            <div v-for="minute in gridMarks" :key="'line-' + minute" class="grid-line"
              :class="{ half: minute % 60 !== 0 }" :style="pointStyle(minute)" />

            <div v-for="section in grade.sections" :key="section.id" class="section-column">
              <div class="ceremony-block" :style="rangeStyle(snapshot.ceremony.start, snapshot.ceremony.end)">
                <strong>Flag Ceremony</strong><span>{{ formatDisplayRange(snapshot.ceremony.start, snapshot.ceremony.end) }}</span>
              </div>
              <div v-for="band in section.bands" :key="`${band.type}-${band.start}-${band.end}`"
                class="schedule-band" :class="bandClass(band.type)" :style="rangeStyle(band.start, band.end)">
                <strong>{{ band.label }}</strong><span>{{ formatDisplayRange(band.start, band.end) }}</span>
              </div>
              <div v-for="entry in section.entries" :key="entry.id" class="class-entry"
                :class="durationClass(entry)" :style="rangeStyle(entry.start_time, entry.end_time)">
                <strong>{{ entryTitle(entry) }}</strong>
                <small>{{ formatDisplayRange(entry.start_time, entry.end_time) }}</small>
                <span v-if="duration(entry) >= 30">{{ entry.faculty?.name ?? 'TBA' }}</span>
              </div>
            </div>
            <div v-if="!grade.sections.length" class="empty-grade">No active sections found for this grade.</div>
          </div>
        </div>
      </div>

      <footer class="signatories">
        <div><span>Prepared by:</span><strong>{{ signatories?.prepared?.name ?? '' }}</strong><small>{{ signatories?.prepared?.position ?? '' }}</small></div>
        <div><span>Approved by:</span><strong>{{ signatories?.approved?.name ?? '' }}</strong><small>{{ signatories?.approved?.position ?? '' }}</small></div>
      </footer>

      <div v-if="adjustment.status === 'draft'" class="draft-watermark">DRAFT PREVIEW</div>
    </main>
    <img class="report-footer print-asset" src="/images/report_footer_landscape.jpg" alt="" />
  </section>
</template>

<script setup>
import { computed, nextTick, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  adjustment: { type: Object, required: true },
  term: { type: Object, required: true },
  snapshot: { type: Object, required: true },
  signatories: { type: Object, default: null },
})

const startMinutes = computed(() => 7 * 60 + 30)
const endMinutes = computed(() => 17 * 60)
const spanMinutes = computed(() => Math.max(1, endMinutes.value - startMinutes.value))
const timelineHeight = 118
const gridMarks = computed(() => {
  const values = []
  for (let minute = Math.ceil(startMinutes.value / 30) * 30; minute <= endMinutes.value; minute += 30) values.push(minute)
  return values
})
const timeMarks = computed(() => gridMarks.value.filter(minute => minute % 60 === 0 || minute === startMinutes.value || minute === endMinutes.value))
const documentTitle = computed(() => `ADJUSTED CLASS SCHEDULE — ${props.adjustment.effective_date}`)
const formattedEffectiveDate = computed(() => new Date(`${props.adjustment.effective_date}T00:00:00`).toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }))

function timeToMinutes(time) {
  const [hours, minutes] = String(time ?? '00:00').split(':').map(Number)
  return hours * 60 + minutes
}
function formatMinutes(minutes) {
  const hour = Math.floor(minutes / 60)
  const minute = String(minutes % 60).padStart(2, '0')
  return `${hour % 12 || 12}:${minute} ${hour < 12 ? 'AM' : 'PM'}`
}
function formatTime(time) { return formatMinutes(timeToMinutes(time)) }
function formatRange(start, end) {
  const first = formatTime(start)
  const second = formatTime(end)
  return first.slice(-2) === second.slice(-2) ? `${first.slice(0, -3)}–${second}` : `${first}–${second}`
}
function formatDisplayRange(start, end) {
  const visibleStart = Math.max(startMinutes.value, timeToMinutes(start))
  const visibleEnd = Math.min(endMinutes.value, timeToMinutes(end))
  return formatRange(minutesToTime(visibleStart), minutesToTime(visibleEnd))
}
function minutesToTime(minutes) {
  return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`
}
function pointStyle(minute) { return { top: `${((minute - startMinutes.value) / spanMinutes.value) * 100}%` } }
function rangeStyle(start, end) {
  const from = Math.max(startMinutes.value, timeToMinutes(start))
  const to = Math.min(endMinutes.value, timeToMinutes(end))
  return { top: `${((from - startMinutes.value) / spanMinutes.value) * 100}%`, height: `${(Math.max(0, to - from) / spanMinutes.value) * 100}%` }
}
function duration(entry) { return timeToMinutes(entry.end_time) - timeToMinutes(entry.start_time) }
function durationClass(entry) {
  if (duration(entry) <= 20) return 'very-short'
  if (duration(entry) <= 30) return 'short'
  return ''
}
function entryTitle(entry) {
  if (entry.entry_type === 'non_teaching') return entry.title || 'Non-teaching'
  return entry.subject?.code ?? entry.subject?.name ?? 'TBA'
}
function bandClass(type) {
  if (type === 'ELECTIVE') return 'elective'
  if (type === 'SCIENCE_CORE') return 'science-core'
  if (type === 'LUNCH' || type === 'RECESS') return 'break'
  return 'fixed'
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
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #d1d5db; }
.adjusted-sheet { position: relative; width: 297mm; height: 210mm; margin: 4mm auto; overflow: hidden; background: white; color: #0f172a; font-family: Arial, Helvetica, sans-serif; box-shadow: 0 1mm 3mm rgba(15, 23, 42, .25); }
.report-header, .report-footer { position: absolute; left: 0; width: 100%; object-fit: fill; }
.report-header { top: 0; height: 25mm; }
.report-footer { bottom: 0; height: 12mm; }
.sheet-body { position: absolute; inset: 24mm 10mm 11mm; }
.document-heading { border-bottom: .5mm solid #1e3a8a; padding: 1.5mm 0; text-align: center; }
.document-heading h1 { margin: 0; font-size: 14pt; font-weight: 800; color: #172554; }
.document-heading p { margin: 1mm 0 0; font-size: 8pt; color: #475569; }
.adjustment-note { display: flex; align-items: center; gap: 4mm; min-height: 8mm; border: .25mm solid #f59e0b; background: #fffbeb; padding: 1.5mm 2.5mm; font-size: 7.5pt; color: #78350f; }
.adjustment-note .reason { margin-left: auto; max-width: 105mm; text-align: right; }
.schedule-grid { border: .3mm solid #94a3b8; }
.section-headings { display: grid; grid-template-columns: 20mm repeat(var(--section-count), minmax(0, 1fr)); min-height: 8mm; background: #eff6ff; border-bottom: .3mm solid #94a3b8; }
.time-heading, .section-heading { display: flex; align-items: center; justify-content: center; border-right: .2mm solid #cbd5e1; font-size: 8pt; font-weight: 700; text-transform: uppercase; }
.timeline { display: grid; grid-template-columns: 20mm 1fr; position: relative; }
.time-axis { position: relative; border-right: .3mm solid #94a3b8; }
.time-axis span { position: absolute; right: 1.5mm; transform: translateY(-50%); white-space: nowrap; font-size: 6.2pt; color: #475569; }
.time-axis span:first-child { transform: translateY(0); }
.time-axis span:last-child { transform: translateY(-100%); }
.section-columns { position: relative; display: grid; grid-template-columns: repeat(var(--section-count), minmax(0, 1fr)); }
.section-column { position: relative; min-width: 0; border-right: .2mm solid #cbd5e1; }
.grid-line { position: absolute; z-index: 0; left: 0; right: 0; border-top: .2mm solid #cbd5e1; pointer-events: none; }
.grid-line.half { border-top-style: dotted; border-top-color: #e2e8f0; }
.ceremony-block, .schedule-band, .class-entry { position: absolute; left: .5mm; right: .5mm; z-index: 2; overflow: hidden; padding: .6mm 1mm; text-align: center; line-height: 1.12; }
.ceremony-block { background: #fef3c7; border: .25mm solid #f59e0b; color: #92400e; font-size: 6.8pt; }
.ceremony-block strong, .ceremony-block span, .schedule-band strong, .schedule-band span, .class-entry strong, .class-entry span, .class-entry small { display: block; }
.schedule-band { z-index: 1; background: #f8fafc; border: .2mm dashed #94a3b8; color: #475569; font-size: 6pt; }
.schedule-band.break { background: #f1f5f9; }
.schedule-band.elective { background: #fffbeb; border-color: #f59e0b; color: #92400e; }
.schedule-band.science-core { background: #f5f3ff; border-color: #8b5cf6; color: #5b21b6; }
.class-entry { background: #dbeafe; border: .25mm solid #60a5fa; color: #1e3a8a; font-size: 6.3pt; }
.class-entry strong { font-size: 7pt; }
.class-entry span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.class-entry small { margin: .3mm 0; white-space: nowrap; font-size: 5.7pt; font-weight: 700; }
.class-entry.short { padding: .25mm .7mm; }
.class-entry.short span { display: none; }
.class-entry.very-short { display: flex; align-items: center; justify-content: space-between; padding: 0 .7mm; }
.class-entry.very-short span { display: none; }
.class-entry.very-short small { margin: 0; }
.empty-grade { grid-column: 1 / -1; padding: 20mm; text-align: center; font-size: 9pt; color: #64748b; }
.signatories { display: grid; grid-template-columns: 1fr 1fr; gap: 35mm; margin: 3mm 20mm 0; }
.signatories > div { display: flex; min-height: 14mm; flex-direction: column; justify-content: flex-end; text-align: center; font-size: 6.5pt; }
.signatories span { align-self: flex-start; color: #475569; }
.signatories strong { border-bottom: .2mm solid #334155; padding-bottom: .6mm; font-size: 8pt; text-transform: uppercase; }
.signatories small { padding-top: .5mm; color: #475569; }
.draft-watermark { position: absolute; z-index: 20; top: 77mm; left: 55mm; transform: rotate(-28deg); font-size: 38pt; font-weight: 800; letter-spacing: .12em; color: rgba(220, 38, 38, .13); pointer-events: none; }
@page { size: A4 landscape; margin: 0; }
@media print {
  html, body { width: 297mm; margin: 0; padding: 0; background: white; }
  .adjusted-sheet { margin: 0; box-shadow: none; break-after: page; page-break-after: always; }
  .adjusted-sheet:last-of-type { break-after: avoid; page-break-after: avoid; }
}
</style>
