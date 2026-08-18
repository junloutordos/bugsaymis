<template>
  <Head :title="`Activity Report — ${activity.title}`" />

  <div id="ams-print-root">
    <table id="ams-pt-wrap">
      <thead>
        <tr><td id="ams-pt-head">
          <img src="/images/report_header.jpeg" style="width:100%; display:block;" />
        </td></tr>
      </thead>

      <tfoot>
        <tr><td id="ams-pt-foot">
          <img src="/images/report_footer.jpeg" style="width:100%; display:block;" />
        </td></tr>
      </tfoot>

      <tbody>
        <tr><td id="ams-pt-body">

          <div style="text-align:center; margin:10px 0 12px;">
            <h2 style="font-size:13pt; font-weight:bold; letter-spacing:1px; margin:0;">ACTIVITY ATTENDANCE &amp; EVALUATION REPORT</h2>
          </div>

          <table class="ams-info-table">
            <thead>
              <tr>
                <th>ACTIVITY</th>
                <th>DATE(S)</th>
                <th>VENUE</th>
                <th>PROPONENT</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>{{ activity.title }}</td>
                <td>{{ dateLabel }}</td>
                <td>{{ activity.venue ?? '—' }}</td>
                <td>{{ activity.proponent ?? '—' }}</td>
              </tr>
            </tbody>
          </table>

          <table class="ams-kpi-table">
            <tbody>
              <tr>
                <td>Invited: <strong>{{ report.kpis.invited }}</strong></td>
                <td>Present: <strong>{{ report.kpis.present }} ({{ report.kpis.attendance_rate }}%)</strong></td>
                <td>Evaluated: <strong>{{ report.kpis.evaluated }} ({{ report.kpis.evaluation_rate }}%)</strong></td>
                <td>Certificates Issued: <strong>{{ report.kpis.certificates_issued }}</strong></td>
              </tr>
            </tbody>
          </table>

          <table class="ams-main-table">
            <thead>
              <tr>
                <th class="ams-col-name">Name</th>
                <th class="ams-col-type">Type</th>
                <th class="ams-col-section">Section / Division</th>
                <th v-for="date in report.days" :key="date" class="ams-col-day">{{ fmtDayShort(date) }}</th>
                <th class="ams-col-status">Overall</th>
                <th class="ams-col-hours">Hours</th>
                <th class="ams-col-status">Evaluated</th>
                <th class="ams-col-status">Certificate</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in report.rows" :key="row.name + row.type">
                <td class="ams-col-name">{{ row.name }}</td>
                <td class="ams-col-type">{{ row.type }}</td>
                <td class="ams-col-section">{{ row.section ?? '—' }}</td>
                <td v-for="day in row.daily" :key="day.date" class="ams-col-day">{{ day.attended ? '✓' : '—' }}</td>
                <td class="ams-col-status">{{ row.attended ? 'Present' : 'Absent' }}</td>
                <td class="ams-col-hours">{{ row.hours_attended }}</td>
                <td class="ams-col-status">{{ row.evaluated ? 'Yes' : 'No' }}</td>
                <td class="ams-col-status">{{ row.certificate_issued ? 'Issued' : '—' }}</td>
              </tr>
              <tr v-if="!report.rows.length">
                <td :colspan="4 + report.days.length" style="text-align:center; padding:16px; color:#aaa;">No participants recorded.</td>
              </tr>
            </tbody>
          </table>

          <div class="ams-sig-section">
            <p class="ams-sig-top">Prepared by:</p>
            <div class="ams-sig-single">
              <div class="ams-sig-name">{{ activity.proponent?.toUpperCase() ?? '—' }}</div>
              <div class="ams-sig-sub">Activity Proponent</div>
            </div>
          </div>

          <div class="ams-sig-section">
            <p class="ams-sig-top">Noted by:</p>
            <div class="ams-sig-single">
              <div class="ams-sig-line"></div>
              <div class="ams-sig-sub">Evaluation Committee</div>
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
  activity: Object,
  report:   Object,
})

const dateLabel = computed(() => {
  const start = fmtDay(props.activity.start_date)
  const end = fmtDay(props.activity.end_date)
  return props.activity.start_date === props.activity.end_date ? start : `${start} – ${end}`
})

function fmtDay(d) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}
function fmtDayShort(d) {
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })
}

onMounted(() => setTimeout(() => window.print(), 400))
</script>

<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #fff; }

#ams-print-root {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 9pt;
  color: #000;
}

#ams-pt-wrap { width: 100%; border-collapse: collapse; }
#ams-pt-head, #ams-pt-foot { padding: 0 0.75in; }
#ams-pt-body { padding: 10px 0.75in; vertical-align: top; }

.ams-info-table, .ams-kpi-table, .ams-main-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10px;
}
.ams-info-table th, .ams-info-table td,
.ams-main-table th, .ams-main-table td {
  border: 1.5px solid #000;
  padding: 4px 6px;
  font-size: 8.5pt;
}
.ams-info-table th { font-weight: 700; background: #f5f5f5; text-align: center; }
.ams-main-table th { font-weight: 700; background: #f5f5f5; text-align: center; }
.ams-col-day, .ams-col-status, .ams-col-hours { text-align: center; white-space: nowrap; }
.ams-col-type { text-align: center; }

.ams-kpi-table td { border: 1px solid #ccc; padding: 5px 8px; font-size: 8.5pt; text-align: center; }

.ams-sig-section { margin: 18px 0; }
.ams-sig-top { font-size: 9pt; margin-bottom: 22px; }
.ams-sig-single { display: inline-block; min-width: 220px; }
.ams-sig-name {
  font-weight: 700; font-size: 10pt; text-decoration: underline; text-transform: uppercase;
  border-bottom: 1px solid #000; padding-bottom: 2px; margin-bottom: 3px;
}
.ams-sig-line { border-bottom: 1px solid #000; min-height: 30px; margin-bottom: 3px; }
.ams-sig-sub { font-size: 8.5pt; }

@page { margin: 0.25in 0 0 0; }
@media print {
  body { margin: 0; }
  tr { break-inside: avoid; page-break-inside: avoid; }
}
</style>
