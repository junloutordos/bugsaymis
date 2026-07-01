<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  Chart as ChartJS,
  Title, Tooltip, Legend,
  CategoryScale, LinearScale,
  PointElement, LineElement,
  Filler,
} from 'chart.js'
import { Line } from 'vue-chartjs'
import {
  ChevronDownIcon, ChevronUpIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

ChartJS.register(Title, Tooltip, Legend, CategoryScale, LinearScale, PointElement, LineElement, Filler)

const props = defineProps({
  devices: Array,
})

const PER_PAGE = 15
const currentPage = ref(1)
const sortKey = ref('risk_score')
const sortDir = ref('desc')
const expandedDeviceId = ref(null)
const historyByDevice = ref({})
const loadingHistory = ref(false)

const riskTierOrder = { critical: 4, high: 3, medium: 2, low: 1 }

function sortBy(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = 'desc'
  }
  currentPage.value = 1
}

const sorted = computed(() => {
  const list = [...props.devices]
  const dir = sortDir.value === 'asc' ? 1 : -1

  return list.sort((a, b) => {
    let av = a[sortKey.value]
    let bv = b[sortKey.value]

    if (sortKey.value === 'risk_tier') {
      av = riskTierOrder[a.risk_tier] ?? 0
      bv = riskTierOrder[b.risk_tier] ?? 0
    }

    if (av === null || av === undefined) return 1
    if (bv === null || bv === undefined) return -1
    if (av < bv) return -1 * dir
    if (av > bv) return 1 * dir
    return 0
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(sorted.value.length / PER_PAGE)))
const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return sorted.value.slice(start, start + PER_PAGE)
})

const riskTierClasses = {
  critical: 'bg-red-100 text-red-700',
  high: 'bg-orange-100 text-orange-700',
  medium: 'bg-amber-100 text-amber-700',
  low: 'bg-emerald-100 text-emerald-700',
}

function scoreBarColor(score) {
  if (score === null || score === undefined) return 'bg-slate-300'
  if (score >= 80) return 'bg-emerald-500'
  if (score >= 50) return 'bg-amber-500'
  return 'bg-red-500'
}

function warrantyBadge(equipment) {
  if (!equipment?.warranty_expires_at) return null
  const days = Math.ceil((new Date(equipment.warranty_expires_at) - new Date()) / 86400000)
  if (days < 0) return { text: 'Warranty expired', class: 'text-red-600' }
  if (days <= 90) return { text: `Warranty expires in ${days}d`, class: 'text-amber-600' }
  return null
}

function deviceAgeYears(equipment) {
  if (!equipment?.date_acquired) return null
  const years = (new Date() - new Date(equipment.date_acquired)) / (365.25 * 86400000)
  return years >= 5 ? Math.floor(years) : null
}

async function toggleExpand(device) {
  if (expandedDeviceId.value === device.id) {
    expandedDeviceId.value = null
    return
  }

  expandedDeviceId.value = device.id

  if (!historyByDevice.value[device.id]) {
    loadingHistory.value = true
    try {
      const { data } = await axios.get(route('atlas-sentinel.health-dashboard.history', device.id))
      historyByDevice.value[device.id] = data.history
    } finally {
      loadingHistory.value = false
    }
  }
}

function trendChartData(deviceId) {
  const history = historyByDevice.value[deviceId] || []
  return {
    labels: history.map(h => new Date(h.recorded_at).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })),
    datasets: [
      {
        label: 'Health Score',
        data: history.map(h => h.health_score),
        borderColor: '#10b981',
        backgroundColor: 'rgba(16, 185, 129, 0.1)',
        fill: true,
        tension: 0.3,
      },
      {
        label: 'Risk Score',
        data: history.map(h => h.risk_score),
        borderColor: '#ef4444',
        backgroundColor: 'rgba(239, 68, 68, 0.1)',
        fill: true,
        tension: 0.3,
      },
    ],
  }
}

const trendChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  scales: { y: { min: 0, max: 100 } },
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

// A failed self-update can leave the agent's Windows service down rather
// than just on an old version — that looks identical to "all is well" here
// unless we flag check-ins that never resumed afterward.
const STALE_CHECKIN_MINUTES = 40
function offlineAfterFailedUpdate(device) {
  if (!['failed', 'failed_service_down'].includes(device.last_update_result)) return false
  if (!device.last_checkin_at) return false
  const minutesSinceCheckin = (Date.now() - new Date(device.last_checkin_at).getTime()) / 60000
  return minutesSinceCheckin > STALE_CHECKIN_MINUTES
}
</script>

