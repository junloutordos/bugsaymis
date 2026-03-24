<template>
  <Head :title="`DTR — ${employee.name} — ${monthLabel}`" />

  <div class="print-root">
    <div class="copy-pair">
      <div v-for="copy in 2" :key="copy" class="dtr-card">

        <!-- ── Header ───────────────────────────────── -->
        <div class="form-no">CIVIL SERVICE FORM NO. 48</div>
        <div class="form-title">Daily Time Record</div>
        <div class="emp-name">{{ employee.name?.toUpperCase() }}</div>
        <div class="emp-period">For the period of: {{ periodLabel }}</div>

        <!-- ── Main Table ────────────────────────────── -->
        <table class="dtr-table">
          <thead>
            <tr>
              <th rowspan="2" class="col-day">Day</th>
              <th colspan="2">AM</th>
              <th colspan="2">PM</th>
              <th colspan="2">OverTime</th>
              <th colspan="2">Tardy/<br>UnderTime</th>
            </tr>
            <tr>
              <th class="col-time">Time In</th>
              <th class="col-time">Time Out</th>
              <th class="col-time">Time In</th>
              <th class="col-time">Time Out</th>
              <th class="col-time">Time In</th>
              <th class="col-time">Time Out</th>
              <th class="col-num">Hrs.</th>
              <th class="col-num">Mins.</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="day in daysInMonth" :key="day">
              <td class="col-day">{{ String(day).padStart(2,'0') }}</td>

              <!-- Spanning cell for weekends / holidays -->
              <template v-if="spanLabel(records[ds(day)], day)">
                <td colspan="8" class="span-cell">
                  {{ spanLabel(records[ds(day)], day) }}
                </td>
              </template>

              <!-- Regular work day -->
              <template v-else>
                <td v-for="f in ['time_in_am','time_out_am','time_in_pm','time_out_pm']" :key="f"
                    :style="cellStyle(records[ds(day)], f)">
                  {{ cellText(records[ds(day)], f) }}
                </td>
                <td></td><!-- OT In -->
                <td></td><!-- OT Out -->
                <td>{{ utH(records[ds(day)]) }}</td>
                <td>{{ utM(records[ds(day)]) }}</td>
              </template>
            </tr>

            <!-- Total -->
            <tr class="total-row">
              <td colspan="7" style="text-align:right; padding-right:4px;">TOTAL</td>
              <td colspan="2" style="font-size:7px;">
                {{ totalTardyHours }} hour(s) {{ totalTardyMinutes }} min(s)
              </td>
            </tr>
          </tbody>
        </table>

        <!-- ── Legend ───────────────────────────────── -->
        <div class="legend">
          Legend: [T]-Travel &nbsp; [L]-Leave &nbsp; [A]-Absent &nbsp; [OB]-Official Business
        </div>

        <!-- ── Certification ────────────────────────── -->
        <div class="certify">
          I CERTIFY on my honor that the above is a true and correct report of the hours of work
          performed, record of which was made daily at the time of arrival at and departure from office
        </div>

        <!-- ── Employee Signature ───────────────────── -->
        <div class="sig-row supervisor-row">
          <div class="sig-box">
            <div class="sig-line">
              <span class="sig-name sup-name">{{ employee.name }}</span>
            </div>
            <div class="sig-label sup-label">{{ empPosition }}</div>
          </div>
        </div>

        <!-- ── Verified / Supervisor ────────────────── -->
        <div class="verify-label">Verified as to the prescribed office hours</div>
        <div class="sig-row supervisor-row" style="margin-top:6px;">
          <div class="sig-box">
            <div class="sig-line"></div>
            <div v-if="supervisor" class="sig-name sup-name" style="margin-top:1px;">{{ supervisor.name }}</div>
            <div v-if="supervisor" class="sig-label sup-label">{{ supervisor.position }}</div>
          </div>
        </div>

        <!-- ── Meta ─────────────────────────────────── -->
        <div class="meta">
          Date &amp; Time Printed: {{ printedAt }}
        </div>

      </div><!-- end .dtr-card -->
    </div><!-- end .copy-pair -->
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  employee:          Object,
  records:           Object,   // keyed by YYYY-MM-DD
  month:             String,
  holidays:          Object,   // keyed by YYYY-MM-DD → { name, type }
  supervisor:        Object,
  totalTardyHours:   Number,
  totalTardyMinutes: Number,
})

const [yr, mo] = props.month.split('-').map(Number)

const daysInMonth = computed(() => new Date(yr, mo, 0).getDate())

const monthLabel = computed(() =>
  new Date(yr, mo - 1, 1).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })
)

const periodLabel = computed(() => {
  const last  = daysInMonth.value
  const mName = new Date(yr, mo - 1, 1).toLocaleDateString('en-PH', { month: 'long' })
  return `${mName} 1-${mName} ${last}, ${yr}`
})

const printedAt = computed(() => {
  const n = new Date()
  const p = v => String(v).padStart(2, '0')
  return `${n.getFullYear()}-${p(n.getMonth()+1)}-${p(n.getDate())} ` +
         `${p(n.getHours())}:${p(n.getMinutes())}:${p(n.getSeconds())}`
})

const empPosition = computed(() =>
  props.employee?.employeeProfile?.position ?? props.employee?.position ?? ''
)

// ── Helpers ────────────────────────────────────────────────────────

