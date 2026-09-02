<template>
  <Head :title="documentTitle" />

  <section v-for="grade in snapshot.grades" :key="grade.grade_level" class="adjusted-sheet">
    <img class="report-header print-asset" src="/images/report_header_landscape.jpg" alt="" />

    <main class="sheet-body">
      <header class="document-heading">
        <h1>GRADE {{ grade.grade_level }} - ADJUSTED CLASS SCHEDULE</h1>
        <p>{{ formattedEffectiveDate }}</p>
      </header>

      <div class="schedule-grid" :style="{ '--section-count': Math.max(grade.sections.length, 1) }">
        <div class="section-headings">
          <div class="time-heading">TIME</div>
          <div v-for="section in grade.sections" :key="section.id" class="section-heading">{{ section.name }}</div>
        </div>

        <div class="timeline" :style="{ height: timelineHeight + 'mm' }">
          <div class="time-axis">
            <span v-for="minute in timeMarks" :key="minute" :style="pointStyle(minute, grade)">{{ formatMinutes(minute) }}</span>
          </div>

          <div class="section-columns">
            <div v-for="minute in gridMarks" :key="'line-' + minute" class="grid-line"
              :class="{ half: minute % 60 !== 0 }" :style="pointStyle(minute, grade)" />

            <div v-for="section in grade.sections" :key="section.id" class="section-column">
              <div v-if="snapshot.ceremony" class="ceremony-block" :class="durationClass(snapshot.ceremony.start, snapshot.ceremony.end)"
                :style="rangeStyle(snapshot.ceremony.start, snapshot.ceremony.end, grade)">
                <strong>Flag Ceremony</strong><span class="block-time">{{ formatDisplayRange(snapshot.ceremony.start, snapshot.ceremony.end) }}</span>
              </div>
              <div v-for="band in section.bands" :key="`${band.type}-${band.start}-${band.end}`"
                class="schedule-band" :class="[bandClass(band.type), durationClass(band.start, band.end)]" :style="rangeStyle(band.start, band.end, grade)">
                <strong>{{ band.label }}</strong><span class="block-time">{{ formatDisplayRange(band.start, band.end) }}</span>
              </div>
              <div v-for="entry in section.entries" :key="entry.id" class="class-entry"
                :class="durationClass(entry.start_time, entry.end_time)" :style="rangeStyle(entry.start_time, entry.end_time, grade)">
                <div class="entry-main">
                  <strong>{{ entryTitle(entry) }}</strong>
                  <small>{{ formatDisplayRange(entry.start_time, entry.end_time) }}</small>
                  <span v-if="entry.manually_adjusted" class="adjusted-badge" title="Manually adjusted before publishing">*</span>
                </div>
                <span v-if="duration(entry.start_time, entry.end_time) >= 30" class="faculty-name">{{ entry.faculty?.name ?? 'TBA' }}</span>
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

      <p v-if="hasManualAdjustments(grade)" class="manual-adjustment-note">
        * Manually adjusted time to resolve a scheduling conflict prior to publishing.
      </p>

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
// Two-zone timeline: a purely linear 7:30-17:00 scale gives a 30-minute
// compressed class only ~6mm on the sheet whenever a multi-hour Early
// Dismissal / Official Activity tail shares the same page, and overflow:
// hidden on the entry boxes then silently clips the details. Zone 1 (day
// start through the last real instructional time) is stretched to occupy
// most of the sheet; the administrative tail (Official Activity/Consult)
// is compressed into a small remainder — same continuous timeline, just
// rebalanced so the busy period is legible.
const INSTRUCTIONAL_ZONE_SHARE = 0.8
const MIN_TAIL_MINUTES = 30

