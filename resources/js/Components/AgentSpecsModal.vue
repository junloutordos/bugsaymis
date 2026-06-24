<script setup>
import { ref, computed } from 'vue'
import {
  CpuChipIcon,
  Square3Stack3DIcon,
  CircleStackIcon,
  WifiIcon,
  FireIcon,
  BoltIcon,
  ShieldCheckIcon,
  ServerStackIcon,
  CheckBadgeIcon,
  ArchiveBoxIcon,
  MagnifyingGlassIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  equipment: Object,
})

defineEmits(['close'])

const softwareSearch = ref('')

const filteredSoftware = computed(() => {
  const list = props.equipment?.agent_device?.software_inventory?.installed_software ?? []
  const term = softwareSearch.value.trim().toLowerCase()
  if (!term) return list
  return list.filter(sw =>
    (sw.name || '').toLowerCase().includes(term) || (sw.publisher || '').toLowerCase().includes(term)
  )
})

const RAM_LOW_THRESHOLD = 10
const DISK_LOW_THRESHOLD = 15
const HIGH_CPU_USAGE_THRESHOLD = 85

function percentFree(free, total) {
  if (!total) return null
  return Math.round((free / total) * 1000) / 10
}

function percentUsed(free, total) {
  const freePct = percentFree(free, total)
  return freePct === null ? 0 : 100 - freePct
}

function freeBarColor(percent, threshold) {
  if (percent === null) return 'bg-slate-300'
  if (percent < threshold) return 'bg-red-500'
  if (percent < threshold * 2) return 'bg-amber-500'
  return 'bg-emerald-500'
}

function usageBarColor(percent, threshold) {
  if (percent === null || percent === undefined) return 'bg-slate-300'
  if (percent > threshold) return 'bg-red-500'
  if (percent > threshold * 0.7) return 'bg-amber-500'
  return 'bg-emerald-500'
}

function formatDateTime(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleString('en-PH', {
    year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit',
  })
}

const riskTierClasses = {
  critical: 'bg-red-100 text-red-700',
  high: 'bg-orange-100 text-orange-700',
  medium: 'bg-amber-100 text-amber-700',
  low: 'bg-emerald-100 text-emerald-700',
}

function batteryWearPct(battery) {
  if (!battery?.design_capacity_mwh || !battery?.full_charge_capacity_mwh) return null
  return Math.round((1 - battery.full_charge_capacity_mwh / battery.design_capacity_mwh) * 1000) / 10
}

function securityRowClass(value) {
  return value === false ? 'text-red-600' : value === true ? 'text-emerald-600' : 'text-slate-400'
}
</script>