<template>
  <Head title="Atlas Sentinel Health Dashboard" />
  <AdminLayout title="Atlas Sentinel Health Dashboard">
    <div class="space-y-4">
      <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="w-8"></th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide cursor-pointer" @click="sortBy('hostname')">
                Device
              </th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                Owner / Room
              </th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide cursor-pointer" @click="sortBy('health_score')">
                Health
              </th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide cursor-pointer" @click="sortBy('risk_tier')">
                Risk
              </th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                Open Alerts
              </th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide cursor-pointer" @click="sortBy('last_checkin_at')">
                Last Check-in
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <template v-for="device in displayed" :key="device.id">
              <tr class="hover:bg-slate-50 cursor-pointer" @click="toggleExpand(device)">
                <td class="px-2 text-slate-400">
                  <ChevronUpIcon v-if="expandedDeviceId === device.id" class="w-4 h-4" />
                  <ChevronDownIcon v-else class="w-4 h-4" />
                </td>
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-700">{{ device.hostname || '—' }}</div>
                  <div class="text-xs text-slate-400">{{ device.equipment?.description }} ({{ device.equipment?.serial_no }})</div>
                  <div
                    v-if="offlineAfterFailedUpdate(device)"
                    class="mt-1 inline-flex items-center gap-1 text-red-600 text-xs font-medium"
                    :title="device.last_update_details || 'Update failed and no check-in since.'"
                  >
                    <ExclamationTriangleIcon class="w-3.5 h-3.5" /> Likely offline after failed update
                  </div>
                  <div v-else-if="device.last_update_result === 'failed' || device.last_update_result === 'failed_service_down'"
                    class="mt-1 text-xs text-amber-600"
                    :title="device.last_update_details || ''"
                  >
                    Last update failed ({{ formatDate(device.last_update_attempted_at) }})
                  </div>
                </td>
                <td class="px-4 py-3 text-slate-600">
                  <div>{{ device.equipment?.owner?.name || '—' }}</div>
                  <div class="text-xs text-slate-400">{{ device.equipment?.room?.name || '—' }}</div>
                  <div v-if="warrantyBadge(device.equipment)" :class="warrantyBadge(device.equipment).class" class="text-xs font-medium">
                    {{ warrantyBadge(device.equipment).text }}
                  </div>
                  <div v-if="deviceAgeYears(device.equipment)" class="text-xs text-slate-400">
                    {{ deviceAgeYears(device.equipment) }}y old
                  </div>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <div class="w-20 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                      <div :class="scoreBarColor(device.health_score)" class="h-full rounded-full" :style="{ width: (device.health_score ?? 0) + '%' }"></div>
                    </div>
                    <span class="text-xs text-slate-500">{{ device.health_score ?? '—' }}</span>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <span v-if="device.risk_tier" :class="riskTierClasses[device.risk_tier]" class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">
                    {{ device.risk_tier }}
                  </span>
                  <span v-else class="text-slate-400 text-xs">—</span>
                </td>
                <td class="px-4 py-3">
                  <span v-if="device.open_alerts_count > 0" class="inline-flex items-center gap-1 text-amber-600 text-xs font-medium">
                    <ExclamationTriangleIcon class="w-3.5 h-3.5" /> {{ device.open_alerts_count }}
                  </span>
                  <span v-else class="text-slate-400 text-xs">None</span>
                </td>
                <td class="px-4 py-3 text-slate-500 text-xs">{{ formatDate(device.last_checkin_at) }}</td>
              </tr>
              <tr v-if="expandedDeviceId === device.id">
                <td colspan="6" class="px-4 py-4 bg-slate-50">
                  <div v-if="loadingHistory" class="text-sm text-slate-400">Loading trend...</div>
                  <div v-else-if="(historyByDevice[device.id] || []).length === 0" class="text-sm text-slate-400">
                    No scored history yet for this device.
                  </div>
                  <div v-else class="h-56">
                    <Line :data="trendChartData(device.id)" :options="trendChartOptions" />
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>

        <div v-if="!devices.length" class="px-4 py-10 text-center text-slate-400 text-sm">
          No enrolled devices yet.
        </div>

                <PaginationControl
          :current-page="currentPage"
          :total-pages="totalPages"
          @prev="currentPage--"
          @next="currentPage++"
          @page="currentPage = $event"
        />
      </div>
    </div>
  </AdminLayout>
</template>
