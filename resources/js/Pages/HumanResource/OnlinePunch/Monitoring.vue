<template>
  <Head title="Online Time Punches — Monitoring" />
  <AdminLayout title="Online Time Punches — Monitoring">
    <div class="space-y-5">

      <AppPageHeader title="Online Time Punches" subtitle="Monitor face-verified time punches across employees." />

      <!-- Filters -->
      <AppFilterBar>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Month</label>
          <input v-model="filterMonth" type="month"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
          <select v-model="filterStatus"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All</option>
            <option value="verified">Verified</option>
            <option value="manual_review">Under Review</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>

        <div class="flex-1 min-w-[160px]">
          <label class="block text-xs font-medium text-slate-600 mb-1">Search Employee</label>
          <input v-model="searchQuery" type="text" placeholder="Name…"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
        </div>

        <template #actions>
          <AppButton size="sm" :loading="loading" @click="load">
            {{ loading ? 'Loading…' : 'Apply' }}
          </AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :loading="loading" :is-empty="!rows.length" :skeleton-cols="10">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Employee</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Punch</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Time</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Liveness</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Match Score</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Location</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Network</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Photo</th>
          </tr>
        </template>

        <tr v-for="row in rows" :key="row.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-slate-800">{{ row.user?.name }}</td>
          <td class="px-4 py-3 text-slate-600">{{ row.work_date }}</td>
          <td class="px-4 py-3 text-slate-600">{{ punchLabel(row.punch_type) }}</td>
          <td class="px-4 py-3 text-slate-600">{{ fmtTime(row.punched_at) }}</td>
          <td class="px-4 py-3 text-center text-slate-600">{{ row.liveness_confidence ?? '—' }}</td>
          <td class="px-4 py-3 text-center text-slate-600">{{ row.match_score ?? '—' }}</td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="geofenceBadgeColor(row.geofence_status)">{{ geofenceLabel(row.geofence_status, row.distance_meters) }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="networkBadgeColor(row.network_status)">{{ networkLabel(row.network_status) }}</AppBadge>
            <p v-if="row.ip_address" class="text-[11px] text-slate-400 mt-1">{{ row.ip_address }}</p>
          </td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="statusBadgeColor(row.match_status)">{{ statusLabel(row.match_status) }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <a v-if="row.photo_url" :href="row.photo_url" target="_blank" class="text-indigo-600 hover:underline text-xs">View</a>
            <span v-else class="text-slate-300 text-xs">—</span>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="row in rows" :key="row.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ row.user?.name }}</p>
                <p class="text-xs text-slate-500">{{ row.work_date }} &middot; {{ punchLabel(row.punch_type) }} &middot; {{ fmtTime(row.punched_at) }}</p>
              </div>
              <AppBadge :color="statusBadgeColor(row.match_status)">{{ statusLabel(row.match_status) }}</AppBadge>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-500">
              <span>Liveness {{ row.liveness_confidence ?? '—' }} &middot; Match {{ row.match_score ?? '—' }}</span>
              <a v-if="row.photo_url" :href="row.photo_url" target="_blank" class="text-indigo-600 hover:underline">View photo</a>
              <span v-else class="text-slate-300">—</span>
            </div>
            <div class="flex items-center gap-2">
              <AppBadge :color="geofenceBadgeColor(row.geofence_status)">{{ geofenceLabel(row.geofence_status, row.distance_meters) }}</AppBadge>
              <AppBadge :color="networkBadgeColor(row.network_status)">{{ networkLabel(row.network_status) }}</AppBadge>
              <span v-if="row.ip_address" class="text-[11px] text-slate-400">{{ row.ip_address }}</span>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No online time punches found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="meta?.current_page"
            :total-pages="meta?.last_page"
            :total="meta?.total"
            @prev="load(meta.current_page - 1)"
            @next="load(meta.current_page + 1)"
            @page="load($event)"
          />
        </template>
      </AppTable>

      <!-- Geofence Zones (Admin/HR only) -->
      <AppCard v-if="canManageGeofence" title="Geofence Zones" :padded="false">
        <template #header>
          <AppButton size="sm" @click="openZoneForm()">+ Add Zone</AppButton>
        </template>

        <div class="p-5 space-y-3">
          <p class="text-sm text-slate-500">
            Employees can only punch when their device's GPS location is inside one of these zones.
            If no active zone exists, geofencing is not enforced.
          </p>

          <div v-if="!zones.length" class="text-sm text-slate-400 py-4 text-center">No geofence zones configured yet — punches are not location-restricted.</div>

          <div v-for="zone in zones" :key="zone.id"
               class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-3">
            <div>
              <p class="font-medium text-slate-800">{{ zone.label }}</p>
              <p class="text-xs text-slate-500">{{ zone.latitude }}, {{ zone.longitude }} &middot; {{ zone.radius_meters }}m radius</p>
            </div>
            <div class="flex items-center gap-2">
              <AppBadge :color="zone.is_active ? 'green' : 'slate'">{{ zone.is_active ? 'Active' : 'Inactive' }}</AppBadge>
              <AppButton size="sm" variant="secondary" @click="openZoneForm(zone)">Edit</AppButton>
              <AppButton size="sm" variant="danger" @click="deleteZone(zone)">Delete</AppButton>
            </div>
          </div>
        </div>
      </AppCard>

      <!-- Trusted Networks (Admin/HR only) -->
      <AppCard v-if="canManageGeofence" title="Trusted Networks" :padded="false">
        <template #header>
          <AppButton size="sm" @click="openRuleForm()">+ Add Rule</AppButton>
        </template>

        <div class="p-5 space-y-3">
          <p class="text-sm text-slate-500">
            Informational only — a matching IP/CIDR marks a punch "Trusted" for HR's awareness, but an unmatched IP
            never blocks or flags a punch, since several campus ISPs use dynamic or CGNAT addresses.
          </p>

          <div v-if="!networkRules.length" class="text-sm text-slate-400 py-4 text-center">No network rules configured yet.</div>

          <div v-for="rule in networkRules" :key="rule.id"
               class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-3">
            <div>
              <p class="font-medium text-slate-800">{{ rule.label }}</p>
              <p class="text-xs text-slate-500">{{ rule.cidr }}</p>
            </div>
            <div class="flex items-center gap-2">
              <AppBadge :color="rule.is_active ? 'green' : 'slate'">{{ rule.is_active ? 'Active' : 'Inactive' }}</AppBadge>
              <AppButton size="sm" variant="secondary" @click="openRuleForm(rule)">Edit</AppButton>
              <AppButton size="sm" variant="danger" @click="deleteRule(rule)">Delete</AppButton>
            </div>
          </div>
        </div>
      </AppCard>
    </div>

    <!-- Zone Form Modal -->
    <AppModal :show="showZoneForm" :title="editingZone ? 'Edit Geofence Zone' : 'Add Geofence Zone'" size="sm" @close="showZoneForm = false">
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Label</label>
          <input v-model="zoneForm.label" type="text" placeholder="e.g. Main Campus" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Latitude</label>
            <input v-model.number="zoneForm.latitude" type="number" step="any" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Longitude</label>
            <input v-model.number="zoneForm.longitude" type="number" step="any" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Radius (meters)</label>
          <input v-model.number="zoneForm.radius_meters" type="number" min="10" max="5000" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
          <input v-model="zoneForm.is_active" type="checkbox" class="rounded border-slate-300" />
          Active (enforce this zone)
        </label>
        <div class="flex gap-3 pt-2">
          <AppButton variant="secondary" block @click="showZoneForm = false">Cancel</AppButton>
          <AppButton block :loading="savingZone" @click="saveZone">Save</AppButton>
        </div>
      </div>
    </AppModal>

    <!-- Network Rule Form Modal -->
    <AppModal :show="showRuleForm" :title="editingRule ? 'Edit Network Rule' : 'Add Network Rule'" size="sm" @close="showRuleForm = false">
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Label</label>
          <input v-model="ruleForm.label" type="text" placeholder="e.g. Main ISP" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">IP or CIDR</label>
          <input v-model="ruleForm.cidr" type="text" placeholder="e.g. 136.239.178.120/32" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          <p class="text-xs text-slate-400 mt-1">A single IP is treated as /32. Avoid wide ranges shared with other customers on the same ISP.</p>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
          <input v-model="ruleForm.is_active" type="checkbox" class="rounded border-slate-300" />
          Active (match this rule)
        </label>
        <div class="flex gap-3 pt-2">
          <AppButton variant="secondary" block @click="showRuleForm = false">Cancel</AppButton>
          <AppButton block :loading="savingRule" @click="saveRule">Save</AppButton>
        </div>
      </div>
    </AppModal>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppCard from '@/Components/AppCard.vue'
import AppModal from '@/Components/AppModal.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import Swal from 'sweetalert2'
import axios from 'axios'

const currentMonth = new Date().toISOString().slice(0, 7)
const filterMonth  = ref(currentMonth)
const filterStatus = ref('')
const searchQuery  = ref('')
const rows         = ref([])
const meta         = ref(null)
const loading      = ref(false)

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await axios.get(route('hr.online-punch.monitor'), {
      params: { month: filterMonth.value, status: filterStatus.value, search: searchQuery.value, page },
    })
    rows.value = data.data
    meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total }
  } finally {
    loading.value = false
  }
}