<template>
  <div class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white w-full max-w-3xl rounded-2xl shadow-xl max-h-[90vh] overflow-y-auto">
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-semibold text-slate-800">
          Agent Specs for {{ equipment?.description }} / {{ equipment?.serial_no }}
        </h2>
        <button
          @click="$emit('close')"
          class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
        </button>
      </div>

      <div class="px-6 py-5">
        <div v-if="!equipment?.agent_device?.health_snapshot" class="py-16 text-center text-slate-400 text-sm">
          No check-in data yet — the agent reports every 20 minutes, so this fills in shortly after install.
        </div>

        <div v-else class="space-y-4">
          <!-- Header band -->
          <div class="rounded-xl bg-gradient-to-r from-indigo-50 to-slate-50 border border-indigo-100 px-4 py-3">
            <div class="flex items-center justify-between">
              <div class="text-sm font-semibold text-slate-800">{{ equipment.agent_device.hostname }}</div>
              <div class="flex items-center gap-1.5">
                <span
                  v-if="equipment.agent_device.risk_tier"
                  :class="riskTierClasses[equipment.agent_device.risk_tier]"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium capitalize"
                >{{ equipment.agent_device.risk_tier }} risk</span>
                <span
                  v-if="equipment.agent_device.network_location === 'on_campus'"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700"
                >On Campus</span>
                <span
                  v-else-if="equipment.agent_device.network_location === 'off_campus'"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700"
                  :title="`Since ${formatDateTime(equipment.agent_device.network_location_changed_at)}`"
                >Off Campus</span>
              </div>
            </div>
            <div class="text-xs text-slate-500 mt-0.5">
              {{ equipment.agent_device.os_version }}
              &middot; Agent v{{ equipment.agent_device.agent_version }}
            </div>
            <div class="text-xs text-indigo-600 mt-1">
              Last reported {{ formatDateTime(equipment.agent_device.health_snapshot.recorded_at) }}
            </div>
          </div>

          <!-- CPU -->
          <div class="border border-slate-100 rounded-lg p-3 flex items-start gap-3">
            <CpuChipIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
            <div class="flex-1">
              <div class="flex items-center justify-between">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">CPU</div>
                <div v-if="equipment.agent_device.health_snapshot.payload?.cpu_temp_c" class="text-xs text-slate-500 flex items-center gap-1">
                  <FireIcon class="w-3.5 h-3.5 text-slate-400" />
                  {{ equipment.agent_device.health_snapshot.payload.cpu_temp_c }}&deg;C
                </div>
              </div>
              <div class="mt-0.5 text-sm text-slate-700">{{ equipment.agent_device.health_snapshot.payload?.cpu ?? '—' }}</div>
              <template v-if="equipment.agent_device.health_snapshot.payload?.cpu_usage_pct !== undefined && equipment.agent_device.health_snapshot.payload?.cpu_usage_pct !== null">
                <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all"
                    :class="usageBarColor(equipment.agent_device.health_snapshot.payload.cpu_usage_pct, HIGH_CPU_USAGE_THRESHOLD)"
                    :style="{ width: equipment.agent_device.health_snapshot.payload.cpu_usage_pct + '%' }"
                  ></div>
                </div>
                <div class="mt-1 text-xs text-slate-500">{{ equipment.agent_device.health_snapshot.payload.cpu_usage_pct }}% usage</div>
              </template>
            </div>
          </div>

          <!-- Network -->
          <div
            v-if="equipment.agent_device.health_snapshot.payload?.network"
            class="border border-slate-100 rounded-lg p-3 flex items-start gap-3"
          >
            <WifiIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
            <div class="flex-1">
              <div class="flex items-center justify-between">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Network</div>
                <span v-if="equipment.agent_device.health_snapshot.payload.network.link_up === false" class="text-[11px] font-medium text-red-600">Link Down</span>
              </div>
              <div class="mt-0.5 text-sm text-slate-700 space-x-3">
                <span v-if="equipment.agent_device.health_snapshot.payload.network.gateway_latency_ms !== null">
                  {{ equipment.agent_device.health_snapshot.payload.network.gateway_latency_ms }}ms latency
                </span>
                <span v-if="equipment.agent_device.health_snapshot.payload.network.packet_loss_pct" :class="equipment.agent_device.health_snapshot.payload.network.packet_loss_pct > 20 ? 'text-red-600' : ''">
                  {{ equipment.agent_device.health_snapshot.payload.network.packet_loss_pct }}% loss
                </span>
                <span v-if="equipment.agent_device.health_snapshot.payload.network.link_speed_mbps">
                  {{ equipment.agent_device.health_snapshot.payload.network.link_speed_mbps }} Mbps
                </span>
              </div>
              <div class="mt-1 text-xs text-slate-500">
                <span v-if="equipment.agent_device.health_snapshot.payload?.wifi_ssid">
                  Wi-Fi: {{ equipment.agent_device.health_snapshot.payload.wifi_ssid }}
                  <span v-if="equipment.agent_device.health_snapshot.payload?.wifi_bssid">&middot; AP {{ equipment.agent_device.health_snapshot.payload.wifi_bssid }}</span>
                </span>
                <span v-else-if="equipment.agent_device.health_snapshot.payload.network.link_up">Wired connection</span>
              </div>
              <div v-if="equipment.agent_device.health_snapshot.payload.network.local_ip" class="text-xs text-slate-500">
                Local IP: {{ equipment.agent_device.health_snapshot.payload.network.local_ip }}
              </div>
            </div>
          </div>

          <!-- Watched services -->
          <div
            v-if="equipment.agent_device.health_snapshot.payload?.services?.length"
            class="border border-slate-100 rounded-lg p-3 flex items-start gap-3"
          >
            <ServerStackIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
            <div class="flex-1">
              <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Services</div>
              <div class="flex flex-wrap gap-1.5 items-center">
                <span
                  v-for="svc in equipment.agent_device.health_snapshot.payload.services"
                  :key="svc.name"
                  :class="svc.status === 'Running' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium"
                >{{ svc.name }}: {{ svc.status }}</span>
              </div>
            </div>
          </div>

          <!-- Battery -->
          <div
            v-if="equipment.agent_device.hardware_inventory?.battery"
            class="border border-slate-100 rounded-lg p-3 flex items-start gap-3"
          >
            <BoltIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
            <div class="flex-1">
              <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Battery</div>
              <template v-if="batteryWearPct(equipment.agent_device.hardware_inventory.battery) !== null">
                <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all"
                    :class="usageBarColor(batteryWearPct(equipment.agent_device.hardware_inventory.battery), 20)"
                    :style="{ width: batteryWearPct(equipment.agent_device.hardware_inventory.battery) + '%' }"
                  ></div>
                </div>
                <div class="mt-1 text-xs text-slate-500">{{ batteryWearPct(equipment.agent_device.hardware_inventory.battery) }}% worn from design capacity</div>
              </template>
              <div v-else class="mt-0.5 text-sm text-slate-400">—</div>
            </div>
          </div>

          <!-- Security posture -->
          <div
            v-if="equipment.agent_device.security_status"
            class="border border-slate-100 rounded-lg p-3 flex items-start gap-3"
          >
            <ShieldCheckIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
            <div class="flex-1">
              <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Security</div>
              <div class="grid grid-cols-2 gap-1 text-xs">
                <div :class="securityRowClass(equipment.agent_device.security_status.antivirus_enabled)">
                  Antivirus: {{ equipment.agent_device.security_status.antivirus_enabled === false ? 'Disabled' : equipment.agent_device.security_status.antivirus_enabled === true ? 'Enabled' : '—' }}
                </div>
                <div :class="securityRowClass(equipment.agent_device.security_status.firewall_enabled)">
                  Firewall: {{ equipment.agent_device.security_status.firewall_enabled === false ? 'Disabled' : equipment.agent_device.security_status.firewall_enabled === true ? 'Enabled' : '—' }}
                </div>
                <div class="text-slate-500">
                  Pending updates: {{ equipment.agent_device.security_status.pending_updates_count ?? '—' }}
                </div>
                <div :class="equipment.agent_device.security_status.unauthorized_software_count > 0 ? 'text-amber-600' : 'text-slate-500'">
                  Unauthorized software: {{ equipment.agent_device.security_status.unauthorized_software_count ?? 0 }}
                </div>
              </div>
              <div v-if="equipment.agent_device.security_status.reboot_required" class="mt-1 text-xs text-amber-600">Reboot required</div>
            </div>
          </div>

          <!-- RAM -->
          <div
            v-if="equipment.agent_device.health_snapshot.payload?.ram_total_mb"
            class="border border-slate-100 rounded-lg p-3 flex items-start gap-3"
          >
            <Square3Stack3DIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
            <div class="flex-1">
              <div class="flex items-center justify-between">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">RAM</div>
                <div class="text-xs text-slate-500">
                  {{ Math.round(equipment.agent_device.health_snapshot.payload.ram_free_mb / 1024) }} GB free of
                  {{ Math.round(equipment.agent_device.health_snapshot.payload.ram_total_mb / 1024) }} GB
                </div>
              </div>
              <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                <div
                  class="h-full rounded-full transition-all"
                  :class="freeBarColor(percentFree(equipment.agent_device.health_snapshot.payload.ram_free_mb, equipment.agent_device.health_snapshot.payload.ram_total_mb), RAM_LOW_THRESHOLD)"
                  :style="{ width: percentUsed(equipment.agent_device.health_snapshot.payload.ram_free_mb, equipment.agent_device.health_snapshot.payload.ram_total_mb) + '%' }"
                ></div>
              </div>
            </div>
          </div>

          <!-- Disks -->
          <div class="border border-slate-100 rounded-lg p-3 flex items-start gap-3">
            <CircleStackIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
            <div class="flex-1">
              <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Disks</div>
              <div v-if="!equipment.agent_device.health_snapshot.payload?.disks?.length" class="text-sm text-slate-400">—</div>
              <div v-else class="space-y-2.5">
                <div v-for="disk in equipment.agent_device.health_snapshot.payload.disks" :key="disk.drive">
                  <div class="flex items-center justify-between text-sm">
                    <span class="font-medium text-slate-700">{{ disk.drive }}</span>
                    <span class="text-slate-500 text-xs flex items-center gap-1">
                      {{ disk.free_gb }} GB free of {{ disk.total_gb }} GB
                      <ExclamationTriangleIcon
                        v-if="percentFree(disk.free_gb, disk.total_gb) < DISK_LOW_THRESHOLD"
                        class="w-3.5 h-3.5 text-red-500"
                        title="Low disk space"
                      />
                    </span>
                  </div>
                  <div class="mt-1 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                    <div
                      class="h-full rounded-full transition-all"
                      :class="freeBarColor(percentFree(disk.free_gb, disk.total_gb), DISK_LOW_THRESHOLD)"
                      :style="{ width: percentUsed(disk.free_gb, disk.total_gb) + '%' }"
                    ></div>
                  </div>
                </div>
              </div>

              <div v-if="equipment.agent_device.hardware_inventory?.disks?.length" class="mt-3 pt-2 border-t border-slate-100 space-y-1">
                <div
                  v-for="pdisk in equipment.agent_device.hardware_inventory.disks"
                  :key="pdisk.drive"
                  class="flex items-center justify-between text-xs"
                >
                  <span class="text-slate-500">{{ pdisk.model || pdisk.drive }}</span>
                  <span
                    :class="pdisk.smart_status === 'failing' ? 'bg-red-50 text-red-700' : pdisk.smart_status === 'ok' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                    class="px-1.5 py-0.5 rounded-full font-medium"
                  >SMART: {{ pdisk.smart_status ?? 'unknown' }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Printers -->
          <div
            v-if="equipment.agent_device.health_snapshot.payload?.printers?.length"
            class="border border-slate-100 rounded-lg p-3 flex items-start gap-3"
          >
            <ServerStackIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
            <div class="flex-1">
              <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Printers</div>
              <div class="space-y-2">
                <div
                  v-for="printer in equipment.agent_device.health_snapshot.payload.printers"
                  :key="printer.name"
                  class="flex items-center justify-between text-sm"
                >
                  <span class="flex items-center gap-1.5">
                    <span class="text-slate-700">{{ printer.name }}</span>
                    <CheckBadgeIcon v-if="printer.is_default" class="w-4 h-4 text-indigo-500" title="Default printer" />
                  </span>
                  <span class="flex items-center gap-2 text-xs">
                    <span v-if="printer.detected_error_state" class="px-1.5 py-0.5 rounded-full font-medium bg-amber-50 text-amber-700">{{ printer.detected_error_state }}</span>
                    <span v-if="printer.pending_jobs > 0" class="text-slate-500">{{ printer.pending_jobs }} job(s) pending</span>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Installed Software — read-only -->
          <div
            v-if="equipment.agent_device.software_inventory?.installed_software?.length"
            class="border border-slate-100 rounded-lg p-3 flex items-start gap-3"
          >
            <ArchiveBoxIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
            <div class="flex-1">
              <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                Installed Software ({{ equipment.agent_device.software_inventory.installed_software.length }})
              </div>
              <div class="relative mb-2">
                <MagnifyingGlassIcon class="w-3.5 h-3.5 text-slate-400 absolute left-2 top-1/2 -translate-y-1/2" />
                <input
                  v-model="softwareSearch"
                  type="text"
                  placeholder="Search software or publisher…"
                  class="w-full text-xs pl-7 pr-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>
              <div class="max-h-56 overflow-y-auto space-y-1">
                <div
                  v-for="sw in filteredSoftware"
                  :key="sw.uninstall_key ?? sw.name"
                  class="text-xs py-1 border-b border-slate-50 last:border-b-0"
                >
                  <div class="text-slate-700 truncate">{{ sw.name }}</div>
                  <div class="text-[11px] text-slate-400 truncate">{{ sw.publisher || '—' }} &middot; {{ sw.version || '—' }}</div>
                </div>
                <div v-if="!filteredSoftware.length" class="text-center text-slate-400 py-2">No matches.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