function ds(day) {
  return `${yr}-${String(mo).padStart(2,'0')}-${String(day).padStart(2,'0')}`
}

function dow(day) {
  return new Date(ds(day) + 'T00:00:00').getDay() // 0=Sun, 6=Sat
}

function fmtTime12(t) {
  if (!t) return ''
  const [h, m] = String(t).slice(0, 5).split(':').map(Number)
  const ampm = h >= 12 ? 'PM' : 'AM'
  const h12  = h % 12 || 12
  return `${String(h12).padStart(2,'0')}:${String(m).padStart(2,'0')} ${ampm}`
}

function spanLabel(rec, day) {
  const date = ds(day)
  const h = props.holidays?.[date]

  if (rec) {
    const dt = rec.day_type
    if (dt === 'rest_day')         return dow(day) === 0 ? 'SUNDAY' : 'SATURDAY'
    if (dt === 'rest_day_holiday') return h?.name ?? 'Holiday (Rest Day)'
    if (dt === 'holiday_regular')  return h?.name ?? 'Regular Holiday'
    if (dt === 'holiday_special')  return h?.name ?? 'Special Non-Working Holiday'
    return null
  }

  if (h) return h.name
  const d = dow(day)
  if (d === 0) return 'SUNDAY'
  if (d === 6) return 'SATURDAY'
  return null
}

function cellText(rec, field) {
  if (!rec) return ''
  const val = rec[field] || rec['penned_' + field]
  if (val) return fmtTime12(val)

  const s = rec.attendance_status
  const r = (rec.remarks || '').toUpperCase()

  if (s === 'absent')               return 'A'
  if (s === 'on_official_business') return 'OB'
  if (s === 'on_leave') {
    const lt = rec.leave_application?.leave_type?.name ?? ''
    return lt.toLowerCase().includes('travel') ? 'T' : 'L'
  }
  if (r.includes('WFH')) return 'WFH'
  if (r.includes('OB'))  return 'OB'
  return ''
}

function cellStyle(rec, field) {
  if (!rec) return {}
  const bio    = rec[field]
  const penned = rec['penned_' + field]
  if (!bio && penned) return { color: 'red' }
  if (!bio && !penned) {
    const s = rec.attendance_status
    if (s === 'absent' || s === 'on_official_business') return { color: 'red' }
    if (s === 'on_leave') return { color: '#CC7700' }
  }
  return {}
}

function utH(rec) {
  if (!rec || !rec.undertime_minutes) return ''
  const h = Math.floor(rec.undertime_minutes / 60)
  return h > 0 ? h : ''
}
function utM(rec) {
  if (!rec || !rec.undertime_minutes) return ''
  const m = rec.undertime_minutes % 60
  return m > 0 ? m : ''
}

onMounted(() => setTimeout(() => window.print(), 400))
</script>

<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #fff; }

.print-root {
  font-family: Arial, Helvetica, sans-serif;
  color: #000;
  padding: 6mm;
}

/* Two-column layout — portrait A4 */
.copy-pair {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6mm;
}

/* Individual DTR card */
.dtr-card { border: 1px solid #000; padding: 5px 7px; font-size: 8px; }

.form-no    { font-size: 7px; font-style: italic; }
.form-title { font-size: 11px; font-weight: 700; text-align: center; margin: 2px 0; }
.emp-name   { font-size: 9.5px; font-weight: 700; text-align: center; }
.emp-period { font-size: 7.5px; margin: 2px 0 3px; }

/* DTR table */
.dtr-table { width: 100%; border-collapse: collapse; margin-bottom: 3px; }
.dtr-table th,
.dtr-table td {
  border: 1px solid #000;
  padding: 1px 1px;
  text-align: center;
  font-size: 7.5px;
  line-height: 1.2;
  vertical-align: middle;
}
.dtr-table th { font-weight: 700; }
.col-day  { width: 14px; }
.col-time { width: 40px; }
.col-num  { width: 18px; }
.span-cell { font-size: 7px; }
.total-row td { font-weight: 700; font-size: 7px; }

/* Footer sections */
.legend  { font-size: 7px; border-top: 1px solid #ccc; padding-top: 2px; margin-bottom: 3px; }
.certify { font-size: 7px; line-height: 1.4; margin-bottom: 6px; }

.sig-row  { margin-bottom: 3px; }
.sig-box  { display: inline-block; width: 70%; }
.sig-line {
  border-bottom: 1px solid #000;
  min-height: 20px;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding-bottom: 2px;
}
.sig-name  { font-weight: 700; font-size: 8px; text-decoration: underline; text-transform: uppercase; }
.sig-label { font-size: 7px; text-align: center; }

.verify-label { font-size: 7px; margin-top: 3px; }
.supervisor-row { text-align: center; }
.supervisor-row .sig-box { display: block; width: 70%; margin: 0 auto; }
.sup-name  { text-align: center; }
.sup-label { text-align: center; }
.meta { font-size: 6.5px; color: #444; border-top: 1px solid #ddd; padding-top: 2px; margin-top: 3px; }

/* PRINT */
@media print {
  @page { size: A4 portrait; margin: 6mm; }
  body        { margin: 0; }
  .print-root { padding: 0; }
  .copy-pair  { gap: 4mm; }
  .dtr-card   { border-color: #000; }
}
</style>
