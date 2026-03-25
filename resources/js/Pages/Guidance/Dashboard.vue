<template>
  <Head title="Guidance Dashboard" />
  <AdminLayout title="Guidance Dashboard">
    <template #default>
      <div class="space-y-6">

        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <h1 class="text-xl font-semibold text-slate-800">Guidance Dashboard</h1>
            <p class="text-sm text-slate-500">Analytics and overview of guidance consultations</p>
          </div>
          <!-- sub-nav -->
          <div class="flex items-center gap-2 text-sm">
            <span class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-medium">Dashboard</span>
            <Link href="/guidance/consultations" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Consultations</Link>
            <Link href="/guidance/session-reports" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Session Reports</Link>
            <Link href="/guidance/reports" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Reports</Link>
          </div>
        </div>

        <!-- Stat cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <div v-for="card in statCards" :key="card.label" class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">{{ card.label }}</p>
            <p :class="`text-3xl font-bold mt-1 ${card.color}`">{{ card.value }}</p>
          </div>
        </div>

        <!-- Charts row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

          <!-- Monthly trend -->
          <div class="lg:col-span-2 bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-slate-700 mb-4">Monthly Consultations (Last 6 Months)</h2>
            <div class="flex items-end gap-2 h-36">
              <div v-for="month in trend" :key="month.label" class="flex-1 flex flex-col items-center gap-1">
                <span class="text-xs font-semibold text-indigo-600">{{ month.count }}</span>
                <div
                  class="w-full rounded-t bg-indigo-500 transition-all"
                  :style="{ height: trendBarHeight(month.count) }"
                ></div>
                <span class="text-[10px] text-slate-500 text-center leading-tight">{{ month.label }}</span>
              </div>
            </div>
          </div>

          <!-- By type pie -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-slate-700 mb-4">Consultation Type</h2>
            <div class="space-y-3">
              <div v-for="(count, type) in byType" :key="type">
                <div class="flex justify-between text-xs text-slate-600 mb-1">
                  <span class="capitalize">{{ typeLabel(type) }}</span>
                  <span class="font-semibold">{{ count }}</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                  <div
                    class="h-2 rounded-full bg-indigo-500"
                    :style="{ width: pct(count, totalConsultations) }"
                  ></div>
                </div>
              </div>
              <p v-if="!Object.keys(byType).length" class="text-xs text-slate-400">No data</p>
            </div>
          </div>
        </div>

        <!-- Concern & Status row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

          <!-- By concern -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-slate-700 mb-4">Consultations by Concern</h2>
            <div class="space-y-3">
              <div v-for="(count, concern) in byConcern" :key="concern">
                <div class="flex justify-between text-xs text-slate-600 mb-1">
                  <span>{{ concern }}</span>
                  <span class="font-semibold">{{ count }}</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                  <div
                    :class="`h-2 rounded-full ${concernColor(concern)}`"
                    :style="{ width: pct(count, totalConcernSum) }"
                  ></div>
                </div>
              </div>
            </div>
          </div>

          <!-- By status -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-slate-700 mb-4">Consultations by Status</h2>
            <div class="space-y-2">
              <div v-for="(count, status) in byStatus" :key="status" class="flex items-center justify-between">
                <span class="flex items-center gap-2 text-xs text-slate-600">
                  <span :class="`inline-block w-2 h-2 rounded-full ${statusColor(status)}`"></span>
                  {{ statusLabel(status) }}
                </span>
                <span class="text-xs font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-full">{{ count }}</span>
              </div>
              <p v-if="!Object.keys(byStatus).length" class="text-xs text-slate-400">No data</p>
            </div>
          </div>
        </div>

        <!-- Today's appointments -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
          <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Today's Scheduled Appointments</h2>
            <span class="text-xs bg-indigo-50 text-indigo-600 font-semibold px-2 py-0.5 rounded-full">{{ todayAppointments.length }} appointment(s)</span>
          </div>
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
        </div>

      </div>
    </template>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  stats:              Object,
  byStatus:           Object,
  byConcern:          Object,
  byType:             Object,
  trend:              Array,
  todayAppointments:  Array,
})

const statCards = computed(() => [
  { label: 'Total Consultations', value: props.stats.total,     color: 'text-slate-800' },
  { label: 'This Month',          value: props.stats.thisMonth, color: 'text-indigo-600' },
  { label: 'Pending',             value: props.stats.pending,   color: 'text-amber-500'  },
  { label: 'Scheduled',           value: props.stats.scheduled, color: 'text-emerald-600' },
])

const totalConsultations = computed(() =>
  Object.values(props.byType ?? {}).reduce((s, v) => s + v, 0)
)

const totalConcernSum = computed(() =>
  Object.values(props.byConcern ?? {}).reduce((s, v) => s + v, 0)
)

const trendMax = computed(() => Math.max(...(props.trend ?? []).map(t => t.count), 1))

function trendBarHeight(count) {
  const pct = Math.round((count / trendMax.value) * 100)
  return (pct < 4 && count > 0 ? 4 : pct) + '%'
}

function pct(count, total) {
  if (!total) return '0%'
  return Math.round((count / total) * 100) + '%'
}

function typeLabel(type) {
  if (!type || type === 'null') return 'Student (Self)'
  if (type === 'referred') return 'Referred'
  return type
}

function statusLabel(s) {
  const map = {
    pending:                   'Pending',
    scheduled:                 'Scheduled',
    'For Follow-up':           'For Follow-up',
    'For Monitoring':          'For Monitoring',
    'Done Intervention':       'Done Intervention',
    'Refer to School Psychologist': 'Refer to School Psychologist',
  }
  return map[s] ?? s
}

function statusColor(s) {
  const map = {
    pending:                   'bg-amber-400',
    scheduled:                 'bg-blue-400',
    'For Follow-up':           'bg-purple-400',
    'For Monitoring':          'bg-teal-400',
    'Done Intervention':       'bg-emerald-500',
    'Refer to School Psychologist': 'bg-rose-400',
  }
  return map[s] ?? 'bg-slate-300'
}

function concernColor(c) {
  const map = {
    'Academic':        'bg-blue-500',
    'Behavior':        'bg-orange-500',
    'Personal / Social': 'bg-purple-500',
    'Other':           'bg-slate-400',
  }
  return map[c] ?? 'bg-slate-400'
}

function fmtTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', hour12: true })
}
</script>
