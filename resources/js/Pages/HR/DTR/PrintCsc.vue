<template>
  <Head :title="`DTR — ${employee.name} — ${monthLabel}`" />
  <div class="csc-page">

    <!-- Republic Header -->
    <div class="text-center mb-2">
      <p class="text-[10px] tracking-wide">Republic of the Philippines</p>
      <p class="text-[10px] font-semibold tracking-wide uppercase">{{ agencyName }}</p>
    </div>

    <div class="text-center mb-3">
      <p class="text-[13px] font-bold tracking-widest uppercase">Daily Time Record</p>
      <p class="text-[9px] tracking-wide text-gray-500">CS Form No. 48</p>
    </div>

    <!-- Employee Info -->
    <table class="w-full mb-2 text-[10px]">
      <tr>
        <td class="w-1/2 pb-1">
          <span class="font-semibold">Name:</span>
          <span class="border-b border-black ml-1 pr-16">{{ employee.name }}</span>
        </td>
        <td class="w-1/2 pb-1">
          <span class="font-semibold">Month of:</span>
          <span class="border-b border-black ml-1 pr-10">{{ monthLabel }}</span>
        </td>
      </tr>
      <tr>
        <td class="pb-1">
          <span class="font-semibold">Position/Title:</span>
          <span class="border-b border-black ml-1 pr-10">{{ employee.position || '' }}</span>
        </td>
        <td class="pb-1">
          <span class="font-semibold">Office/Dept:</span>
          <span class="border-b border-black ml-1 pr-10">{{ officeName }}</span>
        </td>
      </tr>
    </table>

    <!-- Official Hours note -->
    <p class="text-[9px] mb-2 italic">
      Official Hours for Arrival and Departure:
      <span class="ml-2 not-italic font-medium">{{ officialHours }}</span>
    </p>

    <!-- Main DTR Table -->
    <table class="w-full border-collapse text-[9.5px] mb-3">
      <thead>
        <tr>
          <th rowspan="2" class="border border-black px-1 py-1 text-center w-[32px]">Day</th>
          <th colspan="2" class="border border-black px-1 py-1 text-center">A.M.</th>
          <th colspan="2" class="border border-black px-1 py-1 text-center">P.M.</th>
          <th colspan="2" class="border border-black px-1 py-1 text-center">UNDERTIME</th>
        </tr>
        <tr>
          <th class="border border-black px-1 py-1 text-center w-[60px]">Arrival</th>
          <th class="border border-black px-1 py-1 text-center w-[60px]">Departure</th>
          <th class="border border-black px-1 py-1 text-center w-[60px]">Arrival</th>
          <th class="border border-black px-1 py-1 text-center w-[60px]">Departure</th>
          <th class="border border-black px-1 py-1 text-center w-[36px]">Hours</th>
          <th class="border border-black px-1 py-1 text-center w-[36px]">Minutes</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="day in daysInMonth" :key="day">
          <td class="border border-black text-center font-medium py-[2px]">{{ day }}</td>
          <template v-if="getRecord(day)">
            <td v-if="isRestDay(day)" colspan="6" class="border border-black text-center text-[8px] italic text-gray-400">
              {{ getDayType(day) }}
            </td>
            <template v-else>
              <td v-for="f in ['time_in_am','time_out_am','time_in_pm','time_out_pm']" :key="f"
                  class="border border-black text-center py-[2px]"
                  :style="!getRecord(day)[f] && getRecord(day)['penned_'+f] ? 'color:red' : ''">
                {{ fmtTime(getRecord(day)[f] || getRecord(day)['penned_'+f]) }}
              </td>
              <td class="border border-black text-center py-[2px]">{{ undertimeHours(getRecord(day)) }}</td>
              <td class="border border-black text-center py-[2px]">{{ undertimeMinutes(getRecord(day)) }}</td>
            </template>
          </template>
          <template v-else>
            <td v-if="isWeekend(day)" colspan="6" class="border border-black text-center text-[8px] italic text-gray-400">
              {{ getDayOfWeek(day) }}
            </td>
            <template v-else>
              <td class="border border-black text-center py-[2px] text-gray-300"></td>
              <td class="border border-black text-center py-[2px] text-gray-300"></td>
              <td class="border border-black text-center py-[2px] text-gray-300"></td>
              <td class="border border-black text-center py-[2px] text-gray-300"></td>
              <td class="border border-black text-center py-[2px]"></td>
              <td class="border border-black text-center py-[2px]"></td>
            </template>
          </template>
        </tr>

        <!-- Totals row -->
        <tr class="font-semibold">
          <td colspan="5" class="border border-black text-right pr-2 py-1 text-[9px]">TOTAL UNDERTIME</td>
          <td class="border border-black text-center py-1">{{ totalUndertimeHours }}</td>
          <td class="border border-black text-center py-1">{{ totalUndertimeMinutes }}</td>
        </tr>
      </tbody>
    </table>

    <!-- Certification -->
    <div class="text-[9px] mb-5 leading-relaxed">
      <p class="mb-2">
        I CERTIFY on my honor that the above is a true and correct report of the hours of work performed,
        record of which was made daily at the time of arrival at and departure from office.
      </p>
      <div class="flex justify-center mt-4">
        <div class="text-center w-56">
          <div class="border-b border-black pb-0.5 mb-0.5 min-h-[28px] flex items-end justify-center">
            <span class="text-[10px] font-semibold">{{ employee.name }}</span>
          </div>
          <p>Signature of Employee</p>
        </div>
      </div>
    </div>

    <!-- Verification -->
    <div class="text-[9px]">
      <p class="font-semibold mb-3">Verified as to the prescribed office hours:</p>
      <div class="flex justify-between items-end">
        <div class="text-center w-48">
          <div class="border-b border-black mb-0.5 min-h-[28px]"></div>
          <p>Supervisor/Head of Office</p>
          <p class="text-[8px] text-gray-500 mt-0.5">Signature over Printed Name</p>
        </div>
        <div class="text-center w-32">
          <div class="border-b border-black mb-0.5 min-h-[28px]"></div>
          <p>Date</p>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  employee: Object,
  records:  Object,   // keyed by YYYY-MM-DD
  month:    String,
})

