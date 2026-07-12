<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
  CursorArrowRippleIcon, ClockIcon, SignalIcon, ClipboardIcon,
  CheckIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  enabled:  { type: Boolean, default: false },
  requests: { type: Array,  default: () => [] },
  counts:   { type: Object, default: () => ({}) },
  filters:  { type: Object, default: () => ({}) },
  pollUrl:  { type: String, default: '' },
})

const requests = ref([...props.requests])
const counts = reactive({ waiting: 0, claimed: 0, in_session: 0, ...props.counts })
const statusFilter = ref(props.filters.status ?? '')

const busy = reactive({}) // id -> bool, disables a row's buttons mid-action

// ── Live polling ────────────────────────────────────────────────────────────
let timer = null
async function refresh() {
  try {
    const { data } = await axios.get(props.pollUrl, { params: { status: statusFilter.value || undefined } })
    requests.value = data.requests
    Object.assign(counts, data.counts)
  } catch (_) { /* transient — next tick retries */ }
}
onMounted(() => { if (props.enabled) timer = setInterval(refresh, 10000) })
onUnmounted(() => { if (timer) clearInterval(timer) })

function applyFilter() {
  router.get(route('atlas-sentinel.remote-help.index'), { status: statusFilter.value || undefined }, { preserveState: true, preserveScroll: true })
}

// ── Actions ─────────────────────────────────────────────────────────────────
async function claim(r) {
  busy[r.id] = true
  try {
    const { data } = await axios.post(route('atlas-sentinel.remote-help.claim', r.id))
    patchRow(data.request)
  } catch (e) {
    alertError(e, 'Could not claim this request.')
    await refresh()
  } finally { busy[r.id] = false }
}

async function endSession(r) {
  busy[r.id] = true
  try {
    const { data } = await axios.post(route('atlas-sentinel.remote-help.end', r.id))
    patchRow(data.request)
  } catch (e) {
    alertError(e, 'Could not end this session.')
  } finally { busy[r.id] = false }
}

// Connect modal
const revealModal = reactive({ show: false, loading: false, anydesk_id: '', hostname: '' })
async function reveal(r) {
  revealModal.show = true
  revealModal.loading = true
  revealModal.hostname = r.hostname
  revealModal.anydesk_id = ''
  try {
    const { data } = await axios.post(route('atlas-sentinel.remote-help.reveal', r.id))
    revealModal.anydesk_id = data.anydesk_id
    await refresh()
  } catch (e) {
    revealModal.show = false
    alertError(e, 'Could not open the connection details yet.')
  } finally { revealModal.loading = false }
}

function patchRow(row) {
  const i = requests.value.findIndex(x => x.id === row.id)
  if (i !== -1) requests.value[i] = row
}

function alertError(e, fallback) {
  window.alert(e?.response?.data?.message ?? fallback)
}

// ── Clipboard ─────────────────────────────────────────────────────────────
const copied = ref('')
async function copy(text, key) {
  try {
    await navigator.clipboard.writeText(text)
    copied.value = key
    setTimeout(() => { if (copied.value === key) copied.value = '' }, 1500)
  } catch (_) { /* clipboard blocked — user can select manually */ }
}

// ── Display helpers ─────────────────────────────────────────────────────────
const STATUS_LABEL = {
  waiting: 'Waiting', claimed: 'Claimed', in_session: 'In session',
  completed: 'Completed', canceled: 'Canceled', timed_out: 'Timed out', failed: 'Failed',
}
const STATUS_COLOR = {
  waiting: 'amber', claimed: 'blue', in_session: 'green',
  completed: 'slate', canceled: 'slate', timed_out: 'orange', failed: 'red',
}
function statusLabel(s) { return STATUS_LABEL[s] ?? s }
function statusColor(s) { return STATUS_COLOR[s] ?? 'slate' }

