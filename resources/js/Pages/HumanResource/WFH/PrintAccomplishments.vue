<template>
  <Head :title="`WFH Accomplishments — ${employee.name} — ${monthLabel}`" />

  <div class="print-root">

    <!-- Header -->
    <div class="report-header">
      <div class="report-title">WORK FROM HOME ACCOMPLISHMENT REPORT</div>
      <div class="report-subtitle">{{ monthLabel }}</div>
      <div class="report-emp">{{ employee.name?.toUpperCase() }}</div>
      <div class="report-pos">{{ employee.position ?? '' }}</div>
    </div>

    <!-- No records -->
    <div v-if="!records.length" class="no-data">
      No accomplishments recorded for this month.
    </div>

    <!-- Per-day sections -->
    <div v-for="rec in records" :key="rec.id" class="day-block">
      <div class="day-header">
        <span class="day-date">{{ fmtDate(rec.date) }}</span>
        <span class="day-times">
          Time In: {{ fmtTime(rec.time_in) }} &nbsp;|&nbsp; Time Out: {{ fmtTime(rec.time_out) }}
        </span>
      </div>

      <table class="acc-table">
        <thead>
          <tr>
            <th class="col-num">#</th>
            <th class="col-title">Accomplishment</th>
            <th class="col-desc">Description</th>
            <th class="col-proof">Proof</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(acc, i) in rec.accomplishments" :key="acc.id">
            <td class="col-num">{{ i + 1 }}</td>
            <td class="col-title">{{ acc.title }}</td>
            <td class="col-desc">{{ acc.description ?? '—' }}</td>
            <td class="col-proof">
              <span v-if="acc.proof_type === 'photo' && acc.google_drive_link">📷 Photo attached</span>
              <span v-else-if="acc.proof_type === 'link' && acc.proof_link">🔗 {{ acc.proof_link }}</span>
              <span v-else>—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Summary -->
    <div class="summary">
      Total WFH Days with Accomplishments: {{ records.length }} &nbsp;|&nbsp;
      Total Accomplishments: {{ totalAccomplishments }}
    </div>

    <!-- Certification -->
    <div class="certify">
      I certify that the above accomplishments were completed during my Work From Home arrangement.
    </div>

    <!-- Signature -->
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

const totalAccomplishments = computed(() =>
  props.records.reduce((s, r) => s + (r.accomplishments?.length ?? 0), 0)
)

const printedAt = computed(() => {
  const n = new Date(), p = v => String(v).padStart(2, '0')
  return `${n.getFullYear()}-${p(n.getMonth()+1)}-${p(n.getDate())} ${p(n.getHours())}:${p(n.getMinutes())}:${p(n.getSeconds())}`
})

function fmtDate(d) {
  if (!d) return '—'
  const [y, m, day] = String(d).slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, day).toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
}

function fmtTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', hour12: true })
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

.report-header   { text-align: center; margin-bottom: 10px; }
.report-title    { font-size: 13px; font-weight: 700; letter-spacing: .5px; }
.report-subtitle { font-size: 10px; color: #444; margin-top: 1px; }
.report-emp      { font-size: 11px; font-weight: 700; margin-top: 4px; }
.report-pos      { font-size: 9px; color: #555; }

.day-block  { margin-bottom: 10px; page-break-inside: avoid; }
.day-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f0f0f0;
  border: 1px solid #ccc;
  padding: 3px 6px;
  font-size: 9px;
  font-weight: 700;
  margin-bottom: 0;
}
.day-date  { font-size: 9.5px; }
.day-times { font-size: 8px; color: #555; font-weight: 400; }

.acc-table { width: 100%; border-collapse: collapse; }
.acc-table th,
.acc-table td {
  border: 1px solid #ccc;
  padding: 3px 5px;
  font-size: 8.5px;
  vertical-align: top;
  line-height: 1.4;
}
.acc-table th { font-weight: 700; background: #fafafa; text-align: center; }

.col-num   { width: 18px; text-align: center; }
.col-title { width: 30%; font-weight: 600; }
.col-desc  { width: 40%; }
.col-proof { width: 20%; font-size: 8px; word-break: break-all; }

.no-data { padding: 20px; text-align: center; color: #aaa; font-size: 9px; }

.summary {
  margin: 8px 0 4px;
  font-size: 8.5px;
  font-weight: 700;
  border-top: 1.5px solid #000;
  padding-top: 4px;
}

.certify { font-size: 8px; margin: 8px 0 6px; line-height: 1.5; }

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
  .day-block  { page-break-inside: avoid; }
}
</style>
