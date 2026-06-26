<template>
  <Head :title="`DTR Checklist — ${employee.name} — ${monthLabel}`" />

  <div class="print-root">
    <div class="checklist-card">

      <!-- ── Header ─────────────────────────────────────────────── -->
      <div class="title-block">
        <div class="form-title">DTR CHECKLIST / MONITORING SLIP</div>
        <table class="header-table">
          <tr>
            <td class="header-label">Name:</td>
            <td class="header-value name-value">{{ employee.name?.toUpperCase() }}</td>
          </tr>
          <tr>
            <td class="header-label">For the period of:</td>
            <td class="header-value">{{ periodLabel }}</td>
          </tr>
        </table>
      </div>

      <!-- ── Official Time ──────────────────────────────────────── -->
      <table class="official-table">
        <thead>
          <tr>
            <th rowspan="2" class="ot-day"></th>
            <th colspan="4" class="ot-header">Official Time</th>
          </tr>
          <tr>
            <th class="ot-col">Time In</th>
            <th class="ot-col" colspan="2">Lunch Break</th>
            <th class="ot-col">Time Out</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(label, day) in dayLabels" :key="day">
            <td class="ot-day-cell">{{ label }}</td>
            <td class="ot-time-cell">{{ fmtTime(officialTimes[day]?.time_in) }}</td>
            <td class="ot-time-cell">{{ officialTimes[day] ? '12:00 PM' : '' }}</td>
            <td class="ot-time-cell">{{ officialTimes[day] ? '1:00 PM' : '' }}</td>
            <td class="ot-time-cell">{{ fmtTime(officialTimes[day]?.time_out) }}</td>
          </tr>
        </tbody>
      </table>

      <!-- ── Exam / Non-teaching / Shortened lines ─────────────── -->
      <div class="lines-section">
        <div class="line-row">Dates on Exam Days: <span class="underline-fill"></span></div>
        <div class="line-row">Dates on Non-teaching Days: <span class="underline-fill"></span></div>
        <div class="line-row">Dates on Shortened Periods: <span class="underline-fill"></span></div>
      </div>

      <!-- ── Biometric & Checkboxes ─────────────────────────────── -->
      <div class="section-title">IN THE DTR PRINTED FROM BIOMETRIC</div>
      <div class="checkbox-grid">
        <div class="checkbox-item"><span class="checkbox-mark">( / )</span> Saturday &amp; Sunday</div>
        <div class="checkbox-item"><span class="checkbox-mark">(  )</span> Days with OB/DO DAY/ HOLIDAY/ shortened periods</div>
        <div class="checkbox-item"><span class="checkbox-mark">(  )</span> Days with Absences/leaves</div>
        <div class="checkbox-item"><span class="checkbox-mark">(  )</span> Affixed my signature</div>
      </div>

      <!-- ── Attachments ────────────────────────────────────────── -->
      <div class="section-title">ATTACHMENTS (if any)</div>
      <div class="attach-grid">
        <div class="checkbox-item">(  ) Gate Pass</div>
        <div class="checkbox-item">(  ) Recommendation from HSU on home quarantine</div>
        <div class="checkbox-item">(  ) Approved Application for Leave</div>
        <div class="checkbox-item">(  ) Travel Order</div>
        <div class="checkbox-item">(  ) Certificate of appearance</div>
        <div class="checkbox-item">(  ) Medical Certificate</div>
        <div class="checkbox-item">(  ) Certificate of participation on trainings</div>
        <div class="checkbox-item">(  ) Other, please specify:</div>
        <div class="checkbox-item">(  ) Approved Alternative Work Arrangement</div>
        <div class="checkbox-item"><span class="underline-fill" style="width:140px;"></span></div>
      </div>

      <!-- ── Penned Entries Justification ──────────────────────── -->
      <table class="penned-table">
        <thead>
          <tr>
            <th colspan="3" class="penned-section-title">JUSTIFICATION of penned entries:</th>
          </tr>
          <tr>
            <th class="p-col-date">Date</th>
            <th class="p-col-time">Time</th>
            <th class="p-col-reason">Reason(s)</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(entry, i) in pennedEntries" :key="i">
            <td class="p-col-date">{{ entry.date }}</td>
            <td class="p-col-time">{{ entry.time ? fmtTime(entry.time) : '' }}</td>
            <td class="p-col-reason">{{ entry.reason }}</td>
          </tr>
          <!-- empty filler rows so the box always has some height -->
          <tr v-for="n in Math.max(0, 3 - pennedEntries.length)" :key="'e' + n">
            <td class="p-col-date">&nbsp;</td>
            <td class="p-col-time"></td>
            <td class="p-col-reason"></td>
          </tr>
        </tbody>
      </table>

      <!-- ── Attested By ─────────────────────────────────────────── -->
      <div class="attested-row">
        ATTESTED BY: <span class="attest-line"></span>
      </div>

      <!-- ── Declared ───────────────────────────────────────────── -->
      <table class="declared-table">
        <thead>
          <tr>
            <th colspan="4" class="declared-section-title">DECLARED THE FOLLOWING (if applicable only)</th>
          </tr>
          <tr>
            <th class="d-col-nature">NATURE (A: Absent<br>T: Tardy/Late/Undertime)</th>
            <th class="d-col-date">Date</th>
            <th class="d-col-mins">No. of Minutes for<br>(T) / No. of days for (A)</th>
            <th class="d-col-reason">Reason</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, i) in declaredRows" :key="i">
            <td class="d-col-nature text-center">{{ row.nature }}</td>
            <td class="d-col-date text-center">{{ row.date }}</td>
            <td class="d-col-mins text-center">{{ row.nature === 'A' ? '1' : row.minutes }}</td>
            <td class="d-col-reason"></td>
          </tr>
          <tr v-for="n in Math.max(0, 5 - declaredRows.length)" :key="'f' + n">
            <td class="d-col-nature">&nbsp;</td>
            <td class="d-col-date"></td>
            <td class="d-col-mins"></td>
            <td class="d-col-reason"></td>
          </tr>
        </tbody>
      </table>

      <!-- ── Totals ─────────────────────────────────────────────── -->
      <div class="totals-section">
        <div>TOTAL NO. OF DAYS ABSENT: <strong>{{ absentCount }} day(s)</strong></div>
        <div>TOTAL MINUTES TARDY/UNDERTIME: <strong>{{ totalTardy }} min(s)</strong></div>
        <div class="checkbox-item mt-4">(  ) PERFECT ATTENDANCE</div>
      </div>

      <!-- ── Signature ──────────────────────────────────────────── -->
      <div class="signature-row">
        <div class="sig-block">
          <div class="sig-name">{{ employee.name?.toUpperCase() }}</div>
          <div class="sig-line-under"></div>
          <div class="sig-caption">Signature / Date</div>
        </div>
      </div>

    </div><!-- end .checklist-card -->
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  employee:      Object,
  month:         String,
  date_from:     { type: String, default: null },
  date_to:       { type: String, default: null },
  officialTimes: Object,   // keyed by Mon/Tue/… → { time_in, time_out }
  pennedEntries: Array,    // [{ date, time, reason }]
  declaredRows:  Array,    // [{ nature, date, minutes, reason }]
  absentCount:   Number,
  totalTardy:    Number,
})

