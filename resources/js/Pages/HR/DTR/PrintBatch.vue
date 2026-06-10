<template>
  <Head :title="`DTR Batch Print — ${monthLabel}`" />

  <div class="print-root">
    <div v-for="emp in employees" :key="emp.user.id" class="employee-page">
      <div class="copy-pair">
        <div v-for="copy in 2" :key="copy" class="dtr-card">

          <!-- ── Header ─────────────────────────────── -->
          <div class="form-no">CIVIL SERVICE FORM NO. 48</div>
          <div class="form-title">Daily Time Record</div>
          <div class="emp-name">{{ emp.user.name?.toUpperCase() }}</div>
          <div class="emp-period">For the period of: {{ periodLabel }}</div>

          <!-- ── Main Table ──────────────────────────── -->
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
              <tr v-for="date in allDates" :key="date">
                <td class="col-day">{{ dayLabel(date) }}{{ emp.records[date]?.is_advance ? '*' : '' }}</td>

                <!-- Spanning cell for weekends/holidays -->
                <template v-if="spanLabel(emp.records[date], date)">
                  <td colspan="8" class="span-cell">
                    {{ spanLabel(emp.records[date], date) }}
                  </td>
                </template>

                <!-- Regular work day -->
                <template v-else>
                  <td v-for="f in ['time_in_am','time_out_am','time_in_pm','time_out_pm']" :key="f"
                      :style="cellStyle(emp.records[date], f)">
                    {{ cellText(emp.records[date], f) }}
                  </td>
                  <td></td><!-- OT In -->
                  <td></td><!-- OT Out -->
                  <td>{{ utH(emp.records[date]) }}</td>
                  <td>{{ utM(emp.records[date]) }}</td>
                </template>
              </tr>

              <!-- Total -->
              <tr class="total-row">
                <td colspan="7" style="text-align:right; padding-right:4px;">TOTAL</td>
                <td colspan="2" style="font-size:7px;">
                  {{ emp.total_tardy_hours }} hour(s) {{ emp.total_tardy_minutes }} min(s)
                </td>
              </tr>
            </tbody>
          </table>

          <!-- ── Legend ──────────────────────────────── -->
          <div class="legend">
            Legend: [T]-Travel &nbsp; [L]-Leave &nbsp; [A]-Absent &nbsp; [OB]-Official Business
            <template v-if="Object.values(emp.records).some(r => r?.is_advance)">
              &nbsp; * Advance entry — pending biometric confirmation
            </template>
          </div>

          <!-- ── Certification ───────────────────────── -->
          <div class="certify">
            I CERTIFY on my honor that the above is a true and correct report of the hours of work
            performed, record of which was made daily at the time of arrival at and departure from office
          </div>

          <!-- ── Employee Signature ──────────────────── -->
          <div class="sig-row supervisor-row">
            <div class="sig-box">
              <div class="sig-line">
                <span class="sig-name sup-name">{{ emp.user.name }}</span>
              </div>
              <div class="sig-label sup-label">{{ empPosition(emp.user) }}</div>
            </div>
          </div>

          <!-- ── Verified / Supervisor ───────────────── -->
          <div class="verify-label">Verified as to the prescribed office hours</div>
          <div class="sig-row supervisor-row" style="margin-top:6px;">
            <div class="sig-box">
              <div class="sig-line"></div>
              <div v-if="emp.supervisor" class="sig-name sup-name">{{ emp.supervisor.name }}</div>
              <div v-if="emp.supervisor" class="sig-label sup-label">{{ emp.supervisor.position }}</div>
            </div>
          </div>

          <!-- ── Meta ────────────────────────────────── -->
          <div class="meta">
            Date &amp; Time Printed: {{ printedAt }}
          </div>

        </div><!-- end .dtr-card -->
      </div><!-- end .copy-pair -->
    </div><!-- end .employee-page -->

    <div v-if="!employees.length" class="no-records">
      No DTR records found for the selected criteria.
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  employees:  Array,
  date_from:  String,
  date_to:    String,
  holidays:   Object,   // keyed by YYYY-MM-DD → { name, type }
})

// ── Date range helpers ──────────────────────────────────────────────

// Build array of all YYYY-MM-DD strings from date_from to date_to
const allDates = computed(() => {
  const dates = []
  const end   = new Date(props.date_to   + 'T00:00:00')
  for (let d = new Date(props.date_from  + 'T00:00:00'); d <= end; d.setDate(d.getDate() + 1)) {
    dates.push(d.toISOString().slice(0, 10))
  }
  return dates
})

// Whether the range spans more than one calendar month
const crossMonth = computed(() => {
  const [fy, fm] = props.date_from.split('-')
  const [ty, tm] = props.date_to.split('-')
  return fy !== ty || fm !== tm
})

// Label shown in the Day column cell
function dayLabel(date) {
  const [, , dd] = date.split('-')
  if (!crossMonth.value) return dd  // just "25"
  // e.g. "Dec\n25"
  const d = new Date(date + 'T00:00:00')
  const mon = d.toLocaleDateString('en-PH', { month: 'short' })
  return `${mon} ${parseInt(dd)}`
}

const fmtDate = (iso) =>
  new Date(iso + 'T00:00:00').toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' })

