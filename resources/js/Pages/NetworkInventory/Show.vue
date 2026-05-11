<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeftIcon, PencilSquareIcon, CheckIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  device:  Object,
  isAdmin: { type: Boolean, default: false },
})

const editing = ref(false)
const saving  = ref(false)
const form    = ref({
  label:       props.device.label       ?? '',
  device_type: props.device.device_type ?? 'unknown',
  notes:       props.device.notes       ?? '',
})

async function save() {
  saving.value = true
  try {
    await axios.patch(route('network.inventory.update', props.device.id), form.value)
    editing.value = false
    router.reload({ only: ['device'] })
    Swal.fire({ icon: 'success', title: 'Device updated.', timer: 1200, showConfirmButton: false })
  } catch {
    Swal.fire('Error', 'Could not update device.', 'error')
  } finally {
    saving.value = false
  }
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function sourceBadge(source) {
  return {
    snmp:         'bg-blue-100 text-blue-700',
    ruijie_cloud: 'bg-purple-100 text-purple-700',
    both:         'bg-indigo-100 text-indigo-700',
  }[source] ?? 'bg-slate-100 text-slate-500'
}

const deviceTypes = ['computer','phone','tablet','printer','ap','switch','camera','iot','unknown']
</script>

<template>
  <Head :title="`${device.label || device.hostname || device.ip_address} — Network`" />
  <AdminLayout :title="device.label || device.hostname || device.ip_address || device.mac_address">
    <div class="space-y-5 max-w-3xl">

      <!-- Back -->
      <button @click="router.visit(route('network.inventory.index'))"
        class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700">
        <ArrowLeftIcon class="h-4 w-4" /> Back to Inventory
      </button>

      <!-- Header card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
        <div class="flex items-start justify-between">
          <div class="flex items-center gap-3">
            <span :class="['h-3 w-3 rounded-full mt-1', device.is_online ? 'bg-emerald-500' : 'bg-slate-300']"></span>
            <div>
              <h1 class="text-lg font-bold text-slate-800">{{ device.label || device.hostname || device.ip_address }}</h1>
              <p class="text-sm text-slate-500 font-mono mt-0.5">{{ device.mac_address }}</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span :class="['px-2.5 py-1 rounded-full text-xs font-medium', sourceBadge(device.data_source)]">
              {{ { snmp: 'SNMP', ruijie_cloud: 'Ruijie Cloud', both: 'Both Sources' }[device.data_source] }}
            </span>
            <button v-if="isAdmin && !editing" @click="editing = true"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-600">
              <PencilSquareIcon class="h-3.5 w-3.5" /> Edit
            </button>
          </div>
        </div>
      </div>

      <!-- Edit form -->
      <div v-if="editing" class="bg-amber-50 border border-amber-200 rounded-xl p-5 space-y-4">
        <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide">Edit Device Info</p>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Label (friendly name)</label>
            <input v-model="form.label" type="text" placeholder="e.g. Library Printer 1"
              class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Device Type</label>
            <select v-model="form.device_type"
              class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
              <option v-for="t in deviceTypes" :key="t" :value="t">{{ t.charAt(0).toUpperCase() + t.slice(1) }}</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Notes</label>
          <textarea v-model="form.notes" rows="3" placeholder="Optional notes about this device…"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none" />
        </div>
        <div class="flex gap-3">
          <button @click="save" :disabled="saving"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg font-medium">
            <CheckIcon class="h-4 w-4" /> {{ saving ? 'Saving…' : 'Save Changes' }}
          </button>
          <button @click="editing = false" class="px-4 py-2 text-sm border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50">Cancel</button>
        </div>
      </div>

      <!-- Detail fields -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm divide-y divide-slate-100">
        <div v-for="[label, value] in [
          ['Label',           device.label || '—'],
          ['IP Address',      device.ip_address || '—'],
          ['MAC Address',     device.mac_address],
          ['Hostname',        device.hostname || '—'],
          ['Vendor',          device.vendor || '—'],
          ['Device Type',     device.device_type],
          ['Connection',      device.connection_type],
          ['VLAN',            device.vlan_name ? `${device.vlan_name} (${device.vlan_id})` : (device.vlan_id || '—')],
          ['Access Point',    device.ap_name || '—'],
          ['SSID',            device.ssid || '—'],
          ['Signal',          device.signal_dbm ? `${device.signal_dbm} dBm` : '—'],
          ['Status',          device.is_online ? 'Online' : 'Offline'],
          ['First Seen',      formatDate(device.first_seen_at)],
          ['Last Seen',       formatDate(device.last_seen_at)],
          ['Data Source',     device.data_source],
          ['Notes',           device.notes || '—'],
        ]" :key="label"
          class="flex items-start px-5 py-3">
          <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide w-36 shrink-0 pt-0.5">{{ label }}</span>
          <span class="text-sm text-slate-800 font-mono" :class="['ip_address','mac_address'].includes(label.toLowerCase().replace(' ','_')) ? 'font-mono' : ''">
            {{ value }}
          </span>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