const [yr, mo] = props.month.split('-').map(Number)

const monthLabel = computed(() => {
  if (props.date_from && props.date_to) {
    const from = new Date(props.date_from + 'T00:00:00')
    const to   = new Date(props.date_to   + 'T00:00:00')
    const fmt  = { month: 'short', day: 'numeric', year: 'numeric' }
    return `${from.toLocaleDateString('en-PH', fmt)} – ${to.toLocaleDateString('en-PH', fmt)}`
  }
  return new Date(yr, mo - 1, 1).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })
})

const periodLabel = computed(() => {
  if (props.date_from && props.date_to) {
    const from = new Date(props.date_from + 'T00:00:00')
    const to   = new Date(props.date_to   + 'T00:00:00')
    const fmt  = { month: 'long', day: 'numeric', year: 'numeric' }
    return `${from.toLocaleDateString('en-PH', fmt)} – ${to.toLocaleDateString('en-PH', fmt)}`
  }
  const last  = new Date(yr, mo, 0).getDate()
  const mName = new Date(yr, mo - 1, 1).toLocaleDateString('en-PH', { month: 'long' })
  return `${mName} 1-${mName} ${last}, ${yr}`
})

const dayLabels = {
  Mon: 'Monday',
  Tue: 'Tuesday',
  Wed: 'Wednesday',
  Thu: 'Thursday',
  Fri: 'Friday',
}

