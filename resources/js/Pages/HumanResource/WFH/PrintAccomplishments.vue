<template>
  <Head :title="`Accomplishment Report — ${employee.name}`" />

  <div id="wfh-print-root">
    <table id="wfh-pt-wrap">

      <!-- Repeating header on every page -->
      <thead>
        <tr><td id="wfh-pt-head">
          <img src="/images/report_header.jpeg" style="width:100%; display:block;" />
        </td></tr>
      </thead>

      <!-- Repeating footer on every page -->
      <tfoot>
        <tr><td id="wfh-pt-foot">
          <img src="/images/report_footer.jpeg" style="width:100%; display:block;" />
        </td></tr>
      </tfoot>

      <!-- Body -->
      <tbody>
        <tr><td id="wfh-pt-body">

          <!-- Report title -->
          <div style="text-align:center; margin:10px 0 12px;">
            <h2 style="font-size:13pt; font-weight:bold; letter-spacing:1px; margin:0;">ACCOMPLISHMENT REPORT</h2>
          </div>

          <!-- Personnel info table -->
          <table class="wfh-info-table">
            <thead>
              <tr>
                <th>NAME OF THE PERSONNEL</th>
                <th>UNIT/DIVISION</th>
                <th>DATES</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>{{ employee.name?.toUpperCase() }}</td>
                <td>{{ division?.division_name ?? '—' }}</td>
                <td>{{ dateLabel }}</td>
              </tr>
            </tbody>
          </table>

          <!-- No records -->
          <div v-if="!records.length" class="wfh-no-data">
            No accomplishments recorded for the selected period.
          </div>

          <!-- Accomplishments table -->
          <template v-else>
            <table class="wfh-main-table">
              <thead>
                <tr>
                  <th class="wfh-col-date">DATE</th>
                  <th class="wfh-col-time">Time</th>
                  <th class="wfh-col-acc">DETAILED ACCOMPLISHMENTS</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="rec in records" :key="rec.id">
                  <tr v-for="(acc, idx) in rec.accomplishments" :key="acc.id" class="wfh-data-row">
                    <!-- DATE — spans all rows for this day -->
                    <td v-if="idx === 0"
                        :rowspan="rec.accomplishments.length"
                        class="wfh-col-date wfh-date-cell">
                      {{ fmtDate(rec.date) }}
                    </td>
                    <!-- Time range -->
                    <td class="wfh-col-time">
                      <span v-if="acc.time_from || acc.time_to">
                        {{ acc.time_from ? fmtTime(acc.time_from) : '' }} - {{ acc.time_to ? fmtTime(acc.time_to) : '' }}
                      </span>
                      <span v-else>&mdash;</span>
                    </td>
                    <!-- Accomplishment text -->
                    <td class="wfh-col-acc">{{ acc.description || acc.title }}</td>
                  </tr>
                </template>
              </tbody>
            </table>

            <p class="wfh-note">
              <em>Note: The time may be modified according to your schedule, as long as tasks are properly accounted for in hourly basis.</em>
            </p>
          </template>

          <!-- Signatures -->
          <div class="wfh-sig-section">
            <p class="wfh-sig-top">Prepared by:</p>
            <div class="wfh-sig-single">
              <div class="wfh-sig-name">{{ employee.name?.toUpperCase() }}</div>
              <div class="wfh-sig-sub">{{ employee.position ?? 'Personnel' }}</div>
            </div>
          </div>

          <div v-if="!isOcdDivision" class="wfh-sig-section">
            <p class="wfh-sig-top">Monitored &amp; Reviewed by:</p>
            <div class="wfh-sig-row">
              <div class="wfh-sig-box">
                <div class="wfh-sig-line">
                  <span v-if="office?.unit_head_name" class="wfh-sig-name-inline">{{ office.unit_head_name.toUpperCase() }}</span>
                </div>
                <div class="wfh-sig-sub">Academic/ Unit Head</div>
                <div v-if="office?.name" class="wfh-sig-office">{{ office.name }}</div>
              </div>
              <div class="wfh-sig-box">
                <div class="wfh-sig-line">
                  <span v-if="division?.chief_name" class="wfh-sig-name-inline">{{ division.chief_name.toUpperCase() }}</span>
                </div>
                <div class="wfh-sig-sub">Division Chief</div>
                <div v-if="division?.division_name" class="wfh-sig-office">{{ division.division_name }}</div>
              </div>
            </div>
          </div>

          <div class="wfh-sig-section">
            <p class="wfh-sig-top">Approved by:</p>
            <div class="wfh-sig-single">
              <div class="wfh-sig-name">{{ approvedBy?.name?.toUpperCase() ?? '—' }}</div>
              <div class="wfh-sig-sub">{{ approvedBy?.position ?? 'Campus Director' }}</div>
            </div>
          </div>

        </td></tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  employee:      Object,
  division:      { type: Object, default: null },
  office:        { type: Object, default: null },
  approvedBy:    { type: Object, default: null },
  isOcdDivision: { type: Boolean, default: false },
  records:       Array,
  mode:          { type: String, default: 'monthly' },
  dateLabel:     { type: String, default: '' },
})

