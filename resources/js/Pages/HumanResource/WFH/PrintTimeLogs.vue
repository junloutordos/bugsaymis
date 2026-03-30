<template>
  <Head :title="`WFH Time Logs — ${employee.name} — ${monthLabel}`" />

  <div id="wfh-tl-root">
    <table id="wfh-tl-wrap">

      <!-- Repeating header -->
      <thead>
        <tr><td id="wfh-tl-head">
          <img src="/images/report_header.jpeg" style="width:100%; display:block;" />
        </td></tr>
      </thead>

      <!-- Repeating footer -->
      <tfoot>
        <tr><td id="wfh-tl-foot">
          <img src="/images/report_footer.jpeg" style="width:100%; display:block;" />
        </td></tr>
      </tfoot>

      <!-- Body -->
      <tbody>
        <tr><td id="wfh-tl-body">

          <!-- Report title -->
          <div style="text-align:center; margin:10px 0 12px;">
            <h2 style="font-size:13pt; font-weight:bold; letter-spacing:1px; margin:0;">WORK FROM HOME TIME LOG</h2>
            <p style="margin:4px 0 0; font-size:10pt;">{{ monthLabel }}</p>
          </div>

          <!-- Employee info -->
          <table class="tl-info-table">
            <thead>
              <tr>
                <th>NAME OF THE PERSONNEL</th>
                <th>POSITION</th>
                <th>PERIOD COVERED</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>{{ employee.name?.toUpperCase() }}</td>
                <td>{{ employee.position ?? '—' }}</td>
                <td>{{ monthLabel }}</td>
              </tr>
            </tbody>
          </table>

          <!-- Time log table -->
          <table class="tl-table">
            <thead>
              <tr>
                <th class="tl-col-date">Date</th>
                <th class="tl-col-day">Day</th>
                <th class="tl-col-time">Time In</th>
                <th class="tl-col-time">Break Out</th>
                <th class="tl-col-time">Break In</th>
                <th class="tl-col-time">Time Out</th>
                <th class="tl-col-dur">Work Duration</th>
                <th class="tl-col-status">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="rec in records" :key="rec.id" class="tl-data-row">
                <td class="tl-col-date">{{ fmtDate(rec.date) }}</td>
                <td class="tl-col-day">{{ fmtDow(rec.date) }}</td>
                <td class="tl-col-time tl-time-in">{{ fmtTime(rec.time_in) }}</td>
                <td class="tl-col-time">{{ fmtTime(rec.break_out) }}</td>
                <td class="tl-col-time">{{ fmtTime(rec.break_in) }}</td>
                <td class="tl-col-time tl-time-out">{{ fmtTime(rec.time_out) }}</td>
                <td class="tl-col-dur">{{ calcDuration(rec.time_in, rec.time_out, rec.break_out, rec.break_in) }}</td>
                <td class="tl-col-status">
                  <span :class="rec.time_out ? 'tl-badge-complete' : 'tl-badge-partial'">
                    {{ rec.time_out ? 'Complete' : 'In Progress' }}
                  </span>
                </td>
              </tr>
              <tr v-if="!records.length">
                <td colspan="8" class="tl-no-data">No WFH records for this period.</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="tl-total-row">
                <td colspan="6" style="text-align:right; padding-right:6px;">Total WFH Days:</td>
                <td colspan="2">{{ records.length }} day(s)</td>
              </tr>
            </tfoot>
          </table>

          <!-- Certification -->
          <p class="tl-certify">
            I certify that the above is a true and correct record of my Work From Home attendance.
          </p>

          <!-- Signature row -->
          <div class="tl-sig-row">
            <!-- Employee -->
            <div class="tl-sig-box">
              <div class="tl-sig-line">
                <span class="tl-sig-name">{{ employee.name?.toUpperCase() }}</span>
              </div>
              <div class="tl-sig-sub">{{ employee.position ?? 'Employee' }}</div>
            </div>

            <!-- Division Chief -->
            <div class="tl-sig-box">
              <div class="tl-sig-line">
                <span v-if="chiefName" class="tl-sig-name">{{ chiefName.toUpperCase() }}</span>
              </div>
              <div class="tl-sig-sub">{{ chiefPosition ?? 'Division Chief' }}</div>
              <div v-if="divisionName" class="tl-sig-division">{{ divisionName }}</div>
            </div>
          </div>

        </td></tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  employee:      Object,
  records:       Array,
  month:         String,
  chiefName:     { type: String, default: null },
  chiefPosition: { type: String, default: null },
  divisionName:  { type: String, default: null },
})

const [yr, mo] = props.month.split('-').map(Number)

const monthLabel = computed(() =>
  new Date(yr, mo - 1, 1).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })
)

function parseDate(d) {
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

#wfh-tl-root {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 9.5pt;
  color: #000;
}

#wfh-tl-wrap {
  width: 100%;
  border-collapse: collapse;
}

#wfh-tl-head,
#wfh-tl-foot {
  padding: 0 0.75in;
}

#wfh-tl-body {
  padding: 10px 0.75in;
  vertical-align: top;
}

/* ── Info table ── */
.tl-info-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10px;
}
.tl-info-table th,
.tl-info-table td {
  border: 1.5px solid #000;
  padding: 4px 6px;
  text-align: center;
  font-size: 9pt;
}
.tl-info-table th { font-weight: 700; background: #f5f5f5; }
.tl-info-table td { font-weight: 600; }

/* ── Time log table ── */
.tl-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 6px;
}
.tl-table th,
.tl-table td {
  border: 1.5px solid #000;
  padding: 3px 5px;
  text-align: center;
  font-size: 9pt;
  line-height: 1.3;
}
.tl-table th { font-weight: 700; background: #f0f0f0; }

.tl-col-date   { width: 90px; text-align: left !important; }
.tl-col-day    { width: 32px; }
.tl-col-time   { width: 65px; }
.tl-col-dur    { width: 60px; }
.tl-col-status { width: 68px; }

.tl-time-in    { color: #065f46; }
.tl-time-out   { color: #1e40af; }

.tl-badge-complete { color: #065f46; font-weight: 600; }
.tl-badge-partial  { color: #92400e; font-weight: 600; }

.tl-total-row td {
  font-weight: 700;
  font-size: 9pt;
  border-top: 2px solid #000;
}

.tl-no-data { color: #aaa; padding: 14px !important; }

/* ── Certification ── */
.tl-certify {
  font-size: 9pt;
  margin: 14px 0 6px;
  line-height: 1.5;
}

/* ── Signatures ── */
.tl-sig-row {
  display: flex;
  gap: 60px;
  margin-top: 10px;
}
.tl-sig-box  { min-width: 220px; }
.tl-sig-line {
  border-bottom: 1px solid #000;
  min-height: 30px;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding-bottom: 2px;
  margin-bottom: 3px;
}
.tl-sig-name     { font-weight: 700; font-size: 9.5pt; text-decoration: underline; }
.tl-sig-sub      { font-size: 8.5pt; text-align: center; }
.tl-sig-division { font-size: 8pt; color: #555; text-align: center; margin-top: 1px; }

/* ── Print ── */
@page { margin: 0.25in 0 0 0; }

@media print {
  body          { margin: 0; }
  .tl-data-row  { break-inside: avoid; page-break-inside: avoid; }
}
</style>
