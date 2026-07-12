<template>
  <Head title="Guidance Dashboard" />
  <AdminLayout title="Guidance Dashboard">
    <template #default>
      <div class="space-y-6">

        <AppPageHeader hero class="dash-section" style="--stagger: 0" title="Guidance Dashboard" subtitle="GAD-ready analytics with sex-disaggregated data" />

        <!-- sub-nav -->
        <div class="flex items-center gap-2 text-sm flex-wrap">
          <span class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-medium">Dashboard</span>
          <Link href="/guidance/consultations" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Consultations</Link>
          <Link href="/guidance/session-reports" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Session Reports</Link>
          <Link href="/guidance/reports" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Reports</Link>
        </div>

        <!-- ── GAD Banner ──────────────────────────────────────────────────── -->
        <div class="bg-gradient-to-r from-pink-50 via-purple-50 to-blue-50 border border-purple-100 rounded-xl px-5 py-3 flex items-center gap-3">
          <span class="text-2xl">⚧</span>
          <div>
            <p class="text-sm font-semibold text-purple-800">Gender and Development (GAD) Analytics</p>
            <p class="text-xs text-purple-600">All data presented with sex disaggregation in compliance with RA 9710 (Magna Carta of Women) and PCW GAD guidelines.</p>
          </div>
        </div>

        <!-- ── Summary stat cards ──────────────────────────────────────────── -->
        <div class="dash-section grid grid-cols-2 lg:grid-cols-4 gap-4" style="--stagger: 1">
          <AppCard v-for="(card, i) in statCards" :key="card.label">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">{{ card.label }}</p>
            <p :class="`text-3xl font-bold mt-1 tabular-nums ${card.color}`">{{ kpiValues[i] ?? card.value }}</p>
          </AppCard>
        </div>

        <!-- ── Sex disaggregation highlight ───────────────────────────────── -->
        <div class="dash-section grid grid-cols-1 lg:grid-cols-2 gap-4" style="--stagger: 2">

          <!-- All time sex breakdown -->
          <AppCard>
            <h2 class="text-sm font-semibold text-slate-700 mb-1">Sex Disaggregation — All Time</h2>
            <p class="text-xs text-slate-400 mb-4">Total: {{ stats.total }} consultation(s)</p>
            <div class="space-y-3">
              <div v-for="(count, sex) in bySex" :key="sex">
                <div class="flex justify-between text-xs mb-1">
                  <span class="flex items-center gap-1.5 font-medium" :class="sexColor(sex).text">
                    <span :class="`inline-block w-2.5 h-2.5 rounded-full ${sexColor(sex).dot}`"></span>
                    {{ sex }}
                  </span>
                  <span class="font-semibold text-slate-700">{{ count }} <span class="font-normal text-slate-400">({{ pct(count, stats.total) }})</span></span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-3">
                  <div :class="`h-3 rounded-full ${sexColor(sex).bar}`" :style="{ width: pct(count, stats.total) }"></div>
                </div>
              </div>
            </div>
          </AppCard>

          <!-- This month sex breakdown -->
          <AppCard>
            <h2 class="text-sm font-semibold text-slate-700 mb-1">Sex Disaggregation — This Month</h2>
            <p class="text-xs text-slate-400 mb-4">Total: {{ stats.thisMonth }} consultation(s)</p>
            <div class="space-y-3">
              <div v-for="(count, sex) in bySexThisMonth" :key="sex">
                <div class="flex justify-between text-xs mb-1">
                  <span class="flex items-center gap-1.5 font-medium" :class="sexColor(sex).text">
                    <span :class="`inline-block w-2.5 h-2.5 rounded-full ${sexColor(sex).dot}`"></span>
                    {{ sex }}
                  </span>
                  <span class="font-semibold text-slate-700">{{ count }} <span class="font-normal text-slate-400">({{ pct(count, stats.thisMonth) }})</span></span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-3">
                  <div :class="`h-3 rounded-full ${sexColor(sex).bar}`" :style="{ width: pct(count, stats.thisMonth) }"></div>
                </div>
              </div>
            </div>
          </AppCard>
        </div>

        <!-- ── Monthly trend (sex-disaggregated grouped bars) ──────────────── -->
        <AppCard>
          <h2 class="text-sm font-semibold text-slate-700 mb-1">Monthly Trend — Last 6 Months</h2>
          <div class="flex items-center gap-4 mb-4 text-xs">
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-blue-500 inline-block"></span>Male</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-pink-500 inline-block"></span>Female</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-slate-300 inline-block"></span>Unspecified</span>
          </div>
          <div class="flex items-end gap-3 h-40">
            <div v-for="month in trend" :key="month.label" class="flex-1 flex flex-col items-center gap-1">
              <!-- Stacked label -->
              <span class="text-[10px] font-semibold text-slate-600">{{ month.total }}</span>
              <!-- Grouped bars -->
              <div class="w-full flex items-end justify-center gap-0.5" style="height:100px;">
                <div class="flex-1 rounded-t bg-blue-500 transition-all" :style="{ height: barH(month.Male, trendMax) }" :title="`Male: ${month.Male}`"></div>
                <div class="flex-1 rounded-t bg-pink-500 transition-all" :style="{ height: barH(month.Female, trendMax) }" :title="`Female: ${month.Female}`"></div>
                <div class="flex-1 rounded-t bg-slate-300 transition-all" :style="{ height: barH(month.Unspecified, trendMax) }" :title="`Unspecified: ${month.Unspecified}`"></div>
              </div>
              <span class="text-[9px] text-slate-500 text-center leading-tight">{{ month.label }}</span>
            </div>
          </div>
        </AppCard>

        <!-- ── Concern × Sex matrix ────────────────────────────────────────── -->
        <AppCard :padded="false" title="Consultations by Concern (Sex-Disaggregated)">
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50/80">
                <tr>
                  <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Concern</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-blue-600 uppercase">Male</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-pink-600 uppercase">Female</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-slate-400 uppercase">Unspecified</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-slate-600 uppercase">Total</th>
                  <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase w-48">Distribution</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-for="(data, concern) in byConcern" :key="concern" class="hover:bg-slate-50 transition-colors">
                  <td class="px-5 py-3 font-medium text-slate-800">{{ concern }}</td>
                  <td class="px-4 py-3 text-center text-blue-600 font-semibold">{{ data.Male }}</td>
                  <td class="px-4 py-3 text-center text-pink-600 font-semibold">{{ data.Female }}</td>
                  <td class="px-4 py-3 text-center text-slate-400 font-semibold">{{ data.Unspecified }}</td>
                  <td class="px-4 py-3 text-center font-bold text-slate-800">{{ data.total }}</td>
                  <td class="px-5 py-3">
                    <div class="flex h-3 rounded-full overflow-hidden bg-slate-100 w-full" v-if="data.total">
                      <div class="bg-blue-500 h-full" :style="{ width: pct(data.Male, data.total) }" :title="`Male: ${data.Male}`"></div>
                      <div class="bg-pink-500 h-full" :style="{ width: pct(data.Female, data.total) }" :title="`Female: ${data.Female}`"></div>
                      <div class="bg-slate-300 h-full" :style="{ width: pct(data.Unspecified, data.total) }" :title="`Unspecified: ${data.Unspecified}`"></div>
                    </div>
                  </td>
                </tr>
                <tr v-if="!Object.keys(byConcern).length">
                  <td colspan="6" class="px-5 py-6 text-center text-slate-400 text-xs">No consultation data yet.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </AppCard>

        <!-- ── Status × Sex + Consultation Type × Sex ─────────────────────── -->
        <div class="dash-section grid grid-cols-1 lg:grid-cols-2 gap-4" style="--stagger: 3">

          <!-- Status breakdown -->
          <AppCard :padded="false" title="Status Breakdown (Sex-Disaggregated)">
            <table class="min-w-full text-xs">
              <thead class="bg-slate-50/80">
                <tr>
                  <th class="px-4 py-2.5 text-left font-semibold text-slate-500 uppercase">Status</th>
                  <th class="px-3 py-2.5 text-center font-semibold text-blue-600 uppercase">M</th>
                  <th class="px-3 py-2.5 text-center font-semibold text-pink-600 uppercase">F</th>
                  <th class="px-3 py-2.5 text-center font-semibold text-slate-400 uppercase">U</th>
                  <th class="px-3 py-2.5 text-center font-semibold text-slate-600 uppercase">Total</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-for="(data, status) in byStatus" :key="status" class="hover:bg-slate-50">
                  <td class="px-4 py-2.5">
                    <span :class="`inline-block w-2 h-2 rounded-full mr-1.5 ${statusDot(status)}`"></span>
                    <span class="font-medium text-slate-700">{{ status }}</span>
                  </td>
                  <td class="px-3 py-2.5 text-center text-blue-600 font-semibold">{{ data.Male }}</td>
                  <td class="px-3 py-2.5 text-center text-pink-600 font-semibold">{{ data.Female }}</td>
                  <td class="px-3 py-2.5 text-center text-slate-400 font-semibold">{{ data.Unspecified }}</td>
                  <td class="px-3 py-2.5 text-center font-bold text-slate-800">{{ data.total }}</td>
                </tr>
                <tr v-if="!Object.keys(byStatus).length">
                  <td colspan="5" class="px-4 py-6 text-center text-slate-400">No data yet.</td>
                </tr>
              </tbody>
            </table>
          </AppCard>

          <!-- Consultation type breakdown -->
          <AppCard :padded="false" title="Consultation Type (Sex-Disaggregated)">
            <table class="min-w-full text-xs">
              <thead class="bg-slate-50/80">
                <tr>
                  <th class="px-4 py-2.5 text-left font-semibold text-slate-500 uppercase">Type</th>
                  <th class="px-3 py-2.5 text-center font-semibold text-blue-600 uppercase">M</th>
                  <th class="px-3 py-2.5 text-center font-semibold text-pink-600 uppercase">F</th>
                  <th class="px-3 py-2.5 text-center font-semibold text-slate-400 uppercase">U</th>
                  <th class="px-3 py-2.5 text-center font-semibold text-slate-600 uppercase">Total</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-for="(data, type) in byType" :key="type" class="hover:bg-slate-50">
                  <td class="px-4 py-2.5 font-medium text-slate-700">{{ typeLabel(type) }}</td>
                  <td class="px-3 py-2.5 text-center text-blue-600 font-semibold">{{ data.Male }}</td>
                  <td class="px-3 py-2.5 text-center text-pink-600 font-semibold">{{ data.Female }}</td>
                  <td class="px-3 py-2.5 text-center text-slate-400 font-semibold">{{ data.Unspecified }}</td>
                  <td class="px-3 py-2.5 text-center font-bold text-slate-800">{{ data.total }}</td>
                </tr>
                <tr v-if="!Object.keys(byType).length">
                  <td colspan="5" class="px-4 py-6 text-center text-slate-400">No data yet.</td>
                </tr>
              </tbody>
            </table>
          </AppCard>
        </div>

        <!-- ── GAD Summary Table ────────────────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-purple-100 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-purple-100 bg-purple-50 flex items-center gap-2">
            <span class="text-purple-600 text-lg">⚧</span>
            <h2 class="text-sm font-semibold text-purple-800">GAD Summary Table</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50/80">
                <tr>
                  <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Indicator</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-blue-600 uppercase">Male</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-pink-600 uppercase">Female</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-slate-400 uppercase">Unspecified</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-slate-600 uppercase">Total</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">% Male</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">% Female</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr class="font-semibold bg-slate-50">
                  <td class="px-5 py-3 text-slate-700">Total Consultations</td>
                  <td class="px-4 py-3 text-center text-blue-600">{{ bySex.Male }}</td>
                  <td class="px-4 py-3 text-center text-pink-600">{{ bySex.Female }}</td>
                  <td class="px-4 py-3 text-center text-slate-400">{{ bySex.Unspecified }}</td>
                  <td class="px-4 py-3 text-center font-bold text-slate-800">{{ stats.total }}</td>
                  <td class="px-4 py-3 text-center text-slate-600">{{ pct(bySex.Male, stats.total) }}</td>
                  <td class="px-4 py-3 text-center text-slate-600">{{ pct(bySex.Female, stats.total) }}</td>
                </tr>
                <tr v-for="(data, concern) in byConcern" :key="'gad-'+concern">
                  <td class="px-5 py-3 text-slate-600 pl-8">{{ concern }}</td>
                  <td class="px-4 py-3 text-center text-blue-500">{{ data.Male }}</td>
                  <td class="px-4 py-3 text-center text-pink-500">{{ data.Female }}</td>
                  <td class="px-4 py-3 text-center text-slate-400">{{ data.Unspecified }}</td>
                  <td class="px-4 py-3 text-center font-semibold text-slate-700">{{ data.total }}</td>
                  <td class="px-4 py-3 text-center text-slate-500">{{ pct(data.Male, data.total) }}</td>
                  <td class="px-4 py-3 text-center text-slate-500">{{ pct(data.Female, data.total) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ── Today's appointments ────────────────────────────────────────── -->
        <AppCard :padded="false">
          <template #header>
            <h2 class="text-sm font-semibold text-slate-700">Today's Scheduled Appointments</h2>
            <span class="text-xs bg-indigo-50 text-indigo-600 font-semibold px-2 py-0.5 rounded-full">{{ todayAppointments.length }}</span>
          </template>
          <div v-if="todayAppointments.length" class="divide-y divide-slate-50">
            <div v-for="appt in todayAppointments" :key="appt.id" class="px-5 py-3 flex items-center justify-between gap-3">
              <div>
                <p class="text-sm font-medium text-slate-800">{{ appt.requestor_name ?? '—' }}</p>
                <p class="text-xs text-slate-500">{{ appt.concern ?? '—' }}</p>
              </div>
              <div class="text-right">
                <p class="text-xs font-semibold text-indigo-600">{{ fmtTime(appt.date_time_assigned) }}</p>
                <span class="text-[10px] text-slate-400">{{ appt.consultation_type === 'referred' ? 'Referred' : 'Self-request' }}</span>
              </div>
            </div>
          </div>
          <p v-else class="px-5 py-6 text-center text-sm text-slate-400">No appointments scheduled today.</p>
        </AppCard>

      </div>
    </template>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { useCountUp } from '@/Composables/useCountUp.js'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'

const props = defineProps({
  stats:             Object,
  bySex:             Object,
  bySexThisMonth:    Object,
  byStatus:          Object,
  byConcern:         Object,
  byType:            Object,
  trend:             Array,
  todayAppointments: Array,
})

const statCards = computed(() => [
  { label: 'Total Consultations', value: props.stats.total,     color: 'text-slate-800' },
  { label: 'This Month',          value: props.stats.thisMonth, color: 'text-indigo-600' },
  { label: 'Pending',             value: props.stats.pending,   color: 'text-warning-500'  },
  { label: 'Scheduled',           value: props.stats.scheduled, color: 'text-success-600' },
])

const { values: kpiValues } = useCountUp(() => statCards.value.map(c => c.value))

const trendMax = computed(() =>
  Math.max(...(props.trend ?? []).map(t => Math.max(t.Male, t.Female, t.Unspecified, 1)))
)

function barH(count, max) {
  const px = Math.round((count / max) * 100)
  return (px < 4 && count > 0 ? 4 : px) + 'px'
}

function pct(count, total) {
  if (!total) return '0%'
  return Math.round((count / total) * 100) + '%'
}

function sexColor(sex) {
  if (sex === 'Male')   return { text: 'text-blue-600',  dot: 'bg-blue-500',  bar: 'bg-blue-500' }
  if (sex === 'Female') return { text: 'text-pink-600',  dot: 'bg-pink-500',  bar: 'bg-pink-500' }
  return                       { text: 'text-slate-500', dot: 'bg-slate-300', bar: 'bg-slate-300' }
}

function statusDot(s) {
  const map = {
    pending:                        'bg-amber-400',
    scheduled:                      'bg-blue-400',
    'For Follow-up':                'bg-purple-400',
    'For Monitoring':               'bg-teal-400',
    'Done Intervention':            'bg-emerald-500',
    'Refer to School Psychologist': 'bg-rose-400',
  }
  return map[s] ?? 'bg-slate-300'
}

function typeLabel(t) {
  if (!t || t === 'null' || t === 'student') return 'Student (Self-request)'
  if (t === 'referred') return 'Referred by Faculty/Staff'
  return t
}

function fmtTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', hour12: true })
}
</script>
