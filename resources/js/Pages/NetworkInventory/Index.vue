<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  ComputerDesktopIcon, WifiIcon, SignalIcon, ServerStackIcon,
  ArrowPathIcon, MagnifyingGlassIcon, FunnelIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  summary:       Object,
  vlans:         Array,
  aps:           Array,
  isAdmin:       { type: Boolean, default: false },
  lastSnmpScan:  { type: Object, default: null },
  lastCloudSync: { type: Object, default: null },
})

// ── Devices list ──────────────────────────────────────────────────────────────
const devices       = ref([])
const meta          = ref({})
const loading       = ref(false)
const currentPage   = ref(1)

const filters = ref({
  search:          '',
  status:          '',
  connection_type: '',
  vlan_id:         '',
  ap_name:         '',
  data_source:     '',
})

async function fetchDevices(page = 1) {
  loading.value = true
  try {
    const params = { page, ...Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v !== '')) }
    const { data } = await axios.get(route('network.devices'), { params })
    devices.value = data.data
    meta.value    = data.meta ?? data
  } finally {
    loading.value = false
  }
}

watch(filters, () => { currentPage.value = 1; fetchDevices(1) }, { deep: true })
onMounted(() => fetchDevices())

function clearFilters() {
  filters.value = { search: '', status: '', connection_type: '', vlan_id: '', ap_name: '', data_source: '' }
}

// ── Status badge helpers ───────────────────────────────────────────────────────
function onlineBadge(online) {
  return online
    ? 'bg-emerald-100 text-emerald-700'
    : 'bg-slate-100 text-slate-500'
}

function sourceBadge(source) {
  return {
    snmp:         'bg-blue-100 text-blue-700',
    ruijie_cloud: 'bg-purple-100 text-purple-700',
    both:         'bg-indigo-100 text-indigo-700',
  }[source] ?? 'bg-slate-100 text-slate-500'
}

function sourceLabel(source) {
  return { snmp: 'SNMP', ruijie_cloud: 'Cloud', both: 'Both' }[source] ?? source
}

function typeIcon(type) {
  return { computer: '💻', phone: '📱', tablet: '📟', printer: '🖨️', ap: '📡', switch: '🔀', camera: '📷', iot: '🔌' }[type] ?? '❓'
}