function punchLabel(type) {
  return { time_in_am: 'Time In AM', time_out_am: 'Time Out AM', time_in_pm: 'Time In PM', time_out_pm: 'Time Out PM' }[type] ?? type
}

function statusPillClass(status) {
  if (status === 'verified') return 'bg-emerald-100 text-emerald-700'
  if (status === 'manual_review') return 'bg-amber-100 text-amber-700'
  return 'bg-rose-100 text-rose-700'
}

function statusBadgeColor(status) {
  if (status === 'verified') return 'green'
  if (status === 'manual_review') return 'amber'
  return 'red'
}

function statusLabel(status) {
  if (status === 'verified') return 'Verified'
  if (status === 'manual_review') return 'Under Review'
  return 'Rejected'
}

function fmtTime(val) {
  if (!val) return '—'
  const d = new Date(String(val).replace(' ', 'T'))
  if (isNaN(d.getTime())) return '—'
  return d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })
}

function geofenceBadgeColor(status) {
  if (status === 'inside') return 'green'
  if (status === 'outside' || status === 'no_permission') return 'red'
  return 'slate'
}

function geofenceLabel(status, distanceMeters) {
  if (status === 'inside') return 'On campus'
  if (status === 'outside') return distanceMeters ? `Off campus (${Math.round(distanceMeters)}m)` : 'Off campus'
  if (status === 'no_permission') return 'No location'
  return 'Not configured'
}

