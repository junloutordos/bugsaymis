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
import {
  XMarkIcon, BellAlertIcon, ShieldCheckIcon, MegaphoneIcon, ClockIcon,
  AcademicCapIcon, UserIcon, ExclamationTriangleIcon, CheckBadgeIcon,
  ChartBarIcon, ArchiveBoxIcon, SignalIcon,
} from '@heroicons/vue/24/outline'
import { CheckCircleIcon as CheckCircleSolid } from '@heroicons/vue/24/solid'

const props = defineProps({ alerts: Array, emergencyAlerts: Array, authUserId: Number })

const activeTab = ref('active')

const alerts = ref([...props.alerts])
const selected = ref(null)
const falseAlarmReason = ref('')
const resolutionNotes = ref('')

const activeAlerts = computed(() => alerts.value.filter(a => !['resolved', 'false_alarm'].includes(a.status)))
const closedAlerts = computed(() => alerts.value.filter(a => ['resolved', 'false_alarm'].includes(a.status)))

const statusStyles = {
  triggered: { badge: 'bg-red-100 text-red-700', ring: 'ring-red-200', accent: 'border-l-red-500' },
  acknowledged: { badge: 'bg-amber-100 text-amber-700', ring: 'ring-amber-200', accent: 'border-l-amber-500' },
  verified: { badge: 'bg-orange-100 text-orange-700', ring: 'ring-orange-200', accent: 'border-l-orange-500' },
  escalated: { badge: 'bg-red-200 text-red-800', ring: 'ring-red-300', accent: 'border-l-red-600' },
  resolved: { badge: 'bg-emerald-100 text-emerald-700', ring: 'ring-emerald-200', accent: 'border-l-emerald-500' },
  false_alarm: { badge: 'bg-slate-100 text-slate-500', ring: 'ring-slate-200', accent: 'border-l-slate-300' },
}

function statusClass(status) {
  return statusStyles[status]?.badge ?? 'bg-slate-100 text-slate-600'
}
function accentClass(status) {
  return statusStyles[status]?.accent ?? 'border-l-slate-300'
}

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

function openAlert(alert) {
  selected.value = alert
}
function closeAlert() {
  selected.value = null
  falseAlarmReason.value = ''
  resolutionNotes.value = ''
}

// ── Single primary action based on current status ──────────────────────────
const primaryAction = computed(() => {
  if (!selected.value) return null
  switch (selected.value.status) {
    case 'triggered':
      return { key: 'acknowledge', label: 'Acknowledge', icon: BellAlertIcon, classes: 'bg-amber-600 hover:bg-amber-700' }
    case 'acknowledged':
      return { key: 'verify', label: 'Verify (real emergency)', icon: ShieldCheckIcon, classes: 'bg-orange-600 hover:bg-orange-700' }
    case 'verified':
    case 'escalated':
      return { key: 'broadcast', label: 'Broadcast Public Alert', icon: MegaphoneIcon, classes: 'bg-red-600 hover:bg-red-700' }
    default:
      return null
  }
})

