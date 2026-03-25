<template>
  <Head :title="`WFH Time Logs — ${employee.name} — ${monthLabel}`" />

  <div class="print-root">

    <!-- Header -->
    <div class="report-header">
      <div class="report-title">WORK FROM HOME TIME LOG</div>
      <div class="report-subtitle">{{ monthLabel }}</div>
      <div class="report-emp">{{ employee.name?.toUpperCase() }}</div>
      <div class="report-pos">{{ employee.position ?? '' }}</div>
    </div>

    <!-- Table -->
    <table class="log-table">
      <thead>
        <tr>
          <th class="col-date">Date</th>
          <th class="col-day">Day</th>
          <th class="col-time">Time In</th>
          <th class="col-time">Break Out</th>
          <th class="col-time">Break In</th>
          <th class="col-time">Time Out</th>
          <th class="col-dur">Work Duration</th>
          <th class="col-status">Status</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="rec in records" :key="rec.id">
          <td class="col-date">{{ fmtDate(rec.date) }}</td>
          <td class="col-day">{{ fmtDow(rec.date) }}</td>
          <td class="col-time time-in">{{ fmtTime(rec.time_in) }}</td>
          <td class="col-time">{{ fmtTime(rec.break_out) }}</td>
          <td class="col-time">{{ fmtTime(rec.break_in) }}</td>
          <td class="col-time time-out">{{ fmtTime(rec.time_out) }}</td>
          <td class="col-dur">{{ calcDuration(rec.time_in, rec.time_out, rec.break_out, rec.break_in) }}</td>
          <td class="col-status">
            <span :class="rec.time_out ? 'badge-complete' : 'badge-partial'">
              {{ rec.time_out ? 'Complete' : 'In Progress' }}
            </span>
          </td>
        </tr>
        <tr v-if="!records.length">
          <td colspan="8" class="no-data">No WFH records for this month.</td>
        </tr>
      </tbody>
      <tfoot>
        <tr class="total-row">
          <td colspan="6" style="text-align:right; padding-right:6px;">Total WFH Days:</td>
          <td colspan="2">{{ records.length }} day(s)</td>
        </tr>
      </tfoot>
    </table>

    <!-- Certification -->
    <div class="certify">
      I certify that the above is a true and correct record of my Work From Home attendance.
    </div>

    <!-- Signature row -->
    <div class="sig-row">
      <div class="sig-box">
        <div class="sig-line">
          <span class="sig-name">{{ employee.name }}</span>
        </div>
        <div class="sig-label">{{ employee.position ?? 'Employee' }}</div>
      </div>
    </div>

    <!-- Meta -->
    <div class="meta">Date &amp; Time Printed: {{ printedAt }}</div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  employee: Object,
  records:  Array,
  month:    String,
})

const [yr, mo] = props.month.split('-').map(Number)

const monthLabel = computed(() =>
  new Date(yr, mo - 1, 1).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })
)

const printedAt = computed(() => {
  const n = new Date(), p = v => String(v).padStart(2, '0')
  return `${n.getFullYear()}-${p(n.getMonth()+1)}-${p(n.getDate())} ${p(n.getHours())}:${p(n.getMinutes())}:${p(n.getSeconds())}`
})

function parseDate(d) {
  // Parse YYYY-MM-DD parts directly → no UTC/timezone conversion
  const [y, m, day] = String(d).slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, day)
}

function fmtDate(d) {
  if (!d) return '—'
  return parseDate(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
}

function fmtDow(d) {
  if (!d) return ''
  return parseDate(d).toLocaleDateString('en-PH', { weekday: 'short' })
}

function fmtTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', hour12: true })
}

function calcDuration(inIso, outIso, breakOut, breakIn) {
  if (!inIso || !outIso) return '—'
  let ms = new Date(outIso) - new Date(inIso)
  if (breakOut && breakIn) ms -= (new Date(breakIn) - new Date(breakOut))
  if (ms < 0) return '—'
  const h = Math.floor(ms / 3600000)
  const m = Math.floor((ms % 3600000) / 60000)
  return `${h}h ${m}m`
}

onMounted(() => setTimeout(() => window.print(), 400))
</script>

<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #fff; }

.print-root {
  font-family: Arial, Helvetica, sans-serif;
  color: #000;
  padding: 10mm;
  font-size: 9px;
}

.report-header   { text-align: center; margin-bottom: 8px; }
.report-title    { font-size: 13px; font-weight: 700; letter-spacing: .5px; }
.report-subtitle { font-size: 10px; color: #444; margin-top: 1px; }
.report-emp      { font-size: 11px; font-weight: 700; margin-top: 4px; }
.report-pos      { font-size: 9px; color: #555; }

.log-table { width: 100%; border-collapse: collapse; margin: 8px 0; }
.log-table th,
.log-table td {
  border: 1px solid #000;
  padding: 2px 4px;
  text-align: center;
  font-size: 8.5px;
  line-height: 1.3;
}
.log-table th { font-weight: 700; background: #f0f0f0; }

.col-date   { width: 80px; text-align: left !important; }
.col-day    { width: 28px; }
.col-time   { width: 56px; }
.col-dur    { width: 54px; }
.col-status { width: 60px; }

.time-in  { color: #065f46; }
.time-out { color: #1e40af; }

.badge-complete { color: #065f46; font-weight: 600; }
.badge-partial  { color: #92400e; font-weight: 600; }

.total-row td { font-weight: 700; font-size: 8px; border-top: 2px solid #000; }

.no-data { color: #aaa; padding: 12px !important; }

.certify { font-size: 8px; margin: 10px 0 6px; line-height: 1.5; }

.sig-row  { margin: 6px 0 4px; }
.sig-box  { display: inline-block; width: 55%; }
.sig-line {
  border-bottom: 1px solid #000;
  min-height: 22px;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding-bottom: 2px;
}
.sig-name  { font-weight: 700; font-size: 9px; text-decoration: underline; text-transform: uppercase; }
.sig-label { font-size: 8px; text-align: center; margin-top: 1px; }

.meta { font-size: 7px; color: #888; border-top: 1px solid #ddd; padding-top: 2px; margin-top: 6px; }

@media print {
  @page { size: A4 portrait; margin: 10mm; }
  body        { margin: 0; }
  .print-root { padding: 0; }
}
</style>