function networkBadgeColor(status) {
  if (status === 'trusted') return 'green'
  return 'slate'
}

function networkLabel(status) {
  if (status === 'trusted') return 'Trusted'
  if (status === 'unknown') return 'Unrecognized'
  return 'Not configured'
}

onMounted(() => {
  load()
  if (canManageGeofence) {
    loadZones()
    loadNetworkRules()
  }
})

// ── Geofence Zones ───────────────────────────────────────────────────────────
const page = usePage()
const canManageGeofence = page.props.auth?.user?.permissions?.includes('hr.online-punch.manage-geofence')

const zones           = ref([])
const showZoneForm    = ref(false)
const editingZone     = ref(null)
const savingZone      = ref(false)
const zoneForm        = ref({ label: '', latitude: null, longitude: null, radius_meters: 250, is_active: true })

async function loadZones() {
  const { data } = await axios.get(route('hr.online-punch.geofence-zones.index'))
  zones.value = data
}

function openZoneForm(zone = null) {
  editingZone.value = zone
  zoneForm.value = zone
    ? { label: zone.label, latitude: Number(zone.latitude), longitude: Number(zone.longitude), radius_meters: zone.radius_meters, is_active: zone.is_active }
    : { label: '', latitude: null, longitude: null, radius_meters: 250, is_active: true }
  showZoneForm.value = true
}

async function saveZone() {
  savingZone.value = true
  try {
    if (editingZone.value) {
      await axios.put(route('hr.online-punch.geofence-zones.update', editingZone.value.id), zoneForm.value)
    } else {
      await axios.post(route('hr.online-punch.geofence-zones.store'), zoneForm.value)
    }
    showZoneForm.value = false
    await loadZones()
  } catch (err) {
    const msg = Object.values(err.response?.data?.errors ?? {})[0]?.[0] ?? 'Something went wrong.'
    Swal.fire('Error', msg, 'error')
  } finally {
    savingZone.value = false
  }
}

async function deleteZone(zone) {
  const result = await Swal.fire({
    title: `Delete "${zone.label}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Delete',
  })
  if (!result.isConfirmed) return

  await axios.delete(route('hr.online-punch.geofence-zones.destroy', zone.id))
  await loadZones()
}

// ── Trusted Networks ─────────────────────────────────────────────────────────
const networkRules  = ref([])
const showRuleForm  = ref(false)
const editingRule   = ref(null)
const savingRule    = ref(false)
const ruleForm      = ref({ label: '', cidr: '', is_active: true })

async function loadNetworkRules() {
  const { data } = await axios.get(route('hr.online-punch.network-rules.index'))
  networkRules.value = data
}

function openRuleForm(rule = null) {
  editingRule.value = rule
  ruleForm.value = rule
    ? { label: rule.label, cidr: rule.cidr, is_active: rule.is_active }
    : { label: '', cidr: '', is_active: true }
  showRuleForm.value = true
}

async function saveRule() {
  savingRule.value = true
  try {
    if (editingRule.value) {
      await axios.put(route('hr.online-punch.network-rules.update', editingRule.value.id), ruleForm.value)
    } else {
      await axios.post(route('hr.online-punch.network-rules.store'), ruleForm.value)
    }
    showRuleForm.value = false
    await loadNetworkRules()
  } catch (err) {
    const msg = Object.values(err.response?.data?.errors ?? {})[0]?.[0] ?? 'Something went wrong.'
    Swal.fire('Error', msg, 'error')
  } finally {
    savingRule.value = false
  }
}

async function deleteRule(rule) {
  const result = await Swal.fire({
    title: `Delete "${rule.label}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Delete',
  })
  if (!result.isConfirmed) return

  await axios.delete(route('hr.online-punch.network-rules.destroy', rule.id))
  await loadNetworkRules()
}
</script>