function runPrimaryAction() {
  if (!primaryAction.value || !selected.value) return
  if (primaryAction.value.key === 'broadcast') {
    openEscalateBroadcast(selected.value)
    return
  }
  act(selected.value, primaryAction.value.key)
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
    <div class="mb-6 flex items-center gap-1 rounded-xl bg-slate-100 p-1 text-sm">
      <button
        class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 font-medium transition-colors"
        :class="activeTab === 'active' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
        @click="activeTab = 'active'">
        <BellAlertIcon class="h-4 w-4" /> Active
        <span v-if="activeAlerts.length" class="ml-1 rounded-full bg-red-100 px-1.5 py-0.5 text-xs font-semibold text-red-700">{{ activeAlerts.length }}</span>
      </button>
      <button
        class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 font-medium transition-colors"
        :class="activeTab === 'history' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
        @click="activeTab = 'history'">
        <ArchiveBoxIcon class="h-4 w-4" /> History
      </button>
      <button
        class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 font-medium transition-colors"
        :class="activeTab === 'stats' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
        @click="activeTab = 'stats'">
        <ChartBarIcon class="h-4 w-4" /> Stats
      </button>
    </div>

    <div v-show="activeTab === 'active'">
      <h2 class="mb-3 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
        <BellAlertIcon class="h-4 w-4" /> Active Alerts
      </h2>
      <div v-if="activeAlerts.length === 0" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-8 text-center text-sm text-slate-400">
        No active SOS alerts.
      </div>
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="alert in activeAlerts" :key="alert.id"
             class="group cursor-pointer rounded-2xl border border-slate-200 border-l-4 bg-white p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
             :class="[accentClass(alert.status), alert.status === 'triggered' ? 'animate-pulse' : '']"
             @click="openAlert(alert)">
          <div class="flex items-center justify-between">
            <span class="text-sm font-semibold text-slate-900">#{{ alert.id }} — {{ alertTypeLabels[alert.alert_type] ?? alert.alert_type }}</span>
            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(alert.status)">{{ alert.status }}</span>
          </div>
          <div class="mt-2 flex items-center gap-1.5 text-sm text-slate-700">
            <AcademicCapIcon v-if="alert.reporter?.type === 'student'" class="h-4 w-4 shrink-0 text-slate-400" />
            <UserIcon v-else class="h-4 w-4 shrink-0 text-slate-400" />
            <span class="truncate font-medium">{{ reporterLabel(alert) }}</span>
          </div>
          <span v-if="alert.is_silent" class="mt-2 inline-block rounded bg-slate-800 px-2 py-0.5 text-xs font-medium text-white">SILENT</span>
          <p class="mt-2 flex items-center gap-1 text-xs text-slate-400">
            <ClockIcon class="h-3.5 w-3.5" /> {{ new Date(alert.triggered_at).toLocaleString('en-PH') }}
          </p>
        </div>
      </div>

      <h2 class="mb-3 mt-8 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
        <CheckBadgeIcon class="h-4 w-4" /> Closed
      </h2>
      <div class="space-y-2">
        <div v-for="alert in closedAlerts" :key="alert.id"
             class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-100 p-3 text-sm text-slate-600 transition-colors hover:border-slate-200 hover:bg-slate-50"
             @click="openAlert(alert)">
          <div class="flex items-center gap-2">
            <span class="font-medium text-slate-700">#{{ alert.id }} — {{ alertTypeLabels[alert.alert_type] ?? alert.alert_type }}</span>
            <span class="text-slate-400">·</span>
            <span>{{ reporterLabel(alert) }}</span>
          </div>
          <span :class="statusClass(alert.status)" class="rounded-full px-2 py-0.5 text-xs">{{ alert.status }}</span>
        </div>
      </div>

      <div class="mt-10 flex items-center justify-between">
        <h2 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
          <MegaphoneIcon class="h-4 w-4" /> Emergency Alerts
        </h2>
        <button class="flex items-center gap-1.5 rounded-xl bg-red-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-red-700"
                @click="openStandaloneBroadcast">
          <MegaphoneIcon class="h-4 w-4" /> New Emergency Alert
        </button>
      </div>

      <div v-if="activeEmergencyAlerts.length === 0" class="mt-3 rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-8 text-center text-sm text-slate-400">
        No active emergency alerts.
      </div>
      <div v-for="alert in activeEmergencyAlerts" :key="alert.id"
           class="mb-3 mt-3 rounded-2xl border-2 border-red-200 bg-red-50/40 p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-sm font-semibold text-slate-900">#{{ alert.id }} — {{ alert.title }}</span>
          <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="severityClass[alert.severity]">{{ alert.severity }}</span>
        </div>
        <p class="mt-1 text-sm text-slate-600">{{ alert.message }}</p>
        <p class="mt-1 text-xs text-slate-500">
          Audience: {{ alert.audience }} · {{ alert.source === 'escalated' ? 'Escalated from SOS' : 'Manual' }}
          · {{ new Date(alert.created_at).toLocaleString('en-PH') }}
        </p>
        <button class="mt-3 flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700"
                @click="resolveEmergencyAlert(alert)">
          <CheckCircleSolid class="h-4 w-4" /> Resolve
        </button>
      </div>

      <div v-if="closedEmergencyAlerts.length" class="mt-4 space-y-2">
        <div v-for="alert in closedEmergencyAlerts" :key="alert.id" class="rounded-xl border border-slate-100 p-3 text-sm text-slate-500">
          #{{ alert.id }} — {{ alert.title }} — <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-xs text-emerald-700">resolved</span>
        </div>
      </div>
    </div>

    <CommandCenterHistoryTab v-if="activeTab === 'history'" />
    <CommandCenterStatsTab v-if="activeTab === 'stats'" />

    <!-- Broadcast form modal -->
    <div v-if="showBroadcastForm" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
      <div class="w-full max-w-md rounded-2xl border-4 border-red-600 bg-white p-6 shadow-2xl">
        <h3 class="flex items-center gap-2 text-lg font-semibold text-slate-900">
          <MegaphoneIcon class="h-5 w-5 text-red-600" />
          {{ showBroadcastForm === 'standalone' ? 'New Emergency Alert' : 'Broadcast Public Alert' }}
        </h3>

        <div class="mt-4 space-y-3">
          <input v-model="broadcastForm.title" placeholder="Title" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          <textarea v-model="broadcastForm.message" placeholder="Message" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>

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
          <button class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                  :disabled="!broadcastForm.title || !broadcastForm.message"
                  @click="submitBroadcast">
            Send
          </button>
        </div>
      </div>
    </div>

    <!-- Alert Card — full-screen overlay -->
    <Transition name="fade">
      <div v-if="selected" class="fixed inset-0 z-[90] overflow-y-auto bg-slate-50">
        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white/90 px-4 py-3 backdrop-blur sm:px-8">
          <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-full" :class="statusClass(selected.status)">
              <ExclamationTriangleIcon class="h-5 w-5" />
            </span>
            <div>
              <h3 class="text-base font-semibold text-slate-900">
                Alert #{{ selected.id }} — {{ alertTypeLabels[selected.alert_type] ?? selected.alert_type }}
              </h3>
              <p class="text-xs text-slate-500">{{ reporterLabel(selected) }} · <span class="capitalize">{{ selected.status }}</span></p>
            </div>
          </div>
          <button class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition-colors hover:bg-slate-100" @click="closeAlert">
            <XMarkIcon class="h-5 w-5" />
          </button>
        </div>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-8">
          <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
              <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reporter</h4>
                <div class="mt-3 flex items-center gap-3">
                  <span class="flex h-11 w-11 items-center justify-center rounded-full bg-indigo-100 text-indigo-600">
                    <AcademicCapIcon v-if="selected.reporter?.type === 'student'" class="h-6 w-6" />
                    <UserIcon v-else class="h-6 w-6" />
                  </span>
                  <div>
                    <p class="text-sm font-semibold text-slate-900">{{ selected.reporter?.name ?? 'Unknown' }}</p>
                    <p v-if="selected.reporter?.type === 'student'" class="text-xs text-slate-500">
                      <template v-if="selected.reporter?.grade_level">Grade {{ selected.reporter.grade_level }}</template>
                      <template v-if="selected.reporter?.section"> - {{ selected.reporter.section }}</template>
                      <template v-if="!selected.reporter?.grade_level && !selected.reporter?.section">Student</template>
                    </p>
                    <p v-else class="text-xs text-slate-500">Employee</p>
                  </div>
                  <span v-if="selected.is_silent" class="ml-auto rounded bg-slate-800 px-2 py-0.5 text-xs font-medium text-white">SILENT</span>
                </div>
              </div>

              <AlertLocationPanel :alert="selected" />

              <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h4 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                  <SignalIcon class="h-4 w-4" /> Timeline
                </h4>
                <ol class="mt-3 space-y-3 border-l border-slate-200 pl-4">
                  <li v-for="(e, i) in selected.events" :key="i" class="relative text-sm">
                    <span class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full bg-indigo-500 ring-2 ring-white"></span>
                    <span class="font-medium capitalize text-slate-800">{{ e.type.replace('_', ' ') }}</span>
                    <span class="ml-1.5 text-xs text-slate-400">{{ new Date(e.created_at).toLocaleString('en-PH') }}</span>
                  </li>
                  <li v-if="!selected.events?.length" class="text-sm text-slate-400">No events yet.</li>
                </ol>
              </div>
            </div>

            <div class="space-y-6">
              <ResponderList :alert="selected" :current-user-id="authUserId" @updated="(data) => { upsertAlert(data); selected = data }" />

              <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</h4>

                <button
                  v-if="primaryAction"
                  class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors"
                  :class="primaryAction.classes"
                  @click="runPrimaryAction"
                >
                  <component :is="primaryAction.icon" class="h-4 w-4" />
                  {{ primaryAction.label }}
                </button>
                <p v-else class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-400">
                  This alert is closed — no further action needed.
                </p>

                <div v-if="!['resolved', 'false_alarm'].includes(selected.status)" class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                  <div>
                    <input v-model="falseAlarmReason" placeholder="Reason for false alarm" class="mb-1.5 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <button class="w-full rounded-xl bg-slate-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!falseAlarmReason"
                            @click="act(selected, 'false-alarm', { reason: falseAlarmReason })">Mark False Alarm</button>
                  </div>

                  <div>
                    <textarea v-model="resolutionNotes" placeholder="Resolution notes" class="mb-1.5 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                    <button class="w-full rounded-xl bg-emerald-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-emerald-700"
                            @click="act(selected, 'resolve', { notes: resolutionNotes })">Resolve</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </AdminLayout>

  <!-- Second, independent overlay instance: reacts to a raw un-triaged SOS
       trigger (sos-responders channel, responder-only), ahead of any
       decision to promote it to the site-wide emergency-alerts broadcast
       that AdminLayout's own overlay reacts to. -->
  <EmergencyBorderOverlay :active="activeAlerts.length > 0" />
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