const agencyName = 'Philippine Science High School – CRC'
const officeName = computed(() => props.employee?.employeeProfile?.division ?? '')

const monthLabel = computed(() => {
  const [y, m] = props.month.split('-')
  return new Date(+y, +m - 1, 1).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })
})

const daysInMonth = computed(() => {
  const [y, m] = props.month.split('-').map(Number)
  return new Date(y, m, 0).getDate()
})

// Determine official hours from the first record's schedule
const officialHours = computed(() => {
  const first = Object.values(props.records)[0]
  if (!first?.schedule) return '8:00 AM – 5:00 PM'
  const s = first.schedule
  if (s.daily_schedules) {
    const entries = Object.values(s.daily_schedules)
    if (entries.length) {
      const t = entries[0]
      return `${fmtHuman(t.time_in)} – ${fmtHuman(t.time_out)}`
    }
  }
  return s.time_in && s.time_out ? `${fmtHuman(s.time_in)} – ${fmtHuman(s.time_out)}` : '8:00 AM – 5:00 PM'
})

function dateStr(day) {
  const [y, m] = props.month.split('-')
  return `${y}-${m}-${String(day).padStart(2, '0')}`
}

function getRecord(day) {
  return props.records[dateStr(day)] ?? null
}

function getDayOfWeek(day) {
  return new Date(dateStr(day) + 'T00:00:00').toLocaleDateString('en-PH', { weekday: 'long' })
}

function isWeekend(day) {
  const dow = new Date(dateStr(day) + 'T00:00:00').getDay()
  return dow === 0 || dow === 6
}

function isRestDay(day) {
  const rec = getRecord(day)
  return rec && (rec.day_type === 'rest_day' || rec.day_type === 'rest_day_holiday')
}

function getDayType(day) {
  const rec = getRecord(day)
  const map = {
    rest_day:         getDayOfWeek(day),
    rest_day_holiday: 'Holiday',
    holiday_regular:  'Regular Holiday',
    holiday_special:  'Special Holiday',
  }
  return map[rec?.day_type] ?? getDayOfWeek(day)
}

function fmtTime(t) {
  if (!t) return ''
  return String(t).slice(0, 5)
}

function fmtHuman(t) {
  if (!t) return ''
  const [h, m] = String(t).slice(0, 5).split(':').map(Number)
  const ampm = h >= 12 ? 'PM' : 'AM'
  const h12  = h % 12 || 12
  return `${h12}:${String(m).padStart(2, '0')} ${ampm}`
}

function undertimeHours(rec) {
  if (!rec || rec.undertime_minutes <= 0) return ''
  return Math.floor(rec.undertime_minutes / 60) || ''
}

function undertimeMinutes(rec) {
  if (!rec || rec.undertime_minutes <= 0) return ''
  return rec.undertime_minutes % 60 || ''
}

const totalUndertimeMinutes = computed(() => {
  const total = Object.values(props.records).reduce((s, r) => s + (r.undertime_minutes ?? 0), 0)
  return total % 60 || (total > 0 ? 0 : '')
})

const totalUndertimeHours = computed(() => {
  const total = Object.values(props.records).reduce((s, r) => s + (r.undertime_minutes ?? 0), 0)
  return total >= 60 ? Math.floor(total / 60) : (total > 0 ? 0 : '')
})

onMounted(() => {
  setTimeout(() => window.print(), 400)
})
</script>

<style>
* { box-sizing: border-box; }
body { margin: 0; background: white; }
.csc-page {
  font-family: Arial, Helvetica, sans-serif;
  width: 210mm;
  min-height: 297mm;
  padding: 12mm 14mm;
  margin: 0 auto;
  background: white;
  color: #000;
}
@media print {
  @page { size: A4 portrait; margin: 8mm; }
  body { margin: 0; }
  .csc-page { width: 100%; padding: 0; }
}
</style>