function connectionIcon(type) {
  return type === 'wireless' ? '📶' : type === 'wired' ? '🔗' : '❓'
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function syncAgo(d) {
  if (!d) return 'Never'
  const diff = Math.floor((Date.now() - new Date(d)) / 1000)
  if (diff < 60)   return `${diff}s ago`
  if (diff < 3600) return `${Math.floor(diff/60)}m ago`
  return `${Math.floor(diff/3600)}h ago`
}
</script>

<template>
  <Head title="Network Inventory" />
  <AdminLayout title="Network Inventory">
    <div class="space-y-5">

      <!-- Sync status bar -->
      <div class="flex flex-wrap gap-3 text-xs">
        <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2">
          <ServerStackIcon class="h-3.5 w-3.5 text-blue-500" />
          <span class="text-slate-500">SNMP Agent:</span>
          <span :class="lastSnmpScan?.status === 'success' ? 'text-emerald-600 font-medium' : 'text-amber-600'">
            {{ lastSnmpScan ? syncAgo(lastSnmpScan.scanned_at) : 'No data yet' }}
          </span>
          <span v-if="lastSnmpScan" class="text-slate-400">· {{ lastSnmpScan.devices_online }} online</span>
        </div>
        <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2">
          <WifiIcon class="h-3.5 w-3.5 text-purple-500" />
          <span class="text-slate-500">Ruijie Cloud:</span>
          <span :class="lastCloudSync?.status === 'success' ? 'text-emerald-600 font-medium' : 'text-amber-600'">
            {{ lastCloudSync ? syncAgo(lastCloudSync.scanned_at) : 'Not configured' }}
          </span>
          <span v-if="lastCloudSync" class="text-slate-400">· {{ lastCloudSync.devices_online }} online</span>
        </div>
      </div>

      <!-- Summary cards -->
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <div v-for="(val, key) in {
          'Total Devices': summary.total,
          'Online Now':    summary.online,
          'Wired':         summary.wired,
          'Wireless':      summary.wireless,
          'New Today':     summary.new_today,
        }" :key="key"
          class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3 text-center">
          <div class="text-2xl font-bold text-slate-800">{{ val }}</div>
          <div class="text-xs text-slate-500 mt-0.5">{{ key }}</div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <div class="flex flex-wrap gap-3 items-end">
          <div class="flex-1 min-w-48">
            <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
            <div class="relative">
              <MagnifyingGlassIcon class="absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
              <input v-model="filters.search" type="text" placeholder="IP, MAC, hostname, label…"
                class="w-full rounded-lg border border-slate-200 pl-8 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
            <select v-model="filters.status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
              <option value="">All</option>
              <option value="online">Online</option>
              <option value="offline">Offline</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Connection</label>
            <select v-model="filters.connection_type" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
              <option value="">All</option>
              <option value="wired">Wired</option>
              <option value="wireless">Wireless</option>
            </select>
          </div>

          <div v-if="vlans.length">
            <label class="block text-xs font-medium text-slate-500 mb-1">VLAN</label>
            <select v-model="filters.vlan_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
              <option value="">All VLANs</option>
              <option v-for="v in vlans" :key="v.vlan_id" :value="v.vlan_id">
                {{ v.vlan_name || 'VLAN ' + v.vlan_id }} ({{ v.count }})
              </option>
            </select>
          </div>

          <div v-if="aps.length">
            <label class="block text-xs font-medium text-slate-500 mb-1">Access Point</label>
            <select v-model="filters.ap_name" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
              <option value="">All APs</option>
              <option v-for="a in aps" :key="a.ap_name" :value="a.ap_name">
                {{ a.ap_name }} ({{ a.count }})
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Source</label>
            <select v-model="filters.data_source" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
              <option value="">All Sources</option>
              <option value="snmp">SNMP only</option>
              <option value="ruijie_cloud">Cloud only</option>
              <option value="both">Both</option>
            </select>
          </div>

          <button @click="clearFilters" class="px-3 py-2 text-sm text-slate-500 border border-slate-200 rounded-lg hover:bg-slate-50">
            Clear
          </button>
        </div>
      </div>

      <!-- Device table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
            {{ meta.total ?? devices.length }} Device(s)
          </p>
          <ArrowPathIcon v-if="loading" class="h-4 w-4 text-slate-400 animate-spin" />
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Device</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">IP</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">MAC</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Vendor</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">VLAN</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">AP / SSID</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Last Seen</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Source</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!devices.length && !loading">
                <td colspan="9" class="px-4 py-12 text-center text-slate-400 text-sm">
                  No devices found. Waiting for the first scan report from the campus agent.
                </td>
              </tr>
              <tr v-for="d in devices" :key="d.id"
                class="hover:bg-slate-50/50 cursor-pointer"
                @click="router.visit(route('network.inventory.show', d.id))">
                <td class="px-4 py-2.5">
                  <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium', onlineBadge(d.is_online)]">
                    <span :class="['h-1.5 w-1.5 rounded-full', d.is_online ? 'bg-emerald-500' : 'bg-slate-400']"></span>
                    {{ d.is_online ? 'Online' : 'Offline' }}
                  </span>
                </td>
                <td class="px-4 py-2.5">
                  <div class="flex items-center gap-2">
                    <span class="text-base">{{ typeIcon(d.device_type) }}</span>
                    <div>
                      <p class="font-medium text-slate-800">{{ d.label || d.hostname || '—' }}</p>
                      <p v-if="d.label && d.hostname" class="text-xs text-slate-400">{{ d.hostname }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-2.5 font-mono text-xs text-slate-700">{{ d.ip_address || '—' }}</td>
                <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ d.mac_address }}</td>
                <td class="px-4 py-2.5 text-xs text-slate-600">{{ d.vendor || '—' }}</td>
                <td class="px-4 py-2.5 text-xs">
                  <span v-if="d.vlan_id" class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-mono">
                    {{ d.vlan_name || 'VLAN ' + d.vlan_id }}
                  </span>
                  <span v-else class="text-slate-300">—</span>
                </td>
                <td class="px-4 py-2.5 text-xs text-slate-600">
                  <span v-if="d.ap_name">{{ d.ap_name }}<br><span class="text-slate-400">{{ d.ssid }}</span></span>
                  <span v-else class="text-slate-300">—</span>
                </td>
                <td class="px-4 py-2.5 text-xs text-slate-500 whitespace-nowrap">{{ formatDate(d.last_seen_at) }}</td>
                <td class="px-4 py-2.5">
                  <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', sourceBadge(d.data_source)]">
                    {{ sourceLabel(d.data_source) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="flex items-center justify-between px-5 py-3 border-t border-slate-100">
          <p class="text-xs text-slate-500">Page {{ meta.current_page }} of {{ meta.last_page }}</p>
          <div class="flex gap-2">
            <button :disabled="meta.current_page === 1"
              @click="fetchDevices(meta.current_page - 1)"
              class="px-3 py-1.5 text-xs border border-slate-200 rounded hover:bg-slate-50 disabled:opacity-40">Prev</button>
            <button :disabled="meta.current_page === meta.last_page"
              @click="fetchDevices(meta.current_page + 1)"
              class="px-3 py-1.5 text-xs border border-slate-200 rounded hover:bg-slate-50 disabled:opacity-40">Next</button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