function fmtDate(d) {
  if (!d) return '—'
  const [y, m, day] = String(d).slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, day).toLocaleDateString('en-PH', {
    month: 'long', day: 'numeric', year: 'numeric',
  })
}

function fmtTime(t) {
  if (!t) return ''
  const [h, m] = t.split(':').map(Number)
  const suffix = h >= 12 ? 'PM' : 'AM'
  const hh = h % 12 || 12
  return `${hh}:${String(m).padStart(2, '0')} ${suffix}`
}

onMounted(() => setTimeout(() => window.print(), 400))
</script>

<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #fff; }

#wfh-print-root {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 9.5pt;
  color: #000;
}

#wfh-pt-wrap {
  width: 100%;
  border-collapse: collapse;
}

#wfh-pt-head,
#wfh-pt-foot {
  padding: 0 0.75in;
}

#wfh-pt-body {
  padding: 10px 0.75in;
  vertical-align: top;
}

/* ── Personnel info table ── */
.wfh-info-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10px;
}
.wfh-info-table th,
.wfh-info-table td {
  border: 1.5px solid #000;
  padding: 4px 6px;
  text-align: center;
  font-size: 9pt;
}
.wfh-info-table th { font-weight: 700; background: #f5f5f5; }
.wfh-info-table td { font-weight: 600; }

/* ── Accomplishments table ── */
.wfh-main-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 4px;
}
.wfh-main-table th,
.wfh-main-table td {
  border: 1.5px solid #000;
  padding: 4px 6px;
  vertical-align: top;
  font-size: 9pt;
  line-height: 1.5;
}
.wfh-main-table th {
  font-weight: 700;
  text-align: center;
  background: #f5f5f5;
}

.wfh-col-date  { width: 18%; text-align: center; }
.wfh-col-time  { width: 18%; text-align: center; }
.wfh-col-acc   { width: 64%; }
.wfh-date-cell { font-weight: 700; vertical-align: middle; }

.wfh-note {
  font-size: 8pt;
  margin: 4px 0 18px;
  line-height: 1.5;
}
.wfh-no-data {
  padding: 20px;
  text-align: center;
  color: #aaa;
  font-size: 9pt;
  border: 1px solid #ccc;
  margin-bottom: 14px;
}

/* ── Signatures ── */
.wfh-sig-section { margin-bottom: 18px; }
.wfh-sig-top     { font-size: 9pt; margin-bottom: 22px; }

.wfh-sig-single  { display: inline-block; min-width: 220px; }
.wfh-sig-name {
  font-weight: 700;
  font-size: 10pt;
  text-decoration: underline;
  text-transform: uppercase;
  border-bottom: 1px solid #000;
  padding-bottom: 2px;
  margin-bottom: 3px;
}
.wfh-sig-sub { font-size: 8.5pt; }

.wfh-sig-row {
  display: flex;
  gap: 60px;
}
.wfh-sig-box    { min-width: 200px; }
.wfh-sig-line   {
  border-bottom: 1px solid #000;
  min-height: 30px;
  margin-bottom: 3px;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding-bottom: 2px;
}
.wfh-sig-name-inline {
  font-weight: 700;
  font-size: 9.5pt;
  text-decoration: underline;
  text-align: center;
}
.wfh-sig-office { font-size: 8pt; color: #555; margin-top: 1px; }

/* ── Print ── */
@page { margin: 0.25in 0 0 0; }

@media print {
  body           { margin: 0; }
  .wfh-data-row  { break-inside: avoid; page-break-inside: avoid; }
}
</style>