function fmtTime(t) {
  if (!t) return ''
  const parts = String(t).slice(0, 5).split(':').map(Number)
  const h = parts[0], mMin = parts[1]
  const ampm = h >= 12 ? 'PM' : 'AM'
  const h12  = h % 12 || 12
  return `${h12}:${String(mMin).padStart(2, '0')} ${ampm}`
}

onMounted(() => setTimeout(() => window.print(), 400))
</script>

<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #fff; }

.print-root {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 9px;
  color: #000;
  padding: 8mm;
}

.checklist-card {
  border: 1px solid #000;
  padding: 8px 10px;
  max-width: 180mm;
  margin: 0 auto;
}

/* Header */
.title-block { margin-bottom: 6px; }
.form-title  { font-size: 11px; font-weight: 700; text-align: center; margin-bottom: 4px; }
.header-table { border-collapse: collapse; width: 100%; }
.header-label { font-weight: 700; white-space: nowrap; padding-right: 4px; font-size: 9px; }
.header-value { font-size: 9px; border-bottom: 1px solid #000; padding-bottom: 1px; }
.name-value   { font-weight: 700; }

/* Official Time table */
.official-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 5px;
  font-size: 8.5px;
}
.official-table th, .official-table td {
  border: 1px solid #000;
  padding: 2px 4px;
  text-align: center;
}
.official-table th { font-weight: 700; background: #f5f5f5; }
.ot-day { width: 20px; }
.ot-header { font-size: 9px; }
.ot-day-cell { text-align: left; padding-left: 4px; font-weight: 600; }
.ot-time-cell { }

/* Lines */
.lines-section { margin-bottom: 5px; font-size: 8.5px; }
.line-row       { display: flex; align-items: baseline; margin-bottom: 2px; gap: 3px; }
.underline-fill { flex: 1; border-bottom: 1px solid #000; }

/* Checkboxes */
.section-title {
  font-weight: 700;
  font-size: 8.5px;
  margin: 4px 0 2px;
  text-decoration: underline;
}
.checkbox-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 8px; margin-bottom: 4px; }
.attach-grid   { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 8px; margin-bottom: 4px; }
.checkbox-item { font-size: 8.5px; display: flex; align-items: baseline; gap: 3px; }
.checkbox-mark { font-family: monospace; }
.mt-4 { margin-top: 4px; }

/* Penned entries */
.penned-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 5px;
  font-size: 8.5px;
}
.penned-table th, .penned-table td {
  border: 1px solid #000;
  padding: 2px 4px;
}
.penned-section-title { font-weight: 700; text-align: left; background: #f5f5f5; }
.p-col-date   { width: 70px; }
.p-col-time   { width: 70px; }
.p-col-reason { }

/* Attested */
.attested-row {
  font-size: 8.5px;
  font-weight: 700;
  margin-bottom: 6px;
  display: flex;
  align-items: baseline;
  gap: 4px;
}
.attest-line {
  flex: 1;
  border-bottom: 1px solid #000;
  min-width: 100px;
}

/* Declared */
.declared-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 4px;
  font-size: 8.5px;
}
.declared-table th, .declared-table td {
  border: 1px solid #000;
  padding: 2px 4px;
}
.declared-section-title { font-weight: 700; text-align: left; background: #f5f5f5; }
.d-col-nature { width: 120px; }
.d-col-date   { width: 70px; }
.d-col-mins   { width: 90px; }
.d-col-reason { }
.text-center { text-align: center; }

/* Totals */
.totals-section { font-size: 8.5px; margin-bottom: 8px; line-height: 1.8; }

/* Signature */
.signature-row { display: flex; justify-content: flex-end; margin-top: 10px; }
.sig-block     { text-align: center; width: 160px; }
.sig-name      { font-weight: 700; font-size: 9px; }
.sig-line-under { border-bottom: 1px solid #000; margin: 2px 0; height: 20px; }
.sig-caption   { font-size: 7.5px; }

/* PRINT */
@media print {
  @page { size: A4 portrait; margin: 8mm; }
  body        { margin: 0; }
  .print-root { padding: 0; }
  .checklist-card { border-color: #000; max-width: 100%; }
}
</style>
