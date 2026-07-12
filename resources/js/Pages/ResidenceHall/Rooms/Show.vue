<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppModal from '@/Components/AppModal.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
import { ArrowLeftIcon, UserIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  room:           Object,
  dormers:        Array,
  unassignedList: Array,
  myHall:         String,
})

// Build bed slot map: bed_number → dormer (or null)
const bedMap = computed(() => {
  const map = {}
  props.dormers.forEach(d => {
    if (d.bed_number) map[d.bed_number] = d
  })
  return map
})

// Generate bed labels 1..capacity
const beds = computed(() =>
  Array.from({ length: props.room.capacity }, (_, i) => String(i + 1))
)

// Assign modal
const showAssign = ref(false)
const assignBed  = ref(null)
const assignId   = ref('')

function openAssign(bed) {
  assignBed.value = bed
  assignId.value  = ''
  showAssign.value = true
}

function submitAssign() {
  router.post(route('rh.rooms.assign-bed', props.room.id), {
    rh_intern_id: Number(assignId.value),
    bed_number:   assignBed.value,
  }, {
    preserveScroll: true,
    onSuccess: () => { showAssign.value = false },
  })
}

async function unassign(dormer) {
  const confirmed = await confirmAction({
    title: 'Remove from bed?',
    text: `Remove ${dormer.name} from Bed ${dormer.bed_number}?`,
    confirmText: 'Remove',
  })
  if (!confirmed) return
  router.post(route('rh.rooms.unassign-bed', props.room.id), {
    rh_intern_id: dormer.id,
  }, { preserveScroll: true })
}

const hallColor = props.room.residence_hall === 'BRH' ? 'indigo' : 'pink'
const hallLabel = props.room.residence_hall === 'BRH' ? "Boys Residence Hall" : "Girls Residence Hall"

const occupiedCount = computed(() => props.dormers.filter(d => d.bed_number).length)
</script>

<template>
  <Head :title="`Room ${room.room_number} — Bed Map`" />
  <AdminLayout title="Residence Hall">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex items-center gap-3">
        <AppButton as="link" variant="secondary" size="sm" :href="route('rh.rooms.index')">
          <ArrowLeftIcon class="w-4 h-4" />
        </AppButton>
        <div class="flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <h1 class="font-heading text-xl font-semibold text-slate-800">Room {{ room.room_number }}</h1>
            <AppBadge :color="room.residence_hall === 'BRH' ? 'indigo' : 'purple'">{{ room.residence_hall }}</AppBadge>
            <AppBadge :color="room.status === 'active' ? 'green' : 'slate'">{{ room.status }}</AppBadge>
          </div>
          <p class="text-sm text-slate-500 mt-0.5">{{ hallLabel }}{{ room.floor ? ' · Floor ' + room.floor : '' }}</p>
        </div>
        <div class="text-right shrink-0">
          <p class="text-2xl font-bold text-slate-800">{{ occupiedCount }}<span class="text-slate-400 text-base font-normal"> / {{ room.capacity }}</span></p>
          <p class="text-xs text-slate-400">beds occupied</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">

        <!-- Bed Grid -->
        <div class="lg:col-span-3 bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 p-6">
          <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-4">Bed Layout</p>

          <!-- 2-column bunk grid -->
          <div class="grid grid-cols-2 gap-4">
            <div v-for="bed in beds" :key="bed"
                 :class="['rounded-xl border-2 p-4 min-h-[100px] flex flex-col justify-between transition-all',
                   bedMap[bed]
                     ? (room.residence_hall === 'BRH' ? 'border-indigo-300 bg-indigo-50' : 'border-pink-300 bg-pink-50')
                     : 'border-dashed border-slate-200 bg-slate-50 hover:border-indigo-300']">

              <div class="flex items-start justify-between">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wide">Bed {{ bed }}</span>
                <UserIcon v-if="bedMap[bed]" class="w-4 h-4"
                          :class="room.residence_hall === 'BRH' ? 'text-indigo-400' : 'text-pink-400'" />
              </div>

              <!-- Occupied -->
              <template v-if="bedMap[bed]">
                <div class="mt-2">
                  <p class="text-sm font-semibold text-slate-800">{{ bedMap[bed].name }}</p>
                </div>
                <button @click="unassign(bedMap[bed])"
                        class="mt-3 text-xs text-slate-400 hover:text-rose-500 text-left transition-colors">
                  Remove from bed
                </button>
              </template>

              <!-- Vacant -->
              <template v-else>
                <p class="text-xs text-slate-400 mt-2 flex-1">Vacant</p>
                <button @click="openAssign(bed)"
                        class="mt-3 text-xs text-indigo-600 hover:underline font-medium text-left">
                  Assign dormer
                </button>
              </template>
            </div>
          </div>

          <p class="text-xs text-slate-400 mt-4">Click a vacant bed to assign a dormer, or "Remove from bed" to clear an assignment.</p>
        </div>

        <!-- Unassigned dormers panel -->
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 p-4 lg:sticky lg:top-4 self-start">
          <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">
            Unassigned in {{ room.residence_hall }} ({{ unassignedList.length }})
          </p>
          <div class="space-y-1.5 max-h-80 overflow-y-auto">
            <div v-for="d in unassignedList" :key="d.id"
                 class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 border border-slate-100 text-xs text-slate-700">
              <UserIcon class="w-3.5 h-3.5 text-slate-400 shrink-0" />
              <span class="truncate">{{ d.name }}</span>
              <span class="text-slate-400 ml-auto shrink-0">#{{ d.id }}</span>
            </div>
            <p v-if="!unassignedList.length" class="text-xs text-slate-400 text-center py-4">
              All dormers in this hall have a bed assignment.
            </p>
          </div>
          <Link :href="route('rh.interns.index')"
                class="mt-4 block text-xs text-indigo-600 hover:underline text-center">
            Go to Dormer Roster →
          </Link>
        </div>

      </div>

    </div>

    <!-- Assign Modal -->
    <AppModal :show="showAssign" :title="`Assign to Bed ${assignBed}`" :subtitle="`Room ${room.room_number} · ${room.residence_hall}`" size="sm" @close="showAssign = false">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Select Dormer</label>
        <select v-model="assignId"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">— Select —</option>
          <option v-for="d in unassignedList" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <p v-if="!unassignedList.length" class="text-xs text-slate-400 mt-2">
          No unassigned dormers in this hall. Assign via the Dormer Roster first.
        </p>
      </div>

      <template #footer>
        <div class="flex gap-3">
          <AppButton block variant="secondary" @click="showAssign = false">Cancel</AppButton>
          <AppButton block :disabled="!assignId" @click="submitAssign">Assign</AppButton>
        </div>
      </template>
    </AppModal>

  </AdminLayout>
</template>