const periodLabel = computed(() =>
  crossMonth.value
    ? `${fmtDate(props.date_from)} – ${fmtDate(props.date_to)}`
    : (() => {
        const [y, m] = props.date_from.split('-').map(Number)
        const last   = new Date(y, m, 0).getDate()
        const mName  = new Date(y, m - 1, 1).toLocaleDateString('en-PH', { month: 'long' })
        return `${mName} 1-${mName} ${last}, ${y}`
      })()
)

const monthLabel = computed(() => {
  const d = new Date(props.date_from + 'T00:00:00')
  return crossMonth.value
    ? `${fmtDate(props.date_from)} – ${fmtDate(props.date_to)}`
    : d.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })
})

const printedAt = computed(() => {
  const n = new Date()
  const pad = v => String(v).padStart(2, '0')
  return `${n.getFullYear()}-${pad(n.getMonth()+1)}-${pad(n.getDate())} ` +
         `${pad(n.getHours())}:${pad(n.getMinutes())}:${pad(n.getSeconds())}`
})

// ── Helpers ────────────────────────────────────────────────────────

function dow(date) {
  return new Date(date + 'T00:00:00').getDay()  // 0=Sun, 6=Sat
}

function fmtTime12(t) {
  if (!t) return ''
  const [h, m] = String(t).slice(0, 5).split(':').map(Number)
  const ampm = h >= 12 ? 'PM' : 'AM'
  const h12  = h % 12 || 12
  return `${String(h12).padStart(2,'0')}:${String(m).padStart(2,'0')} ${ampm}`
}

// Returns span label string (or null for a normal row)
function spanLabel(rec, date) {
  const h = props.holidays?.[date]

  if (rec) {
    const dt = rec.day_type
    if (dt === 'rest_day')         return dow(date) === 0 ? 'SUNDAY' : 'SATURDAY'
    if (dt === 'rest_day_holiday') return h?.name ?? 'Holiday (Rest Day)'
    if (dt === 'holiday_regular')  return h?.name ?? 'Regular Holiday'
    if (dt === 'holiday_special')  return h?.name ?? 'Special Non-Working Holiday'
    return null
  }

  if (h) return h.name
  const d = dow(date)
  if (d === 0) return 'SUNDAY'
  if (d === 6) return 'SATURDAY'
  return null
}

// Text value for a time cell
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

// Color style for a time cell
function cellStyle(rec, field) {
  if (!rec) return {}
  const biometric = rec[field]
  const penned    = rec['penned_' + field]
  if (!biometric && penned) return { color: 'red' }

  const val = biometric || penned
  if (!val) {
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

function empPosition(user) {
  return user.employeeProfile?.position ?? user.position ?? ''
}

onMounted(() => setTimeout(() => window.print(), 600))
</script>

<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #fff; }

.print-root {
  font-family: Arial, Helvetica, sans-serif;
  color: #000;
}

/* Page grouping */
.employee-page { margin-bottom: 24px; }

/* Two-column layout */
.copy-pair {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

/* Individual DTR card */
.dtr-card { border: 1px solid #ccc; padding: 6px 8px; font-size: 8.5px; }

.form-no    { font-size: 7.5px; font-style: italic; text-align: left; }
.form-title { font-size: 12px; font-weight: 700; text-align: center; margin: 2px 0; }
.emp-name   { font-size: 10px; font-weight: 700; text-align: center; }
.emp-period { font-size: 8px; margin: 2px 0 4px; }

/* DTR table */
.dtr-table { width: 100%; border-collapse: collapse; margin-bottom: 3px; }
.dtr-table th,
.dtr-table td {
  border: 1px solid #000;
  padding: 1px 2px;
  text-align: center;
  font-size: 8px;
  line-height: 1.25;
  vertical-align: middle;
}
.dtr-table th { font-weight: 700; }
.col-day  { width: 26px; }
.col-time { width: 48px; }
.col-num  { width: 22px; }
.span-cell { font-size: 7.5px; }
.total-row td { font-weight: 700; font-size: 7.5px; }

/* Legend / certify */
.legend  { font-size: 7.5px; margin-bottom: 4px; border-top: 1px solid #ccc; padding-top: 2px; }
.certify { font-size: 7.5px; line-height: 1.4; margin-bottom: 8px; }

/* Signature blocks */
.sig-row   { margin-bottom: 4px; }
.sig-box   { display: inline-block; width: 65%; }
.sig-line  {
  border-bottom: 1px solid #000;
  min-height: 22px;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding-bottom: 2px;
}
.sig-name  { font-weight: 700; font-size: 8.5px; text-decoration: underline; text-transform: uppercase; }
.sig-label { font-size: 7.5px; text-align: center; }

.verify-label { font-size: 7.5px; margin-top: 4px; }
.meta { font-size: 7px; color: #444; border-top: 1px solid #ddd; padding-top: 2px; margin-top: 4px; }

.no-records { padding: 40px; text-align: center; color: #888; }

.supervisor-row { text-align: center; }
.supervisor-row .sig-box { display: block; width: 70%; margin: 0 auto; }
.sup-name  { text-align: center; }
.sup-label { text-align: center; }

/* PRINT */
@media print {
  @page { size: A4 portrait; margin: 6mm; }
  body  { margin: 0; }

  .employee-page { page-break-after: always; margin: 0; }
  .employee-page:last-child { page-break-after: auto; }

  .copy-pair { gap: 4mm; }
  .dtr-card  { border-color: #000; }
}
</style>
