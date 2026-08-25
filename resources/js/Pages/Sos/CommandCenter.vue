<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import EmergencyBorderOverlay from '@/Components/Sos/EmergencyBorderOverlay.vue'
import AlertLocationPanel from '@/Components/Sos/AlertLocationPanel.vue'
import ResponderList from '@/Components/Sos/ResponderList.vue'
import CommandCenterHistoryTab from '@/Components/Sos/CommandCenterHistoryTab.vue'
import CommandCenterStatsTab from '@/Components/Sos/CommandCenterStatsTab.vue'
import axios from 'axios'

const props = defineProps({ alerts: Array, emergencyAlerts: Array, authUserId: Number })

const activeTab = ref('active')

const alerts = ref([...props.alerts])
const selected = ref(null)
const falseAlarmReason = ref('')
const resolutionNotes = ref('')

const activeAlerts = computed(() => alerts.value.filter(a => !['resolved', 'false_alarm'].includes(a.status)))
const closedAlerts = computed(() => alerts.value.filter(a => ['resolved', 'false_alarm'].includes(a.status)))

function statusClass(status) {
  return {
    triggered: 'bg-red-100 text-red-700',
    acknowledged: 'bg-amber-100 text-amber-700',
    verified: 'bg-orange-100 text-orange-700',
    escalated: 'bg-red-200 text-red-800',
    resolved: 'bg-emerald-100 text-emerald-700',
    false_alarm: 'bg-slate-100 text-slate-500',
  }[status] ?? 'bg-slate-100 text-slate-600'
}

function upsertAlert(payload) {
  const idx = alerts.value.findIndex(a => a.id === payload.id)
  if (idx === -1) {
    alerts.value.unshift({ ...payload, events: [] })
  } else {
    alerts.value[idx] = { ...alerts.value[idx], ...payload }
  }
}

let channel = null
function subscribe() {
  if (!window.Echo) return
  channel = window.Echo.private('sos-responders')
    .listen('.sos.alert.triggered', (payload) => upsertAlert(payload))
    .listen('.sos.alert.updated', (payload) => upsertAlert(payload))
}

onMounted(() => {
  subscribe()
  subscribeEmergencyChannel()
})
onUnmounted(() => {
  if (window.Echo && channel) window.Echo.leave('sos-responders')
  if (window.Echo && emergencyChannel) window.Echo.leave('emergency-alerts')
})

async function act(alert, action, body = {}) {
  const { data } = await axios.post(route(`sos.${action}`, alert.id), body)
  upsertAlert(data)
  if (selected.value?.id === alert.id) selected.value = data
}

// ── Emergency Alert Broadcast ───────────────────────────────────────────────

const emergencyAlerts = ref([...props.emergencyAlerts])
const showBroadcastForm = ref(false) // false | 'standalone' | <sosAlertId>
const broadcastForm = ref({ title: '', message: '', severity: 'warning', audience: 'all' })

const activeEmergencyAlerts = computed(() => emergencyAlerts.value.filter(a => a.status === 'active'))
const closedEmergencyAlerts = computed(() => emergencyAlerts.value.filter(a => a.status !== 'active'))

const severityClass = {
  info: 'bg-sky-100 text-sky-700',
  warning: 'bg-amber-100 text-amber-700',
  critical: 'bg-red-200 text-red-800',
}

function openStandaloneBroadcast() {
  broadcastForm.value = { title: '', message: '', severity: 'warning', audience: 'all' }
  showBroadcastForm.value = 'standalone'
}

function openEscalateBroadcast(alert) {
  const severity = ['security', 'fire_disaster'].includes(alert.alert_type) ? 'critical' : 'warning'
  const label = alert.alert_type.replace('_', ' ')
  broadcastForm.value = {
    title: `Emergency: ${label}`,
    message: `An emergency has been reported on campus (${label}). Please follow safety instructions from campus staff.`,
    severity,
    audience: 'all',
  }
  showBroadcastForm.value = alert.id
}

async function submitBroadcast() {
  const url = showBroadcastForm.value === 'standalone'
    ? route('sos.broadcast.store')
    : route('sos.broadcast.from-sos', showBroadcastForm.value)
  const { data } = await axios.post(url, broadcastForm.value)
  emergencyAlerts.value.unshift(data)
  showBroadcastForm.value = false
}

