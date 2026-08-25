<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AlertLocationPanel from '@/Components/Sos/AlertLocationPanel.vue'

const alerts = ref([])
const meta = ref(null)
const selected = ref(null)
const filters = ref({ from: '', to: '', alert_type: '', status: '', reporter: '' })

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
        <input v-model="filters.from" type="date" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs" />
        <input v-model="filters.to" type="date" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs" />
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
        <input v-model="filters.reporter" placeholder="Reporter name" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs" />
      </div>
      <button class="mb-4 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white" @click="load(1)">Apply filters</button>

      <div v-if="alerts.length === 0" class="rounded-lg border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400">No matching alerts.</div>
      <div v-for="alert in alerts" :key="alert.id" class="mb-2 cursor-pointer rounded-lg border border-slate-100 p-3 text-sm hover:border-indigo-300" @click="selected = alert">
        #{{ alert.id }} — {{ alert.alert_type.replace('_', ' ') }} — {{ alert.resolved_location.label }}
        — <span class="text-xs text-slate-500">{{ new Date(alert.triggered_at).toLocaleString('en-PH') }}</span>
      </div>

      <div v-if="meta && meta.last_page > 1" class="mt-3 flex gap-2">
        <button v-for="page in meta.last_page" :key="page" class="rounded px-2 py-1 text-xs"
                :class="page === meta.current_page ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600'"
                @click="load(page)">{{ page }}</button>
      </div>
    </div>

    <div v-if="selected" class="rounded-xl border border-slate-200 bg-white p-5">
      <h3 class="text-sm font-semibold text-slate-900">Alert #{{ selected.id }}</h3>
      <p class="mt-1 text-xs text-slate-500">{{ selected.status }} · {{ selected.resolution_notes }}</p>
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