function instructionalBoundaryMinutes(grade) {
  const ends = []
  if (props.snapshot.ceremony) ends.push(timeToMinutes(props.snapshot.ceremony.end))
  for (const section of grade.sections ?? []) {
    for (const entry of section.entries ?? []) ends.push(timeToMinutes(entry.end_time))
    for (const band of section.bands ?? []) {
      if (band.type === 'OFFICIAL_ACTIVITY' || band.type === 'CONSULT') continue
      ends.push(timeToMinutes(band.end))
    }
  }
  if (!ends.length) return endMinutes.value
  return Math.min(endMinutes.value, Math.max(...ends))
}
function scaledPercent(minutes, boundary) {
  const clamped = Math.min(endMinutes.value, Math.max(startMinutes.value, minutes))
  const tailMinutes = endMinutes.value - boundary
  if (tailMinutes < MIN_TAIL_MINUTES) {
    return ((clamped - startMinutes.value) / spanMinutes.value) * 100
  }
  const zone1Span = boundary - startMinutes.value
  const zone1Percent = INSTRUCTIONAL_ZONE_SHARE * 100
  if (clamped <= boundary) {
    return zone1Span > 0 ? ((clamped - startMinutes.value) / zone1Span) * zone1Percent : 0
  }
  return zone1Percent + ((clamped - boundary) / tailMinutes) * (100 - zone1Percent)
}
function pointStyle(minute, grade) {
  return { top: `${scaledPercent(minute, instructionalBoundaryMinutes(grade))}%` }
}
function rangeStyle(start, end, grade) {
  const boundary = instructionalBoundaryMinutes(grade)
  const from = Math.max(startMinutes.value, timeToMinutes(start))
  const to = Math.min(endMinutes.value, timeToMinutes(end))
  const top = scaledPercent(from, boundary)
  const bottom = scaledPercent(to, boundary)
  return { top: `${top}%`, height: `${Math.max(0, bottom - top)}%` }
}
function duration(start, end) { return timeToMinutes(end) - timeToMinutes(start) }
function durationClass(start, end) {
  if (duration(start, end) <= 20) return 'very-short'
  if (duration(start, end) <= 30) return 'short'
  return ''
}
function entryTitle(entry) {
  if (entry.entry_type === 'non_teaching') return entry.title || 'Non-teaching'
  return entry.subject?.name ?? entry.subject?.code ?? 'TBA'
}
function bandClass(type) {
  if (type === 'OFFICIAL_ACTIVITY') return 'official-activity'
  if (type === 'ELECTIVE') return 'elective'
  if (type === 'SCIENCE_CORE') return 'science-core'
  if (type === 'LUNCH' || type === 'RECESS') return 'break'
  return 'fixed'
}
function hasManualAdjustments(grade) {
  return (grade.sections ?? []).some(section => (section.entries ?? []).some(entry => entry.manually_adjusted))
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
.report-header, .report-footer { position: absolute; left: 0; display: block; width: 100%; height: 25mm; object-fit: contain; }
.report-header { top: 0; height: 25mm; }
.report-footer { bottom: 0; }
.sheet-body { position: absolute; inset: 25mm 10mm 25mm; }
.document-heading { border-bottom: .5mm solid #1e3a8a; padding: 1.5mm 0; text-align: center; }
.document-heading h1 { margin: 0; font-size: 14pt; font-weight: 800; color: #172554; }
.document-heading p { margin: 1mm 0 0; font-size: 8pt; color: #475569; }
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
.ceremony-block.short, .ceremony-block.very-short, .schedule-band.short, .schedule-band.very-short { display: flex; align-items: center; gap: .8mm; padding: 0 .7mm; text-align: left; line-height: 1; }
.ceremony-block.short strong, .ceremony-block.very-short strong, .schedule-band.short strong, .schedule-band.very-short strong { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ceremony-block.short .block-time, .ceremony-block.very-short .block-time, .schedule-band.short .block-time, .schedule-band.very-short .block-time { flex: none; margin-left: auto; white-space: nowrap; font-size: 5pt; }
.ceremony-block.very-short, .schedule-band.very-short { font-size: 5pt; }
.schedule-band { z-index: 1; background: #f8fafc; border: .2mm dashed #94a3b8; color: #475569; font-size: 6pt; }
.schedule-band.break { background: #f1f5f9; }
.schedule-band.elective { background: #fffbeb; border-color: #f59e0b; color: #92400e; }
.schedule-band.science-core { background: #f5f3ff; border-color: #8b5cf6; color: #5b21b6; }
.schedule-band.official-activity { background: #ede9fe; border-color: #7c3aed; color: #4c1d95; font-size: 7pt; }
.class-entry { background: #dbeafe; border: .25mm solid #60a5fa; color: #1e3a8a; font-size: 6.3pt; }
.entry-main { position: relative; min-width: 0; }
.class-entry strong { font-size: 7pt; }
.class-entry .faculty-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.class-entry small { margin: .3mm 0; white-space: nowrap; font-size: 5.7pt; font-weight: 700; }
.class-entry.short { display: flex; flex-direction: column; justify-content: center; padding: .15mm .6mm; line-height: 1; }
.class-entry.short .entry-main { display: flex; align-items: center; gap: .8mm; }
.class-entry.short .entry-main strong { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 6.1pt; }
.class-entry.short .entry-main small { flex: none; margin: 0 0 0 auto; font-size: 5.1pt; }
.class-entry.short .faculty-name { margin-top: .25mm; font-size: 4.8pt; line-height: 1; }
.class-entry.very-short { display: flex; align-items: center; padding: 0 .6mm; }
.class-entry.very-short .entry-main { display: flex; width: 100%; align-items: center; gap: .6mm; }
.class-entry.very-short .entry-main strong { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 5.4pt; }
.class-entry.very-short .entry-main small { flex: none; margin: 0 0 0 auto; font-size: 4.8pt; }
.class-entry.very-short .faculty-name { display: none; }
.empty-grade { grid-column: 1 / -1; padding: 20mm; text-align: center; font-size: 9pt; color: #64748b; }
.signatories { display: grid; grid-template-columns: 1fr 1fr; gap: 35mm; margin: 3mm 20mm 0; }
.signatories > div { display: flex; min-height: 14mm; flex-direction: column; justify-content: flex-end; text-align: center; font-size: 6.5pt; }
.signatories span { align-self: flex-start; color: #475569; }
.signatories strong { border-bottom: .2mm solid #334155; padding-bottom: .6mm; font-size: 8pt; }
.signatories small { padding-top: .5mm; color: #475569; }
.adjusted-badge { position: absolute; top: .3mm; right: .6mm; z-index: 3; font-size: 6.5pt; font-weight: 800; color: #4338ca; }
.manual-adjustment-note { position: absolute; bottom: 15mm; left: 0; margin: 0; font-size: 5.6pt; font-style: italic; color: #4338ca; }
.draft-watermark { position: absolute; z-index: 20; top: 77mm; left: 55mm; transform: rotate(-28deg); font-size: 38pt; font-weight: 800; letter-spacing: .12em; color: rgba(220, 38, 38, .13); pointer-events: none; }
@page { size: A4 landscape; margin: 0; }
@media print {
  html, body { width: 297mm; margin: 0; padding: 0; background: white; }
  .adjusted-sheet { margin: 0; box-shadow: none; break-after: page; page-break-after: always; }
  .adjusted-sheet:last-of-type { break-after: avoid; page-break-after: avoid; }
}
</style>
