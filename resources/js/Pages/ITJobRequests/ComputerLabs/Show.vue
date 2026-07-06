<script setup>
import { ref, computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppButton from '@/Components/AppButton.vue'
import AgentSpecsModal from '@/Components/AgentSpecsModal.vue'
import { ComputerDesktopIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  room: Object,
  equipments: Array,
  rows: Number,
  cols: Number,
  latestAgentVersion: { type: String, default: null },
})

const equipmentList = ref(props.equipments.map(eq => ({ ...eq })))

const page = usePage()
const canManage = computed(() =>
  (page.props.auth?.user?.permissions ?? []).includes('it.equipment.manage')
)

// A device that hasn't checked in within this window is treated as offline
// even if its last known health/risk scores looked fine.
const OFFLINE_THRESHOLD_MINUTES = 40

function tileStatus(eq) {
  if (!eq.agent_device) return 'unenrolled'

  const lastCheckin = eq.agent_device.last_checkin_at
  const minutesSinceCheckin = lastCheckin ? (Date.now() - new Date(lastCheckin).getTime()) / 60000 : Infinity
  if (minutesSinceCheckin > OFFLINE_THRESHOLD_MINUTES) return 'offline'

  if (['critical', 'high'].includes(eq.agent_device.risk_tier)) return 'critical'
  if (eq.agent_device.risk_tier === 'medium') return 'warning'
  return 'healthy'
}

const statusClasses = {
  healthy: 'bg-emerald-50 border-emerald-300 text-emerald-700',
  warning: 'bg-amber-50 border-amber-300 text-amber-700',
  critical: 'bg-red-50 border-red-300 text-red-700',
  offline: 'bg-slate-100 border-slate-300 text-slate-500',
  unenrolled: 'bg-slate-50 border-slate-200 text-slate-400',
}

const statusLabels = {
  healthy: 'Healthy',
  warning: 'Needs attention',
  critical: 'Critical risk',
  offline: 'Offline',
  unenrolled: 'Agent not installed',
}

const cellMap = computed(() => {
  const map = {}
  equipmentList.value.forEach(eq => {
    if (eq.lab_seat_row && eq.lab_seat_col) map[`${eq.lab_seat_row}-${eq.lab_seat_col}`] = eq
  })
  return map
})

const unassigned = computed(() =>
  equipmentList.value.filter(eq =>
    !eq.lab_seat_row || !eq.lab_seat_col || eq.lab_seat_row > props.rows || eq.lab_seat_col > props.cols
  )
)

const draggingId = ref(null)

function onDragStart(eq) {
  if (!canManage.value) return
  draggingId.value = eq.id
}

function onDrop(row, col) {
  if (!canManage.value || draggingId.value === null) return
  if (cellMap.value[`${row}-${col}`]) return
  saveSeat(draggingId.value, row, col)
  draggingId.value = null
}

function onDropTray() {
  if (!canManage.value || draggingId.value === null) return
  saveSeat(draggingId.value, null, null)
  draggingId.value = null
}

async function saveSeat(equipmentId, row, col) {
  try {
    const { data } = await axios.patch(route('computer-labs.update-seat', equipmentId), {
      lab_seat_row: row,
      lab_seat_col: col,
    })
    const idx = equipmentList.value.findIndex(eq => eq.id === equipmentId)
    if (idx !== -1) {
      equipmentList.value[idx] = { ...equipmentList.value[idx], ...data.equipment }
    }
  } catch (e) {
    Swal.fire('Cannot place there', e.response?.data?.message ?? 'Something went wrong.', 'error')
  }
}

const showSpecsModal = ref(false)
const selectedEquipment = ref(null)

function openSpecs(eq) {
  if (!eq.agent_device) return
  selectedEquipment.value = eq
  showSpecsModal.value = true
}
</script>

<template>
  <Head :title="`${room.name} — Computer Laboratory`" />
  <AdminLayout :title="room.name">
    <div class="space-y-4">
      <AppButton as="link" variant="ghost" size="sm" :href="route('computer-labs.index')">
        <ArrowLeftIcon class="w-4 h-4" /> Back to Computer Laboratories
      </AppButton>

      <div class="flex flex-wrap items-center gap-3 text-xs">
        <span v-for="(label, key) in statusLabels" :key="key" class="inline-flex items-center gap-1.5">
          <span :class="statusClasses[key]" class="w-3 h-3 rounded-full border"></span>
          {{ label }}
        </span>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
        <div class="lg:col-span-3 bg-white rounded-xl shadow-sm border border-slate-200 p-5">
          <div
            v-for="r in rows"
            :key="r"
            class="grid gap-3 mb-3"
            :style="{ gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))` }"
          >
            <div
              v-for="c in cols"
              :key="c"
              class="aspect-square rounded-lg border-2 flex flex-col items-center justify-center cursor-pointer transition-colors"
              :class="cellMap[`${r}-${c}`] ? statusClasses[tileStatus(cellMap[`${r}-${c}`])] : 'border-dashed border-slate-200 text-slate-300'"
              @click="cellMap[`${r}-${c}`] && openSpecs(cellMap[`${r}-${c}`])"
              @dragover.prevent
              @drop="onDrop(r, c)"
            >
              <template v-if="cellMap[`${r}-${c}`]">
                <div
                  :draggable="canManage"
                  @dragstart="onDragStart(cellMap[`${r}-${c}`])"
                  class="flex flex-col items-center"
                >
                  <ComputerDesktopIcon class="w-6 h-6" />
                  <div class="text-[10px] font-medium mt-1 truncate max-w-full px-1">{{ cellMap[`${r}-${c}`].property_no || cellMap[`${r}-${c}`].serial_no }}</div>
                </div>
              </template>
              <span v-else class="text-[10px]">R{{ r }}C{{ c }}</span>
            </div>
          </div>
        </div>

        <div
          class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 lg:sticky lg:top-4 self-start"
          @dragover.prevent
          @drop="onDropTray"
        >
          <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
            Unassigned ({{ unassigned.length }})
          </div>
          <p v-if="canManage" class="text-[11px] text-slate-400 mb-3">Drag a unit onto the grid to place it, or drag a placed unit back here to unassign it.</p>
          <div class="space-y-2 max-h-[28rem] overflow-y-auto">
            <div
              v-for="eq in unassigned"
              :key="eq.id"
              :draggable="canManage"
              @dragstart="onDragStart(eq)"
              @click="openSpecs(eq)"
              class="flex items-center gap-2 px-2 py-1.5 rounded-lg border text-xs cursor-pointer"
              :class="statusClasses[tileStatus(eq)]"
            >
              <ComputerDesktopIcon class="w-4 h-4 shrink-0" />
              <span class="truncate">{{ eq.property_no || eq.serial_no || eq.description }}</span>
            </div>
            <div v-if="!unassigned.length" class="text-xs text-slate-400 text-center py-4">All units are placed.</div>
          </div>
        </div>
      </div>
    </div>

    <AgentSpecsModal
      v-if="showSpecsModal"
      :equipment="selectedEquipment"
      :latestAgentVersion="latestAgentVersion"
      @close="showSpecsModal = false"
    />
  </AdminLayout>
</template>