function waitedSince(iso) {
  if (!iso) return '—'
  const secs = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000))
  if (secs < 60) return `${secs}s`
  if (secs < 3600) return `${Math.floor(secs / 60)}m`
  return `${Math.floor(secs / 3600)}h ${Math.floor((secs % 3600) / 60)}m`
}
function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const filterOptions = [
  { value: '', label: 'Live + recent' },
  { value: 'waiting', label: 'Waiting' },
  { value: 'claimed', label: 'Claimed' },
  { value: 'in_session', label: 'In session' },
  { value: 'completed', label: 'Completed' },
  { value: 'failed', label: 'Failed' },
]
</script>

<template>
  <Head title="Remote Help" />
  <AdminLayout title="Remote Help">
    <div class="space-y-5">
      <AppPageHeader
        title="Remote Help Queue"
        subtitle="Attended remote-desktop requests from Atlas Sentinel devices"
      />

      <AppCard v-if="!enabled">
        <div class="flex items-start gap-3">
          <ExclamationTriangleIcon class="h-5 w-5 text-warning-500 shrink-0 mt-0.5" />
          <div class="text-sm text-slate-600">
            Remote help is currently <strong>disabled</strong>. Requests won't be accepted from devices
            until the RustDesk relay is live and the feature flag is switched on.
          </div>
        </div>
      </AppCard>

      <!-- Stat cards -->
      <div class="grid grid-cols-3 gap-3">
        <AppCard :padded="false">
          <div class="px-4 py-3 flex items-center gap-3">
            <div class="h-9 w-9 rounded-full bg-warning-50 flex items-center justify-center shrink-0">
              <ClockIcon class="h-5 w-5 text-warning-500" />
            </div>
            <div>
              <p class="text-2xl font-bold text-warning-600">{{ counts.waiting }}</p>
              <p class="text-xs text-slate-500">Waiting</p>
            </div>
          </div>
        </AppCard>
        <AppCard :padded="false">
          <div class="px-4 py-3 flex items-center gap-3">
            <div class="h-9 w-9 rounded-full bg-blue-50 flex items-center justify-center shrink-0">
              <CursorArrowRippleIcon class="h-5 w-5 text-blue-500" />
            </div>
            <div>
              <p class="text-2xl font-bold text-blue-600">{{ counts.claimed }}</p>
              <p class="text-xs text-slate-500">Claimed</p>
            </div>
          </div>
        </AppCard>
        <AppCard :padded="false">
          <div class="px-4 py-3 flex items-center gap-3">
            <div class="h-9 w-9 rounded-full bg-success-50 flex items-center justify-center shrink-0">
              <SignalIcon class="h-5 w-5 text-success-500" />
            </div>
            <div>
              <p class="text-2xl font-bold text-success-600">{{ counts.in_session }}</p>
              <p class="text-xs text-slate-500">In session</p>
            </div>
          </div>
        </AppCard>
      </div>

      <!-- Filter -->
      <div class="flex items-center gap-2">
        <select
          v-model="statusFilter"
          @change="applyFilter"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option v-for="o in filterOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>
        <span class="text-xs text-slate-400">Auto-refreshes every 10s</span>
      </div>

      <!-- Queue -->
      <AppCard :padded="false">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                <th class="px-4 py-3">Device</th>
                <th class="px-4 py-3">Request</th>
                <th class="px-4 py-3">Ticket</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Waiting</th>
                <th class="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in requests" :key="r.id" class="border-b border-slate-50 align-top">
                <td class="px-4 py-3">
                  <p class="font-medium text-slate-800">{{ r.hostname ?? '—' }}</p>
                  <p class="text-xs text-slate-500">{{ r.description ?? r.property_no ?? '' }}</p>
                  <p v-if="r.owner_name" class="text-xs text-slate-400">{{ r.owner_name }}</p>
                </td>
                <td class="px-4 py-3 max-w-xs">
                  <p class="text-slate-600 whitespace-pre-line">{{ r.note || '—' }}</p>
                  <p v-if="r.status === 'failed' && r.error_message" class="text-xs text-danger-600 mt-1">
                    {{ r.error_message }}
                  </p>
                </td>
                <td class="px-4 py-3">
                  <span v-if="r.itjr_no" class="text-xs text-slate-600">{{ r.itjr_no }}</span>
                  <span v-else class="text-xs text-slate-400 italic">no ticket</span>
                </td>
                <td class="px-4 py-3">
                  <AppBadge :color="statusColor(r.status)">{{ statusLabel(r.status) }}</AppBadge>
                  <p v-if="r.claimer_name && r.status !== 'waiting'" class="text-xs text-slate-400 mt-1">
                    {{ r.is_mine ? 'You' : r.claimer_name }}
                  </p>
                </td>
                <td class="px-4 py-3 text-slate-500 text-xs whitespace-nowrap">
                  <span v-if="['waiting','claimed'].includes(r.status)">{{ waitedSince(r.created_at) }}</span>
                  <span v-else>{{ formatDate(r.created_at) }}</span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex justify-end gap-2">
                    <!-- Waiting: anyone can claim -->
                    <AppButton
                      v-if="r.status === 'waiting'"
                      size="sm" variant="primary"
                      :loading="busy[r.id]"
                      @click="claim(r)"
                    >Claim</AppButton>

                    <!-- Claimed/in-session by me: connect + end -->
                    <template v-if="['claimed','in_session'].includes(r.status) && r.is_mine">
                      <AppButton
                        size="sm" variant="success"
                        :disabled="!r.id_ready"
                        :title="r.id_ready ? '' : 'Waiting for the device to report its AnyDesk ID…'"
                        @click="reveal(r)"
                      >{{ r.id_ready ? 'Connect' : 'Preparing…' }}</AppButton>
                      <AppButton size="sm" variant="secondary" :loading="busy[r.id]" @click="endSession(r)">End</AppButton>
                    </template>

                    <!-- Claimed by someone else -->
                    <span v-else-if="['claimed','in_session'].includes(r.status) && !r.is_mine" class="text-xs text-slate-400 self-center">
                      Handled by {{ r.claimer_name }}
                    </span>
                  </div>
                </td>
              </tr>
              <tr v-if="!requests.length">
                <td colspan="6" class="px-4 py-10">
                  <EmptyState
                    :icon="CursorArrowRippleIcon"
                    title="No remote-help requests"
                    subtitle="Requests raised from the Atlas Sentinel tray will appear here."
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>
    </div>

    <!-- Connect (AnyDesk) modal -->
    <AppModal :show="revealModal.show" title="Connect with AnyDesk" size="md" @close="revealModal.show = false">
      <div class="space-y-4">
        <p class="text-sm text-slate-500">
          Open your AnyDesk app and connect to this ID. The user on
          <strong>{{ revealModal.hostname }}</strong> will be asked to click <strong>Accept</strong>
          on their screen before the session starts.
        </p>

        <div v-if="revealModal.loading" class="text-sm text-slate-400 py-6 text-center">Fetching the AnyDesk ID…</div>

        <template v-else>
          <div>
            <label class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">AnyDesk ID</label>
            <div class="mt-1 flex items-center gap-2">
              <code class="flex-1 rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-base font-mono text-slate-800">{{ revealModal.anydesk_id || '—' }}</code>
              <AppButton size="sm" variant="ghost" @click="copy(revealModal.anydesk_id, 'id')">
                <CheckIcon v-if="copied === 'id'" class="h-4 w-4 text-success-500" />
                <ClipboardIcon v-else class="h-4 w-4" />
              </AppButton>
            </div>
          </div>

          <a
            v-if="revealModal.anydesk_id"
            :href="`anydesk:${revealModal.anydesk_id}`"
            class="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:text-indigo-700"
          >
            <CursorArrowRippleIcon class="h-4 w-4" />
            Launch AnyDesk to this ID
          </a>
        </template>
      </div>

      <template #footer>
        <div class="flex justify-end">
          <AppButton variant="secondary" @click="revealModal.show = false">Done</AppButton>
        </div>
      </template>
    </AppModal>
  </AdminLayout>
</template>
