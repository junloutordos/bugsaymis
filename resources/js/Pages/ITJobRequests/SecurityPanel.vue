<script setup>
import { ref, onMounted, watch } from "vue"
import axios from "axios"
import Swal from "sweetalert2"
import { ShieldExclamationIcon, ShieldCheckIcon } from "@heroicons/vue/24/outline"
import AppBadge from "@/Components/AppBadge.vue"
import AppButton from "@/Components/AppButton.vue"

const props = defineProps({ equipmentId: { type: Number, required: true } })

const loading = ref(true)
const status = ref('none')
const exempt = ref(false)
const incidents = ref([])

async function load() {
  loading.value = true
  const { data } = await axios.get(`/ict-equipments/${props.equipmentId}/security`)
  status.value = data.status
  exempt.value = data.exempt
  incidents.value = data.incidents
  loading.value = false
}

async function isolateNow() {
  const confirmResult = await Swal.fire({
    title: 'Isolate this device?',
    text: 'It will be blocked from the network except for reporting back to this server.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Isolate',
  })
  if (!confirmResult.isConfirmed) return
  await axios.post(`/ict-equipments/${props.equipmentId}/remediate`, { action: 'network_containment' })
  await load()
}

async function releaseNow() {
  await axios.post(`/ict-equipments/${props.equipmentId}/remediate`, { action: 'network_release' })
  await load()
}

async function confirmIncident(incidentId) {
  await axios.post(`/ict-equipments/${props.equipmentId}/security-incidents/${incidentId}/confirm`)
  await load()
}

async function toggleExempt() {
  await axios.patch(`/ict-equipments/${props.equipmentId}/security-exempt`, { exempt: !exempt.value })
  await load()
}

onMounted(load)
watch(() => props.equipmentId, load)
</script>

<template>
  <div class="border border-slate-100 rounded-lg p-3">
    <div class="flex items-start gap-3">
      <ShieldExclamationIcon v-if="status === 'contained'" class="w-5 h-5 text-danger-500 shrink-0 mt-0.5" />
      <ShieldCheckIcon v-else class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
      <div class="flex-1">
        <div class="flex items-center justify-between">
          <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Threat Containment</div>
          <AppBadge :color="status === 'contained' ? 'red' : 'green'">
            {{ status === 'contained' ? 'Contained' : 'Healthy' }}
          </AppBadge>
        </div>

        <div class="mt-2 flex flex-wrap gap-2">
          <AppButton v-if="status !== 'contained'" variant="danger" size="sm" @click="isolateNow">
            Isolate Now
          </AppButton>
          <AppButton v-else size="sm" @click="releaseNow">
            Release
          </AppButton>
          <AppButton variant="secondary" size="sm" @click="toggleExempt">
            {{ exempt ? 'Remove Exemption' : 'Exempt from Auto-Containment' }}
          </AppButton>
        </div>

        <div v-if="!loading" class="mt-3 space-y-1.5">
          <div v-for="incident in incidents" :key="incident.id" class="text-xs border-t border-slate-100 pt-1.5 flex items-center justify-between">
            <span class="text-slate-600">
              {{ incident.reason }} &middot; {{ new Date(incident.triggered_at).toLocaleString('en-PH') }}
            </span>
            <button
              v-if="!incident.confirmed_at && !incident.released_at"
              @click="confirmIncident(incident.id)"
              class="text-indigo-600 font-medium hover:text-indigo-800"
            >Confirm</button>
          </div>
          <p v-if="incidents.length === 0" class="text-xs text-slate-400">No incidents recorded.</p>
        </div>
      </div>
    </div>
  </div>
</template>
