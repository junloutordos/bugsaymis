<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import {
  ArrowLeftIcon, PrinterIcon, UsersIcon, CheckCircleIcon,
  ClipboardDocumentCheckIcon, DocumentTextIcon, CalendarDaysIcon, MapPinIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  activity: Object,
  report:   Object,
})

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

const search = ref('')
const typeFilter = ref('all')

const filteredRows = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.report.rows.filter(r => {
    if (q && !r.name.toLowerCase().includes(q)) return false
    if (typeFilter.value !== 'all' && r.type !== typeFilter.value) return false
    return true
  })
})

const kpiCards = computed(() => ([
  { label: 'Invited', value: props.report.kpis.invited, icon: UsersIcon, color: 'slate' },
  { label: 'Present', value: `${props.report.kpis.present} (${props.report.kpis.attendance_rate}%)`, icon: CheckCircleIcon, color: 'green' },
  { label: 'Evaluated', value: `${props.report.kpis.evaluated} (${props.report.kpis.evaluation_rate}%)`, icon: ClipboardDocumentCheckIcon, color: 'indigo' },
  { label: 'Certificates Issued', value: props.report.kpis.certificates_issued, icon: DocumentTextIcon, color: 'amber' },
]))
</script>

<template>
  <Head :title="`Report — ${activity.title}`" />
  <AdminLayout :title="`Report — ${activity.title}`">

    <div class="flex items-center justify-between mb-6">
      <a :href="route('ams.activities.show', activity.id)"
         class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
        <ArrowLeftIcon class="w-4 h-4" /> Back to Activity
      </a>
      <AppButton as="a" :href="route('ams.activities.report.print', activity.id)" target="_blank" variant="secondary" size="sm">
        <PrinterIcon class="w-4 h-4" /> Print / PDF
      </AppButton>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-5 mb-6">
      <h1 class="text-xl font-bold text-slate-800 mb-2">{{ activity.title }}</h1>
      <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-600">
        <span class="inline-flex items-center gap-1"><CalendarDaysIcon class="w-4 h-4 text-slate-400" />
          {{ formatDate(activity.start_date) }}
          <template v-if="activity.end_date && activity.end_date !== activity.start_date"> – {{ formatDate(activity.end_date) }}</template>
        </span>
        <span v-if="activity.venue" class="inline-flex items-center gap-1"><MapPinIcon class="w-4 h-4 text-slate-400" /> {{ activity.venue }}</span>
        <span>Proponent: {{ activity.proponent ?? '—' }}</span>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div v-for="card in kpiCards" :key="card.label" class="bg-white rounded-xl border border-slate-200 p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
             :class="{
               slate: 'bg-slate-100 text-slate-500', green: 'bg-success-50 text-success-600',
               indigo: 'bg-indigo-50 text-indigo-600', amber: 'bg-warning-50 text-warning-600',
             }[card.color]">
          <component :is="card.icon" class="w-5 h-5" />
        </div>
        <div>
          <p class="text-xs text-slate-400 uppercase tracking-wide">{{ card.label }}</p>
          <p class="text-lg font-bold text-slate-800">{{ card.value }}</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center gap-3">
        <input v-model="search" placeholder="Search participants…"
               class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 flex-1 min-w-48" />
        <select v-model="typeFilter" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="all">All Types</option>
          <option value="Employee">Employees</option>
          <option value="Student">Students</option>
        </select>
        <span class="text-xs text-slate-400 ml-auto">{{ filteredRows.length }} result(s)</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 text-[11px] font-semibold text-slate-400 uppercase tracking-wider bg-slate-50">
              <th class="text-left px-4 py-2">Name</th>
              <th class="text-center px-3 py-2">Type</th>
              <th class="text-left px-3 py-2">Section / Division</th>
              <th v-for="date in report.days" :key="date" class="text-center px-3 py-2 whitespace-nowrap">
                {{ new Date(date + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }) }}
              </th>
              <th class="text-center px-3 py-2">Overall</th>
              <th class="text-center px-3 py-2">Hours</th>
              <th class="text-center px-3 py-2">Evaluated</th>
              <th class="text-center px-3 py-2">Certificate</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="row in filteredRows" :key="row.name + row.type" class="hover:bg-slate-50">
              <td class="px-4 py-2 font-medium text-slate-700">{{ row.name }}</td>
              <td class="px-3 py-2 text-center"><AppBadge :color="row.type === 'Employee' ? 'indigo' : 'amber'">{{ row.type }}</AppBadge></td>
              <td class="px-3 py-2 text-slate-500">{{ row.section ?? '—' }}</td>
              <td v-for="day in row.daily" :key="day.date" class="px-3 py-2 text-center">
                <CheckCircleIcon v-if="day.attended" class="w-4 h-4 text-success-500 inline" />
                <span v-else class="text-slate-300">—</span>
              </td>
              <td class="px-3 py-2 text-center">
                <AppBadge :color="row.attended ? 'green' : 'red'">{{ row.attended ? 'Present' : 'Absent' }}</AppBadge>
              </td>
              <td class="px-3 py-2 text-center text-slate-600">{{ row.hours_attended }}</td>
              <td class="px-3 py-2 text-center">
                <AppBadge :color="row.evaluated ? 'indigo' : 'slate'">{{ row.evaluated ? 'Yes' : 'No' }}</AppBadge>
              </td>
              <td class="px-3 py-2 text-center">
                <AppBadge :color="row.certificate_issued ? 'green' : 'slate'">{{ row.certificate_issued ? 'Issued' : '—' }}</AppBadge>
              </td>
            </tr>
            <tr v-if="!filteredRows.length">
              <td :colspan="4 + report.days.length" class="px-4 py-10 text-center text-sm text-slate-400 italic">No participants match the current filters.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </AdminLayout>
</template>