async function resolveEmergencyAlert(alert) {
  const { data } = await axios.post(route('sos.broadcast.resolve', alert.id))
  const idx = emergencyAlerts.value.findIndex(a => a.id === alert.id)
  if (idx !== -1) emergencyAlerts.value[idx] = data
}

let emergencyChannel = null
function subscribeEmergencyChannel() {
  if (!window.Echo) return
  emergencyChannel = window.Echo.private('emergency-alerts')
    .listen('.emergency.alert.broadcast', (payload) => {
      if (!emergencyAlerts.value.some(a => a.id === payload.id)) {
        emergencyAlerts.value.unshift(payload)
      }
    })
    .listen('.emergency.alert.resolved', (payload) => {
      const idx = emergencyAlerts.value.findIndex(a => a.id === payload.id)
      if (idx !== -1) emergencyAlerts.value[idx] = payload
    })
}
</script>

<template>
  <Head title="SOS Command Center" />
  <AdminLayout title="SOS Command Center">
    <div class="mb-4 flex gap-2 border-b border-slate-200">
      <button class="px-3 py-2 text-sm font-medium" :class="activeTab === 'active' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500'" @click="activeTab = 'active'">Active</button>
      <button class="px-3 py-2 text-sm font-medium" :class="activeTab === 'history' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500'" @click="activeTab = 'history'">History</button>
      <button class="px-3 py-2 text-sm font-medium" :class="activeTab === 'stats' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500'" @click="activeTab = 'stats'">Stats</button>
    </div>

    <div v-show="activeTab === 'active'" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <div class="lg:col-span-2">
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Active Alerts</h2>
        <div v-if="activeAlerts.length === 0" class="rounded-lg border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400">
          No active SOS alerts.
        </div>
        <div v-for="alert in activeAlerts" :key="alert.id"
             class="mb-3 cursor-pointer rounded-xl border border-slate-200 bg-white p-4 hover:border-indigo-300"
             @click="selected = alert">
          <div class="flex items-center justify-between">
            <div>
              <span class="text-sm font-semibold text-slate-900">#{{ alert.id }} — {{ alert.alert_type.replace('_', ' ') }}</span>
              <span v-if="alert.is_silent" class="ml-2 rounded bg-slate-800 px-2 py-0.5 text-xs font-medium text-white">SILENT</span>
            </div>
            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(alert.status)">{{ alert.status }}</span>
          </div>
          <p class="mt-1 text-xs text-slate-500">Triggered {{ new Date(alert.triggered_at).toLocaleString('en-PH') }}</p>
        </div>

        <h2 class="mb-3 mt-6 text-xs font-semibold uppercase tracking-wide text-slate-500">Closed</h2>
        <div v-for="alert in closedAlerts" :key="alert.id" class="mb-2 rounded-lg border border-slate-100 p-3 text-sm text-slate-500">
          #{{ alert.id }} — {{ alert.alert_type.replace('_', ' ') }} — <span :class="statusClass(alert.status)" class="rounded px-1.5 py-0.5 text-xs">{{ alert.status }}</span>
        </div>

        <div class="mt-8 flex items-center justify-between">
          <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Emergency Alerts</h2>
          <button class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700"
                  @click="openStandaloneBroadcast">
            New Emergency Alert
          </button>
        </div>

        <div v-if="activeEmergencyAlerts.length === 0" class="mt-3 rounded-lg border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400">
          No active emergency alerts.
        </div>
        <div v-for="alert in activeEmergencyAlerts" :key="alert.id"
             class="mb-3 mt-3 rounded-xl border-2 border-red-200 bg-red-50/40 p-4">
          <div class="flex items-center justify-between">
            <span class="text-sm font-semibold text-slate-900">#{{ alert.id }} — {{ alert.title }}</span>
            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="severityClass[alert.severity]">{{ alert.severity }}</span>
          </div>
          <p class="mt-1 text-sm text-slate-600">{{ alert.message }}</p>
          <p class="mt-1 text-xs text-slate-500">
            Audience: {{ alert.audience }} · {{ alert.source === 'escalated' ? 'Escalated from SOS' : 'Manual' }}
            · {{ new Date(alert.created_at).toLocaleString('en-PH') }}
          </p>
          <button class="mt-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700"
                  @click="resolveEmergencyAlert(alert)">
            Resolve
          </button>
        </div>

        <div v-if="closedEmergencyAlerts.length" class="mt-4 space-y-2">
          <div v-for="alert in closedEmergencyAlerts" :key="alert.id" class="rounded-lg border border-slate-100 p-3 text-sm text-slate-500">
            #{{ alert.id }} — {{ alert.title }} — <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-xs text-emerald-700">resolved</span>
          </div>
        </div>
      </div>

      <div v-if="selected" class="rounded-xl border border-slate-200 bg-white p-5">
        <h3 class="text-sm font-semibold text-slate-900">Alert #{{ selected.id }}</h3>
        <p class="mt-1 text-xs text-slate-500">{{ selected.alert_type.replace('_', ' ') }} · {{ selected.status }}</p>

        <div class="mt-4">
          <AlertLocationPanel :alert="selected" />
        </div>

        <div class="mt-4">
          <ResponderList :alert="selected" :current-user-id="authUserId" @updated="(data) => { upsertAlert(data); selected = data }" />
        </div>

        <div class="mt-4 flex flex-col gap-2">
          <button class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white" @click="act(selected, 'acknowledge')">Acknowledge</button>
          <button class="rounded-lg bg-orange-600 px-3 py-2 text-sm font-medium text-white" @click="act(selected, 'verify')">Verify (real emergency)</button>
          <button class="rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white" @click="openEscalateBroadcast(selected)">
            Broadcast Public Alert
          </button>

          <div class="mt-2">
            <input v-model="falseAlarmReason" placeholder="Reason for false alarm" class="mb-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
            <button class="w-full rounded-lg bg-slate-600 px-3 py-2 text-sm font-medium text-white" :disabled="!falseAlarmReason"
                    @click="act(selected, 'false-alarm', { reason: falseAlarmReason })">Mark False Alarm</button>
          </div>

          <div class="mt-2">
            <textarea v-model="resolutionNotes" placeholder="Resolution notes" class="mb-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
            <button class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white"
                    @click="act(selected, 'resolve', { notes: resolutionNotes })">Resolve</button>
          </div>
        </div>

        <div class="mt-5">
          <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Timeline</h4>
          <ul class="mt-2 space-y-1 text-xs text-slate-600">
            <li v-for="(e, i) in selected.events" :key="i">{{ e.type }} — {{ new Date(e.created_at).toLocaleString('en-PH') }}</li>
          </ul>
        </div>
      </div>
    </div>

    <CommandCenterHistoryTab v-if="activeTab === 'history'" />
    <CommandCenterStatsTab v-if="activeTab === 'stats'" />

    <!-- Broadcast form modal -->
    <div v-if="showBroadcastForm" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 p-4">
      <div class="w-full max-w-md rounded-2xl border-4 border-red-600 bg-white p-6 shadow-2xl">
        <h3 class="text-lg font-semibold text-slate-900">
          {{ showBroadcastForm === 'standalone' ? 'New Emergency Alert' : 'Broadcast Public Alert' }}
        </h3>

        <div class="mt-4 space-y-3">
          <input v-model="broadcastForm.title" placeholder="Title" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <textarea v-model="broadcastForm.message" placeholder="Message" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>

          <div class="flex gap-2">
            <select v-model="broadcastForm.severity" class="w-1/2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
              <option value="info">Info</option>
              <option value="warning">Warning</option>
              <option value="critical">Critical</option>
            </select>
            <select v-model="broadcastForm.audience" class="w-1/2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
              <option value="all">Everyone</option>
              <option value="employees">Employees</option>
              <option value="students">Students</option>
              <option value="parents">Parents</option>
            </select>
          </div>
        </div>

        <div class="mt-5 flex gap-2">
          <button class="flex-1 rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200"
                  @click="showBroadcastForm = false">
            Cancel
          </button>
          <button class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                  :disabled="!broadcastForm.title || !broadcastForm.message"
                  @click="submitBroadcast">
            Send
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>

  <!-- Second, independent overlay instance: reacts to a raw un-triaged SOS
       trigger (sos-responders channel, responder-only), ahead of any
       decision to promote it to the site-wide emergency-alerts broadcast
       that AdminLayout's own overlay reacts to. -->
  <EmergencyBorderOverlay :active="activeAlerts.length > 0" />
</template>
