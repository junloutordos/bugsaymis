<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AlertLocationPanel from '@/Components/Sos/AlertLocationPanel.vue'
import { AcademicCapIcon, ClockIcon, UserIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const alerts = ref([])
const meta = ref(null)
const selected = ref(null)
const filters = ref({ from: '', to: '', alert_type: '', status: '', reporter: '' })

const alertTypeLabels = { medical: 'Medical', security: 'Security', fire_disaster: 'Fire / Disaster', general: 'General' }

function reporterLabel(alert) {
  const r = alert.reporter
  if (!r) return 'Unknown reporter'
  if (r.type === 'student' && (r.grade_level || r.section)) {
    const parts = []
    if (r.grade_level) parts.push(`Grade ${r.grade_level}`)
    if (r.section) parts.push(r.section)
    return `${r.name} · ${parts.join(' - ')}`
  }
  return r.name
}

async function load(page = 1) {
  const { data } = await axios.get(route('sos.history'), { params: { ...filters.value, page } })
  alerts.value = data.data
  meta.value = { current_page: data.current_page, last_page: data.last_page }
  selected.value = null
}

onMounted(() => load())
</script>

<template>
  <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
      <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-5">
        <input v-model="filters.from" type="date" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        <input v-model="filters.to" type="date" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        <select v-model="filters.alert_type" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
          <option value="">All types</option>
          <option value="medical">Medical</option>
          <option value="security">Security</option>
          <option value="fire_disaster">Fire/Disaster</option>
          <option value="general">General</option>
        </select>
        <select v-model="filters.status" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
          <option value="">All statuses</option>
          <option value="resolved">Resolved</option>
          <option value="false_alarm">False alarm</option>
        </select>
        <input v-model="filters.reporter" placeholder="Reporter name" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <button class="mb-4 rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700" @click="load(1)">Apply filters</button>

      <div v-if="alerts.length === 0" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-8 text-center text-sm text-slate-400">No matching alerts.</div>
      <div v-for="alert in alerts" :key="alert.id"
           class="mb-2 cursor-pointer rounded-xl border border-slate-100 p-3.5 text-sm transition-colors hover:border-indigo-300 hover:bg-indigo-50/30"
           :class="selected?.id === alert.id ? 'border-indigo-300 bg-indigo-50/40' : ''"
           @click="selected = alert">
        <div class="flex items-center justify-between">
          <span class="font-medium text-slate-800">#{{ alert.id }} — {{ alertTypeLabels[alert.alert_type] ?? alert.alert_type }}</span>
          <span class="rounded-full px-2 py-0.5 text-xs" :class="alert.status === 'resolved' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">{{ alert.status }}</span>
        </div>
        <div class="mt-1.5 flex items-center gap-1.5 text-xs text-slate-600">
          <AcademicCapIcon v-if="alert.reporter?.type === 'student'" class="h-3.5 w-3.5 text-slate-400" />
          <UserIcon v-else class="h-3.5 w-3.5 text-slate-400" />
          {{ reporterLabel(alert) }}
        </div>
        <p class="mt-1 flex items-center gap-1 text-xs text-slate-400">
          <ClockIcon class="h-3.5 w-3.5" /> {{ alert.resolved_location.label }} · {{ new Date(alert.triggered_at).toLocaleString('en-PH') }}
        </p>
      </div>

      <div v-if="meta && meta.last_page > 1" class="mt-3 flex gap-2">
        <button v-for="page in meta.last_page" :key="page" class="rounded-lg px-2.5 py-1 text-xs font-medium transition-colors"
                :class="page === meta.current_page ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                @click="load(page)">{{ page }}</button>
      </div>
    </div>

    <div v-if="selected" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-900">Alert #{{ selected.id }}</h3>
        <button class="flex h-7 w-7 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100" @click="selected = null">
          <XMarkIcon class="h-4 w-4" />
        </button>
      </div>
      <p class="mt-1 flex items-center gap-1.5 text-xs text-slate-600">
        <AcademicCapIcon v-if="selected.reporter?.type === 'student'" class="h-3.5 w-3.5 text-slate-400" />
        <UserIcon v-else class="h-3.5 w-3.5 text-slate-400" />
        {{ reporterLabel(selected) }}
      </p>
      <p class="mt-1 text-xs text-slate-500 capitalize">{{ selected.status }}<template v-if="selected.resolution_notes"> · {{ selected.resolution_notes }}</template></p>
      <div class="mt-4">
        <AlertLocationPanel :alert="selected" />
      </div>
      <div class="mt-5">
        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Timeline</h4>
        <ul class="mt-2 space-y-1 text-xs text-slate-600">
          <li v-for="(e, i) in selected.events" :key="i">{{ e.type }} — {{ new Date(e.created_at).toLocaleString('en-PH') }}</li>
        </ul>
      </div>
    </div>
  </div>
</template>
